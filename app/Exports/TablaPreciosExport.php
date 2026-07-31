<?php

namespace App\Exports;

use App\Exports\Sheets\ServicioPreciosSheet;
use App\Models\TablaPrecioServicio;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Exporta la tabla de precios en el formato del cliente (una hoja por servicio,
 * layout matricial del PDF). Si se pasa $tipoServicio, exporta solo ese servicio.
 */
class TablaPreciosExport implements WithMultipleSheets
{
    protected ?string $tipoServicio;

    public function __construct(?string $tipoServicio = null)
    {
        $this->tipoServicio = $tipoServicio;
    }

    public function sheets(): array
    {
        return self::buildSheets($this->tipoServicio);
    }

    /**
     * Construye las hojas matriciales de Excel a partir de la matriz de datos.
     */
    public static function buildSheets(?string $tipoServicio = null): array
    {
        $matriz = self::buildMatriz($tipoServicio);

        $titulosUsados = [];
        $sheets = [];

        foreach ($matriz['servicios'] as $servicio) {
            $titulo = self::tituloHoja($servicio['etiqueta'], $titulosUsados);

            $sheets[] = new ServicioPreciosSheet(
                $servicio['etiqueta'],
                $titulo,
                $servicio['precio_minimo'],
                $matriz['calibres'],
                $matriz['bandas'],
                $matriz['largos'],
                $servicio['grid']
            );
        }

        return $sheets;
    }

    /**
     * Fuente de verdad de la estructura matricial (reutilizada por Excel y PDF).
     *
     * @return array{
     *   calibres: array<int,array{clave:string,mm:float}>,
     *   bandas:   array<int,array{min:int,max:?int,label:string}>,
     *   largos:   array<int,array{min:int,max:?int,label:string}>,
     *   servicios: array<int,array{etiqueta:string,tipo:string,precio_minimo:mixed,grid:array}>
     * }
     */
    public static function buildMatriz(?string $tipoServicio = null): array
    {
        $calibres = TablaPrecioServicio::getDistinctCalibres()
            ->map(fn ($c) => ['clave' => $c->clave_calibre, 'mm' => (float) $c->calibre_mm])
            ->values()->all();

        $bandas = TablaPrecioServicio::getDistinctCantidadesServicios()
            ->map(fn ($b) => self::rango((int) $b->cantidad_servicios_min, $b->cantidad_servicios_max))
            ->values()->all();

        $largos = TablaPrecioServicio::getDistinctLargosMm()
            ->map(fn ($l) => self::rango((int) $l->largo_mm_min, $l->largo_mm_max))
            ->values()->all();

        $serviciosDb = TablaPrecioServicio::getDistinctServicios();
        if ($tipoServicio) {
            $serviciosDb = $serviciosDb->where('tipo_servicio', $tipoServicio)->values();
        }

        $servicios = [];
        foreach ($serviciosDb as $servicio) {
            $precios = TablaPrecioServicio::forServicio($servicio->tipo_servicio)->get();
            $mapa = [];
            foreach ($precios as $p) {
                $mapa[$p->cantidad_servicios_min . '|' . $p->largo_mm_min . '|' . $p->clave_calibre] = $p->precio;
            }

            $grid = [];
            foreach ($bandas as $bIdx => $banda) {
                foreach ($largos as $lIdx => $largo) {
                    foreach ($calibres as $cIdx => $cal) {
                        $key = $banda['min'] . '|' . $largo['min'] . '|' . $cal['clave'];
                        $grid[$bIdx][$lIdx][$cIdx] = isset($mapa[$key]) ? (float) $mapa[$key] : null;
                    }
                }
            }

            $servicios[] = [
                'etiqueta'      => $servicio->etiqueta_servicio,
                'tipo'          => $servicio->tipo_servicio,
                'precio_minimo' => $servicio->precio_minimo,
                'grid'          => $grid,
            ];
        }

        return [
            'calibres'  => $calibres,
            'bandas'    => $bandas,
            'largos'    => $largos,
            'servicios' => $servicios,
        ];
    }

    /** Construye {min,max,label} con la convencion ">N" cuando max es null. */
    private static function rango(int $min, $max): array
    {
        return [
            'min'   => $min,
            'max'   => $max === null ? null : (int) $max,
            'label' => $max === null ? '>' . ($min - 1) : $min . '-' . $max,
        ];
    }

    /** Nombre de pestana valido (<=31 chars, sin caracteres ilegales, unico). */
    private static function tituloHoja(string $etiqueta, array &$usados): string
    {
        $base = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $etiqueta);
        $base = trim(preg_replace('/\s+/', ' ', $base));
        $base = mb_substr($base, 0, 31);

        $titulo = $base;
        $i = 2;
        while (in_array(mb_strtolower($titulo), $usados, true)) {
            $sufijo = ' ' . $i++;
            $titulo = mb_substr($base, 0, 31 - mb_strlen($sufijo)) . $sufijo;
        }
        $usados[] = mb_strtolower($titulo);

        return $titulo;
    }
}
