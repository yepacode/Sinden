<?php

namespace App\Imports;

use App\Models\TablaPrecioServicio;

/**
 * Importador de la tabla de precios en el FORMATO DEL CLIENTE (layout matricial
 * del PDF): una hoja por servicio, sub-tablas "N Servicios", filas por rango de
 * largo (1-60/61-120/121-320/>320) y columnas por calibre (#22 ... 1/2").
 *
 * Tolerante:
 *  - Detecta las columnas de calibre por la fila de claves (#22...) Y/O por la
 *    fila de mm (0.76mm...), de modo que si el cliente marca la columna de 4mm
 *    con un guion "-", igual se identifica por su espesor.
 *  - Acepta numeros con "$", separadores de miles ("." o ",") y decimales.
 *  - Reconoce ">200" / ">320" como los rangos abiertos (min 201 / 321).
 *  - Compatibilidad hacia atras: si la hoja trae el formato plano viejo
 *    (columna "tipo_servicio"), la procesa con el lector antiguo.
 *
 * Uso: $import->procesar(Excel::toArray($import, $archivo)).
 */
class TablaPreciosImport
{
    protected int $actualizados = 0;
    protected int $sinCambio = 0;
    protected int $noEncontradas = 0;
    protected int $invalidas = 0;
    protected int $vacias = 0;
    protected array $errores = [];

    /** claveNormalizada => clave real (ej "1/8\"") */
    private array $claveByNorm = [];
    /** mm*100 (int) => clave real */
    private array $claveByMm = [];
    /** etiquetaNormalizada => tipo_servicio (slug) */
    private array $slugByEtiqueta = [];
    /** slug => true */
    private array $slugs = [];
    private bool $catalogoCargado = false;

    // ─────────────────────────────────────────────────────────────

    public function procesar(array $sheets): void
    {
        $this->cargarCatalogo();

        foreach ($sheets as $rows) {
            if (!is_array($rows) || count($rows) === 0) {
                continue;
            }
            $this->procesarHoja($rows);
        }
    }

    private function cargarCatalogo(): void
    {
        if ($this->catalogoCargado) {
            return;
        }

        foreach (TablaPrecioServicio::getDistinctCalibres() as $c) {
            $this->claveByNorm[$this->norm($c->clave_calibre)] = $c->clave_calibre;
            $this->claveByMm[(int) round(((float) $c->calibre_mm) * 100)] = $c->clave_calibre;
        }

        foreach (TablaPrecioServicio::getDistinctServicios() as $s) {
            $this->slugByEtiqueta[$this->norm($s->etiqueta_servicio)] = $s->tipo_servicio;
            $this->slugs[$s->tipo_servicio] = true;
        }

        $this->catalogoCargado = true;
    }

    // ─── Ruteo por hoja ───────────────────────────────────────────

    private function procesarHoja(array $rows): void
    {
        if ($this->esFormatoPlano($rows)) {
            $this->procesarHojaPlana($rows);
            return;
        }

        $this->procesarHojaMatriz($rows);
    }

    private function esFormatoPlano(array $rows): bool
    {
        $tope = min(3, count($rows));
        for ($i = 0; $i < $tope; $i++) {
            foreach ((array) $rows[$i] as $celda) {
                if ($this->norm($celda) === 'TIPO_SERVICIO') {
                    return true;
                }
            }
        }
        return false;
    }

    // ─── Formato matricial (cliente) ──────────────────────────────

    private function procesarHojaMatriz(array $rows): void
    {
        [$colMap, $headerRowIdx] = $this->detectarColumnasCalibre($rows);

        if (empty($colMap)) {
            $this->errores[] = 'Una hoja no tiene columnas de calibre reconocibles; se omitio.';
            return;
        }

        $slug = $this->detectarServicio($rows, $headerRowIdx);
        if ($slug === null) {
            $titulo = $this->primerTextoNoVacio($rows);
            $this->errores[] = 'No se reconocio el servicio de la hoja' . ($titulo ? " (\"{$titulo}\")" : '') . '; se omitio.';
            return;
        }

        $cantMin = null;

        for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
            $fila = (array) $rows[$i];
            $colA = $fila[0] ?? '';
            $token = $this->extraerRango(is_scalar($colA) ? (string) $colA : '');
            $esBanda = is_string($colA) || is_numeric($colA)
                ? stripos((string) $colA, 'servicio') !== false
                : false;

            if ($esBanda) {
                if ($token !== null) {
                    $cantMin = $this->rangoMin($token);
                }
                continue;
            }

            if ($token === null || $cantMin === null) {
                continue; // titulo, fila de mm, MINIMA, vacias, etc.
            }

            $largoMin = $this->rangoMin($token);
            if ($largoMin === null) {
                continue;
            }

            $this->procesarFilaLargo($fila, $colMap, $slug, $cantMin, $largoMin, $i + 1);
        }
    }

    /**
     * Devuelve [colMap (idxColumna=>clave), indiceFilaEncabezado].
     * Combina la fila de claves (#22...) y la fila de mm (0.76mm...) para
     * cubrir columnas que una u otra no identifique (p.ej. 4mm marcado "-").
     */
    private function detectarColumnasCalibre(array $rows): array
    {
        $mejorClave = ['map' => [], 'row' => -1];
        $mejorMm    = ['map' => [], 'row' => -1];

        $tope = min(12, count($rows));
        for ($i = 0; $i < $tope; $i++) {
            $mapClave = [];
            $mapMm = [];
            foreach ((array) $rows[$i] as $col => $celda) {
                if ($col === 0) {
                    continue;
                }
                $clave = $this->claveByNorm[$this->norm($celda)] ?? null;
                if ($clave !== null) {
                    $mapClave[$col] = $clave;
                }
                $mm = $this->extraerMm($celda);
                if ($mm !== null && isset($this->claveByMm[$mm])) {
                    $mapMm[$col] = $this->claveByMm[$mm];
                }
            }
            if (count($mapClave) > count($mejorClave['map'])) {
                $mejorClave = ['map' => $mapClave, 'row' => $i];
            }
            if (count($mapMm) > count($mejorMm['map'])) {
                $mejorMm = ['map' => $mapMm, 'row' => $i];
            }
        }

        // Union: la fila de claves manda; la de mm rellena huecos.
        $colMap = $mejorMm['map'];
        foreach ($mejorClave['map'] as $col => $clave) {
            $colMap[$col] = $clave;
        }

        $headerRowIdx = max($mejorClave['row'], $mejorMm['row']);

        return [$colMap, $headerRowIdx];
    }

    private function detectarServicio(array $rows, int $headerRowIdx): ?string
    {
        $tope = max(1, $headerRowIdx + 1);
        for ($i = 0; $i < $tope && $i < count($rows); $i++) {
            foreach ((array) $rows[$i] as $celda) {
                $n = $this->norm($celda);
                if ($n === '') {
                    continue;
                }
                if (isset($this->slugByEtiqueta[$n])) {
                    return $this->slugByEtiqueta[$n];
                }
                if (isset($this->slugs[strtolower(trim((string) $celda))])) {
                    return strtolower(trim((string) $celda));
                }
            }
        }
        return null;
    }

    private function procesarFilaLargo(array $fila, array $colMap, string $slug, int $cantMin, int $largoMin, int $numeroFila): void
    {
        foreach ($colMap as $col => $clave) {
            $raw = $fila[$col] ?? null;
            $precio = $this->parseNumero($raw);

            if ($precio === null) {
                $this->vacias++;
                continue;
            }
            if ($precio === false) {
                $this->invalidas++;
                if (count($this->errores) < 12) {
                    $this->errores[] = "Fila {$numeroFila}, calibre {$clave}: valor no numerico (\"{$raw}\").";
                }
                continue;
            }

            $registro = TablaPrecioServicio::where('tipo_servicio', $slug)
                ->where('clave_calibre', $clave)
                ->where('cantidad_servicios_min', $cantMin)
                ->where('largo_mm_min', $largoMin)
                ->first();

            if (!$registro) {
                $this->noEncontradas++;
                if (count($this->errores) < 12) {
                    $this->errores[] = "No existe registro ({$slug} / {$clave} / cant>={$cantMin} / largo>={$largoMin}).";
                }
                continue;
            }

            if ((float) $registro->precio === (float) $precio) {
                $this->sinCambio++;
                continue;
            }

            $registro->update(['precio' => $precio]);
            $this->actualizados++;
        }
    }

    // ─── Formato plano (compatibilidad hacia atras) ───────────────

    private function procesarHojaPlana(array $rows): void
    {
        $encabezados = [];
        foreach ((array) $rows[0] as $col => $celda) {
            $encabezados[$col] = $this->slug((string) $celda);
        }

        for ($i = 1; $i < count($rows); $i++) {
            $assoc = [];
            foreach ((array) $rows[$i] as $col => $celda) {
                if (isset($encabezados[$col]) && $encabezados[$col] !== '') {
                    $assoc[$encabezados[$col]] = $celda;
                }
            }

            $data = [
                'tipo_servicio' => $assoc['tipo_servicio'] ?? null,
                'calibre' => $assoc['calibre'] ?? ($assoc['clave_calibre'] ?? null),
                'cantidad_servicios_min' => $assoc['cantidad_servicios_min'] ?? ($assoc['largo_rango_min'] ?? null),
                'largo_mm_min' => $assoc['largo_mm_min'] ?? ($assoc['cantidad_rango_min'] ?? null),
                'precio' => $assoc['precio'] ?? null,
            ];

            $vacia = true;
            foreach ($data as $v) {
                if ($v !== null && $v !== '') { $vacia = false; break; }
            }
            if ($vacia) { $this->vacias++; continue; }

            if (empty($data['tipo_servicio']) || empty($data['calibre'])
                || !is_numeric($data['cantidad_servicios_min'])
                || !is_numeric($data['largo_mm_min'])
                || !is_numeric($data['precio'])) {
                $this->invalidas++;
                continue;
            }

            $registro = TablaPrecioServicio::where('tipo_servicio', $data['tipo_servicio'])
                ->where('clave_calibre', $data['calibre'])
                ->where('cantidad_servicios_min', (int) $data['cantidad_servicios_min'])
                ->where('largo_mm_min', (int) $data['largo_mm_min'])
                ->first();

            if (!$registro) { $this->noEncontradas++; continue; }

            $precioNuevo = (float) $data['precio'];
            if ((float) $registro->precio === $precioNuevo) { $this->sinCambio++; continue; }

            $registro->update(['precio' => $precioNuevo]);
            $this->actualizados++;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /** Extrae el token de rango inicial: "1-5 Servicios"->"1-5", ">320"->">320", "61-120"->"61-120". */
    private function extraerRango(string $texto): ?string
    {
        if (preg_match('/^\s*(>\s*\d+|\d+\s*-\s*\d+|\d+\s*-)/u', $texto, $m)) {
            return preg_replace('/\s+/', '', $m[1]);
        }
        return null;
    }

    /** Min del rango segun convencion de la BD: "1-60"->1, ">320"->321, ">200"->201. */
    private function rangoMin(string $token): ?int
    {
        $token = preg_replace('/\s+/', '', $token);
        if (strpos($token, '>') === 0) {
            return (int) substr($token, 1) + 1;
        }
        if (preg_match('/^(\d+)-/', $token, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /** Extrae mm de una celda tipo "(0.76mm)" o "0,76" -> entero mm*100, o null. */
    private function extraerMm($celda): ?int
    {
        if ($celda === null || $celda === '') {
            return null;
        }
        $s = str_replace(',', '.', (string) $celda);
        if (preg_match('/(\d+(?:\.\d+)?)/', $s, $m)) {
            return (int) round((float) $m[1] * 100);
        }
        return null;
    }

    /**
     * Parsea un precio. Devuelve:
     *   null  -> celda vacia
     *   false -> valor no numerico (invalido)
     *   float -> valor
     */
    private function parseNumero($val)
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_int($val) || is_float($val)) {
            return (float) $val;
        }

        $s = trim((string) $val);
        if ($s === '' || $s === '-') {
            return null;
        }

        // Quita moneda y espacios (incluye NBSP).
        $s = str_replace(['$', ' ', "\xc2\xa0"], '', $s);

        // Miles con puntos: 1.234.567
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        // Miles con comas: 1,234,567
        } elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
            $s = str_replace(',', '', $s);
        } else {
            // Deja solo digitos, separadores y signo; el ultimo separador es decimal.
            $s = preg_replace('/[^\d.,-]/', '', $s);
            $posC = strrpos($s, ',');
            $posP = strrpos($s, '.');
            if ($posC !== false && $posP !== false) {
                $decSep = $posC > $posP ? ',' : '.';
                $milSep = $decSep === ',' ? '.' : ',';
                $s = str_replace($milSep, '', $s);
                $s = str_replace($decSep, '.', $s);
            } elseif ($posC !== false) {
                $s = str_replace(',', '.', $s);
            }
        }

        return is_numeric($s) ? (float) $s : false;
    }

    /** Normaliza para comparar: mayusculas, sin acentos, espacios colapsados. */
    private function norm($v): string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return '';
        }
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
        ]);
        $s = preg_replace('/\s+/', ' ', $s);
        return mb_strtoupper($s);
    }

    /** Nombre de columna del formato plano -> slug (minusculas, guion bajo). */
    private function slug(string $v): string
    {
        $s = strtolower(trim($v));
        $s = preg_replace('/\s+/', '_', $s);
        return preg_replace('/[^a-z0-9_]/', '', $s);
    }

    // ─── Getters ──────────────────────────────────────────────────

    public function getActualizados(): int { return $this->actualizados; }
    public function getSinCambio(): int { return $this->sinCambio; }
    public function getNoEncontradas(): int { return $this->noEncontradas; }
    public function getInvalidas(): int { return $this->invalidas; }
    public function getVacias(): int { return $this->vacias; }
    public function getErrores(): array { return $this->errores; }
}
