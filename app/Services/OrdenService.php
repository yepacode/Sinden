<?php

namespace App\Services;

use App\Models\AsignacionPieza;
use App\Models\HistorialAvance;
use App\Models\Orden;
use App\Models\OrdenBosquejo;
use App\Models\OrdenItem;
use App\Models\OrdenPieza;
use App\Models\Pago;
use App\Models\User;
use App\Services\NotificacionService;
use App\Traits\RegistraActividad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Helpers\ImageHelper;
use Intervention\Image\Facades\Image;

class OrdenService
{
    use RegistraActividad;

    protected OrdenEstadoService $estadoService;

    public function __construct(OrdenEstadoService $estadoService)
    {
        $this->estadoService = $estadoService;
    }

    /**
     * Guarda orden como borrador. Validacion minima.
     */
    public function guardarBorrador(array $data, User $user, ?Orden $orden = null): Orden
    {
        if ($orden) {
            $orden->update([
                'cliente_id' => $data['cliente_id'] ?? $orden->cliente_id,
                'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'hora_entrega' => $data['hora_entrega'] ?? null,
                'notas' => $data['notas'] ?? null,
            ]);
        } else {
            $orden = Orden::create([
                'cliente_id' => $data['cliente_id'],
                'creado_por' => $user->id,
                'estado_trabajo' => 'borrador',
                'fecha_entrega' => $data['fecha_entrega'] ?? null,
                'hora_entrega' => $data['hora_entrega'] ?? null,
                'notas' => $data['notas'] ?? null,
            ]);
        }

        // Sincronizar entidades hijas
        if (isset($data['items'])) {
            $this->sincronizarItems($orden, $data['items']);
        }

        $bosquejoMap = [];
        $bosquejosSincronizados = [];
        if (isset($data['bosquejos'])) {
            [$bosquejoMap, $bosquejosSincronizados] = $this->sincronizarBosquejos($orden, $data['bosquejos']);
        }

        if (isset($data['piezas'])) {
            $this->sincronizarPiezas($orden, $data['piezas'], $bosquejoMap);

            // Aplicar asignacion de operario por pieza. Modo segun estado:
            // - borrador: solo persiste operario_actual_id/requiere_operario/estado sin crear asignaciones
            // - generada u otro: aplica transiciones completas con asignaciones, historial y log
            $modo = $orden->estado_trabajo === 'borrador' ? 'borrador' : 'edicion';
            $this->sincronizarOperariosPorPieza($orden, $data['piezas'], $user, $modo);
        }

        if (isset($data['pagos'])) {
            $this->sincronizarPagos($orden, $data['pagos'], $user);
        }

        if (!empty($data['firma_data'])) {
            $ruta = $this->guardarFirma($orden, $data['firma_data']);
            $orden->update(['ruta_firma_cliente' => $ruta]);
        }

        // Recalcular totales
        $this->estadoService->recalcularTotales($orden);
        $orden->save();

        // Adjuntar bosquejos sincronizados para que el frontend actualice IDs y rutas
        $orden->bosquejosSincronizados = $bosquejosSincronizados;

        return $orden;
    }

    /**
     * Genera orden con validacion completa y numero consecutivo.
     */
    public function generarOrden(array $data, User $user, ?Orden $orden = null): Orden
    {
        // Validar server-side
        $errores = $this->validarParaGenerar($data);
        if (!empty($errores)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], []),
                response()->json(['success' => false, 'message' => 'Falta diligenciar informacion para poder GENERAR ORDEN', 'errores' => $errores], 422)
            );
        }

        // Guardar borrador primero (persiste todos los datos)
        $orden = $this->guardarBorrador($data, $user, $orden);

        // Remover atributo transiente para que no se intente persistir en BD
        $bosquejosSincronizados = $orden->bosquejosSincronizados ?? [];
        unset($orden->bosquejosSincronizados);

        // Asignar numero consecutivo y marcar como generada
        $orden->numero_orden = $this->estadoService->generarNumeroConsecutivo();
        $orden->estado_trabajo = 'generada';
        $orden->save();

        $piezas = $orden->piezas()->get();

        if ($piezas->isEmpty()) {
            // Venta directa: sin piezas
            $orden->estado_trabajo = 'ejecutada';
            $orden->estado_entrega = 'entregada';
        } else {
            // Aplicar asignaciones iniciales: piezas con operario_id reciben AsignacionPieza
            // tipo='inicial', piezas sin operario quedan completadas al 100%.
            $this->sincronizarOperariosPorPieza($orden, $data['piezas'] ?? [], $user, 'inicial');

            // Notificar a cada operario asignado (unicos)
            $operariosAsignados = $orden->piezas()
                ->whereNotNull('operario_actual_id')
                ->pluck('operario_actual_id')
                ->unique();
            foreach ($operariosAsignados as $opId) {
                NotificacionService::ordenGenerada($orden, (int) $opId);
            }

            // Recalcular estado basado en piezas actualizadas
            $orden->estado_trabajo = $this->estadoService->recalcularEstadoTrabajo($orden->fresh());
        }

        // Recalcular estado de pago
        $orden->estado_pago = $this->estadoService->recalcularEstadoPago($orden);
        $orden->save();

        // Re-adjuntar bosquejos sincronizados para respuesta al frontend
        $orden->bosquejosSincronizados = $bosquejosSincronizados;

        return $orden;
    }

    /**
     * Sube un bosquejo temporal (mid-wizard, via AJAX).
     */
    public function subirBosquejoTemporal($archivo, string $tipoOrigen, ?int $ordenId = null, ?string $nombre = null, ?int $plantillaId = null): array
    {
        // Determinar carpeta destino
        if ($ordenId) {
            $uploadPath = public_path("uploads/ordenes/{$ordenId}/bosquejos");
        } else {
            $sessionKey = session()->getId();
            $uploadPath = public_path("uploads/ordenes/temp_{$sessionKey}");
        }

        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $extension = $archivo->getClientOriginalExtension();
        $timestamp = time();
        $uniqueId = uniqid();
        $fileName = "bosquejo_{$timestamp}_{$uniqueId}.{$extension}";
        $thumbName = "thumb_{$timestamp}_{$uniqueId}.{$extension}";

        // Guardar archivo original
        $archivo->move($uploadPath, $fileName);

        $relativePath = $ordenId
            ? "uploads/ordenes/{$ordenId}/bosquejos/{$fileName}"
            : "uploads/ordenes/temp_" . session()->getId() . "/{$fileName}";

        // Hacer cuadrada y generar miniatura
        $thumbRelative = $relativePath;
        try {
            ImageHelper::makeSquare("{$uploadPath}/{$fileName}");
            ImageHelper::makeSquareThumbnail("{$uploadPath}/{$fileName}", "{$uploadPath}/{$thumbName}");
            $thumbRelative = $ordenId
                ? "uploads/ordenes/{$ordenId}/bosquejos/{$thumbName}"
                : "uploads/ordenes/temp_" . session()->getId() . "/{$thumbName}";
        } catch (\Exception $e) {
            // Si falla thumbnail, usar original
        }

        return [
            'nombre' => $nombre ?: pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME),
            'tipo_origen' => $tipoOrigen,
            'ruta_archivo' => $relativePath,
            'ruta_miniatura' => $thumbRelative,
            'plantilla_bosquejo_id' => $plantillaId,
        ];
    }

    /**
     * Sube una imagen desde base64 (dibujo tablet / firma).
     */
    public function subirBase64ComoBosquejo(string $base64Data, string $tipoOrigen, ?int $ordenId = null, ?string $nombre = null): array
    {
        // Determinar carpeta destino
        if ($ordenId) {
            $uploadPath = public_path("uploads/ordenes/{$ordenId}/bosquejos");
        } else {
            $sessionKey = session()->getId();
            $uploadPath = public_path("uploads/ordenes/temp_{$sessionKey}");
        }

        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        // Decodificar base64
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        $imageData = base64_decode($imageData);

        $timestamp = time();
        $uniqueId = uniqid();
        $fileName = "bosquejo_{$timestamp}_{$uniqueId}.png";
        $thumbName = "thumb_{$timestamp}_{$uniqueId}.png";

        file_put_contents("{$uploadPath}/{$fileName}", $imageData);

        $relativePath = $ordenId
            ? "uploads/ordenes/{$ordenId}/bosquejos/{$fileName}"
            : "uploads/ordenes/temp_" . session()->getId() . "/{$fileName}";

        // Hacer cuadrada y generar miniatura
        $thumbRelative = $relativePath;
        try {
            ImageHelper::makeSquare("{$uploadPath}/{$fileName}");
            ImageHelper::makeSquareThumbnail("{$uploadPath}/{$fileName}", "{$uploadPath}/{$thumbName}");
            $thumbRelative = $ordenId
                ? "uploads/ordenes/{$ordenId}/bosquejos/{$thumbName}"
                : "uploads/ordenes/temp_" . session()->getId() . "/{$thumbName}";
        } catch (\Exception $e) {
            // Si falla thumbnail, usar original
        }

        return [
            'nombre' => $nombre ?: "Dibujo " . date('d/m/Y H:i'),
            'tipo_origen' => $tipoOrigen,
            'ruta_archivo' => $relativePath,
            'ruta_miniatura' => $thumbRelative,
            'plantilla_bosquejo_id' => null,
        ];
    }

    /**
     * Validacion server-side para generar orden.
     */
    public function validarParaGenerar(array $data): array
    {
        $errores = [];

        if (empty($data['cliente_id'])) {
            $errores[] = 'Debe seleccionar un cliente.';
        }

        if (empty($data['items']) || count($data['items']) === 0) {
            $errores[] = 'Debe agregar al menos un item.';
        } else {
            foreach ($data['items'] as $i => $item) {
                $num = $i + 1;
                if (empty($item['descripcion'])) {
                    $errores[] = "Item {$num}: falta descripcion.";
                }
                if (empty($item['cantidad']) || $item['cantidad'] <= 0) {
                    $errores[] = "Item {$num}: cantidad debe ser mayor a 0.";
                }
                if (!isset($item['precio_unitario']) || $this->normalizarMonto($item['precio_unitario']) < 0) {
                    $errores[] = "Item {$num}: precio no valido.";
                }
            }
        }

        if (empty($data['fecha_entrega'])) {
            $errores[] = 'Debe indicar la fecha de entrega.';
        }
        if (empty($data['hora_entrega'])) {
            $errores[] = 'Debe indicar la hora de entrega.';
        }

        return $errores;
    }

    // ========================================
    // Metodos protegidos de sincronizacion
    // ========================================

    /**
     * Normaliza un monto que puede llegar como numero o como string formateado
     * en pesos (ej. "1.500.000,50"). Garantiza que el resto del sistema reciba
     * siempre un float, sin importar el formato del input del frontend.
     */
    protected function normalizarMonto($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        if (is_string($valor)) {
            // Quitar simbolos y separadores de miles (.), coma decimal -> punto
            $limpio = str_replace(['$', ' ', '.'], '', $valor);
            $limpio = str_replace(',', '.', $limpio);
            return is_numeric($limpio) ? (float) $limpio : 0.0;
        }
        return 0.0;
    }

    /**
     * Sincroniza items: delete-and-recreate.
     */
    protected function sincronizarItems(Orden $orden, array $items): void
    {
        $orden->items()->delete();

        foreach ($items as $item) {
            $cantidad = floatval($item['cantidad'] ?? 0);
            $precioUnitario = $this->normalizarMonto($item['precio_unitario'] ?? 0);
            $porcentajeIva = floatval($item['porcentaje_iva'] ?? 19);
            $descuentoPorcentaje = max(0, min(100, floatval($item['descuento_porcentaje'] ?? 0)));

            // Peso colombiano sin centavos: redondear a pesos enteros para que
            // el total mostrado sea exactamente el total real (y el abonable).
            $base = round($cantidad * $precioUnitario, 0);
            $descuentoMonto = round($base * $descuentoPorcentaje / 100, 0);
            $montoIva = round($base * ($porcentajeIva / 100), 0);

            OrdenItem::create([
                'orden_id' => $orden->id,
                'catalogo_item_id' => $item['catalogo_item_id'] ?? null,
                'codigo' => $item['codigo'] ?? null,
                'descripcion' => $item['descripcion'] ?? '',
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'porcentaje_iva' => $porcentajeIva,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_monto' => $descuentoMonto,
                'categoria' => $item['categoria'] ?? 'servicio',
                'subtotal' => $base,
                'monto_iva' => $montoIva,
                'total' => $base + $montoIva - $descuentoMonto,
            ]);
        }
    }

    /**
     * Sincroniza bosquejos: mueve archivos de temp si es necesario, crea registros.
     * Retorna mapa [indice_array => db_id] para resolver referencias de piezas.
     */
    protected function sincronizarBosquejos(Orden $orden, array $bosquejos): array
    {
        // Obtener IDs existentes para comparar
        $existingIds = $orden->bosquejos()->pluck('id')->toArray();
        $keepIds = [];
        $indexToIdMap = [];
        $bosquejosSincronizados = [];

        $ordenPath = public_path("uploads/ordenes/{$orden->id}/bosquejos");
        if (!File::exists($ordenPath)) {
            File::makeDirectory($ordenPath, 0755, true);
        }

        foreach ($bosquejos as $index => $bosquejo) {
            // Si ya tiene ID, mantener el registro existente
            if (!empty($bosquejo['id'])) {
                $keepIds[] = $bosquejo['id'];
                $indexToIdMap[$index] = $bosquejo['id'];
                $existing = OrdenBosquejo::find($bosquejo['id']);
                if ($existing) {
                    $bosquejosSincronizados[] = [
                        'id' => $existing->id,
                        'nombre' => $existing->nombre,
                        'tipo_origen' => $existing->tipo_origen,
                        'ruta_archivo' => $existing->ruta_archivo,
                        'ruta_miniatura' => $existing->ruta_miniatura,
                        'plantilla_bosquejo_id' => $existing->plantilla_bosquejo_id,
                    ];
                }
                continue;
            }

            // Mover archivo de temp si es necesario
            $rutaArchivo = $bosquejo['ruta_archivo'] ?? '';
            $rutaMiniatura = $bosquejo['ruta_miniatura'] ?? $rutaArchivo;
            $tipoOrigen = $bosquejo['tipo_origen'] ?? 'archivo_local';

            if (str_contains($rutaArchivo, 'temp_')) {
                $rutaArchivo = $this->moverArchivoDeTemp($rutaArchivo, $orden->id);
                if ($rutaMiniatura && str_contains($rutaMiniatura, 'temp_')) {
                    $rutaMiniatura = $this->moverArchivoDeTemp($rutaMiniatura, $orden->id);
                }
            } elseif (in_array($tipoOrigen, ['plantilla', 'grupo_plantillas'], true)
                && str_contains($rutaArchivo, 'bosquejos-matriz')) {
                // Copiar archivo de la matriz a la carpeta de la orden para que cada orden
                // tenga su propia copia (no referencias compartidas a la matriz).
                $rutaArchivo = $this->copiarArchivoDePlantilla($rutaArchivo, $orden->id);
                if ($rutaMiniatura && str_contains($rutaMiniatura, 'bosquejos-matriz')) {
                    $rutaMiniatura = $this->copiarArchivoDePlantilla($rutaMiniatura, $orden->id);
                } else {
                    $rutaMiniatura = $rutaArchivo;
                }
            }

            // Idempotencia: si ya existe un bosquejo en esta orden con la misma ruta_archivo,
            // reutilizarlo en lugar de crear un duplicado. Esto evita que el autosave
            // dispare creaciones duplicadas seguidas de File::delete del archivo compartido.
            $existentePorRuta = OrdenBosquejo::where('orden_id', $orden->id)
                ->where('ruta_archivo', $rutaArchivo)
                ->first();
            if ($existentePorRuta) {
                $keepIds[] = $existentePorRuta->id;
                $indexToIdMap[$index] = $existentePorRuta->id;
                $bosquejosSincronizados[] = [
                    'id' => $existentePorRuta->id,
                    'nombre' => $existentePorRuta->nombre,
                    'tipo_origen' => $existentePorRuta->tipo_origen,
                    'ruta_archivo' => $existentePorRuta->ruta_archivo,
                    'ruta_miniatura' => $existentePorRuta->ruta_miniatura,
                    'plantilla_bosquejo_id' => $existentePorRuta->plantilla_bosquejo_id,
                ];
                continue;
            }

            $registro = OrdenBosquejo::create([
                'orden_id' => $orden->id,
                'plantilla_bosquejo_id' => $bosquejo['plantilla_bosquejo_id'] ?? null,
                'tipo_origen' => $bosquejo['tipo_origen'] ?? 'archivo_local',
                'nombre' => $bosquejo['nombre'] ?? 'Bosquejo ' . ($index + 1),
                'ruta_archivo' => $rutaArchivo,
                'ruta_miniatura' => $rutaMiniatura,
                'orden_visual' => $index,
            ]);

            $keepIds[] = $registro->id;
            $indexToIdMap[$index] = $registro->id;
            $bosquejosSincronizados[] = [
                'id' => $registro->id,
                'nombre' => $registro->nombre,
                'tipo_origen' => $registro->tipo_origen,
                'ruta_archivo' => $registro->ruta_archivo,
                'ruta_miniatura' => $registro->ruta_miniatura,
                'plantilla_bosquejo_id' => $registro->plantilla_bosquejo_id,
            ];
        }

        // Eliminar bosquejos que ya no estan en la lista
        $toDelete = array_diff($existingIds, $keepIds);
        if (!empty($toDelete)) {
            $oldBosquejos = OrdenBosquejo::whereIn('id', $toDelete)->get();
            foreach ($oldBosquejos as $old) {
                // Nunca borrar archivos de la matriz: son compartidos por todas las ordenes.
                $archivoEsDeMatriz = $old->ruta_archivo && str_contains($old->ruta_archivo, 'bosquejos-matriz');
                $miniaturaEsDeMatriz = $old->ruta_miniatura && str_contains($old->ruta_miniatura, 'bosquejos-matriz');

                // Safe delete: no borrar el archivo si otro registro (de esta u otra orden)
                // aun lo referencia. Previene race conditions del autosave donde un duplicado
                // puede haberse creado con la misma ruta fisica.
                $archivoAunReferenciado = $old->ruta_archivo
                    ? OrdenBosquejo::where('id', '!=', $old->id)
                        ->where(function ($q) use ($old) {
                            $q->where('ruta_archivo', $old->ruta_archivo)
                              ->orWhere('ruta_miniatura', $old->ruta_archivo);
                        })
                        ->exists()
                    : false;
                $miniaturaAunReferenciada = $old->ruta_miniatura
                    ? OrdenBosquejo::where('id', '!=', $old->id)
                        ->where(function ($q) use ($old) {
                            $q->where('ruta_archivo', $old->ruta_miniatura)
                              ->orWhere('ruta_miniatura', $old->ruta_miniatura);
                        })
                        ->exists()
                    : false;

                if (!$archivoEsDeMatriz && !$archivoAunReferenciado && $old->ruta_archivo && File::exists(public_path($old->ruta_archivo))) {
                    File::delete(public_path($old->ruta_archivo));
                }
                if (!$miniaturaEsDeMatriz && !$miniaturaAunReferenciada && $old->ruta_miniatura && $old->ruta_miniatura !== $old->ruta_archivo && File::exists(public_path($old->ruta_miniatura))) {
                    File::delete(public_path($old->ruta_miniatura));
                }
                $old->delete();
            }
        }

        return [$indexToIdMap, $bosquejosSincronizados];
    }

    /**
     * Mueve un archivo de la carpeta temp a la carpeta final de la orden.
     */
    protected function moverArchivoDeTemp(string $rutaRelativa, int $ordenId): string
    {
        $fullPath = public_path($rutaRelativa);
        if (!File::exists($fullPath)) {
            return $rutaRelativa;
        }

        $fileName = basename($rutaRelativa);
        $destDir = public_path("uploads/ordenes/{$ordenId}/bosquejos");
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        File::move($fullPath, "{$destDir}/{$fileName}");

        return "uploads/ordenes/{$ordenId}/bosquejos/{$fileName}";
    }

    /**
     * Copia un archivo de la matriz de plantillas a la carpeta de la orden.
     * A diferencia de moverArchivoDeTemp, NO mueve: preserva el original
     * porque la matriz es compartida entre todas las ordenes.
     */
    protected function copiarArchivoDePlantilla(string $rutaRelativa, int $ordenId): string
    {
        $fullPath = public_path($rutaRelativa);
        if (!File::exists($fullPath)) {
            return $rutaRelativa;
        }

        $destDir = public_path("uploads/ordenes/{$ordenId}/bosquejos");
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $extension = pathinfo($rutaRelativa, PATHINFO_EXTENSION) ?: 'png';
        $nuevoNombre = 'plantilla_' . time() . '_' . uniqid() . '.' . $extension;

        File::copy($fullPath, "{$destDir}/{$nuevoNombre}");

        return "uploads/ordenes/{$ordenId}/bosquejos/{$nuevoNombre}";
    }

    /**
     * Sincroniza piezas con UPSERT por ID. Preserva historial y asignaciones
     * de piezas existentes; crea las nuevas; elimina las que ya no estan en
     * el payload (CASCADE borra su historial, lo cual es correcto porque
     * la pieza dejo de existir).
     *
     * No toca operario_actual_id / estado / porcentaje_avance / requiere_operario
     * en piezas existentes: esos los maneja sincronizarOperariosPorPieza().
     */
    protected function sincronizarPiezas(Orden $orden, array $piezas, array $bosquejoMap = []): void
    {
        $idsExistentes = $orden->piezas()->pluck('id')->toArray();
        $idsConservar = [];

        foreach ($piezas as $index => $pieza) {
            $letra = $this->obtenerLetraPieza($index);
            $nombre = $pieza['nombre'] ?? "Pieza {$letra}";
            $cantidad = intval($pieza['cantidad'] ?? 1);
            $material = $pieza['material'] ?? null;
            $calibre = $pieza['calibre'] ?? null;
            $notas = $pieza['notas'] ?? null;

            // Resolver bosquejo_index a DB ID usando el mapa
            $ordenBosquejoId = null;
            if (isset($pieza['bosquejo_index']) && $pieza['bosquejo_index'] !== null && $pieza['bosquejo_index'] !== '') {
                $bosquejoIndex = (int) $pieza['bosquejo_index'];
                $ordenBosquejoId = $bosquejoMap[$bosquejoIndex] ?? null;
            }
            if (!$ordenBosquejoId && !empty($pieza['orden_bosquejo_id'])) {
                $ordenBosquejoId = $pieza['orden_bosquejo_id'];
            }

            // Auto-generar especificacion
            $partes = [$cantidad];
            if ($nombre) $partes[] = $nombre;
            if ($calibre) $partes[] = $calibre;
            if ($material) $partes[] = $material;
            $especificacion = implode(' - ', $partes);

            $camposBase = [
                'orden_bosquejo_id' => $ordenBosquejoId,
                'nombre' => $nombre,
                'nombre_automatico' => "Pieza {$letra}",
                'cantidad' => $cantidad,
                'material' => $material,
                'calibre' => $calibre,
                'especificacion' => $especificacion,
                'notas' => $notas,
                'orden_visual' => $index,
            ];

            $piezaId = !empty($pieza['id']) ? (int) $pieza['id'] : null;
            $existente = $piezaId ? OrdenPieza::where('id', $piezaId)
                ->where('orden_id', $orden->id)
                ->first() : null;

            if ($existente) {
                // Actualizar solo campos editables; conservar avance/operario/estado
                $existente->update($camposBase);
                $idsConservar[] = $existente->id;
            } else {
                // Crear nueva: estado inicial pendiente/0%. sincronizarOperariosPorPieza
                // ajustara requiere_operario/operario_actual_id/estado/porcentaje_avance.
                $nueva = OrdenPieza::create(array_merge($camposBase, [
                    'orden_id' => $orden->id,
                    'porcentaje_avance' => 0,
                    'estado' => 'pendiente',
                    'requiere_operario' => false,
                ]));
                $idsConservar[] = $nueva->id;
            }
        }

        // Eliminar piezas ya no presentes (CASCADE borra asignaciones e historial)
        $idsBorrar = array_diff($idsExistentes, $idsConservar);
        if (!empty($idsBorrar)) {
            OrdenPieza::whereIn('id', $idsBorrar)->delete();
        }
    }

    /**
     * Sincroniza pagos: delete-and-recreate.
     * Usa forceDelete() para no dejar pagos soft-deleted (que la UI mostraria como "rechazados").
     * El rechazo real de un pago se hace por ContabilidadController::rechazarPago.
     */
    protected function sincronizarPagos(Orden $orden, array $pagos, User $user): void
    {
        // Total recalculado a partir de items recien sincronizados (BD)
        $totalCalculado = (float) $orden->items()->sum('subtotal') + (float) $orden->items()->sum('monto_iva');
        $sumaSolicitada = (float) collect($pagos)->sum(fn($p) => $this->normalizarMonto($p['monto'] ?? 0));
        if ($sumaSolicitada > $totalCalculado + 0.005) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pagos' => [
                    'La suma de abonos ($' . number_format($sumaSolicitada, 0, ',', '.') .
                    ') excede el total de la orden ($' . number_format($totalCalculado, 0, ',', '.') . ').',
                ],
            ]);
        }

        $orden->pagos()->forceDelete();

        $autoAprueba = $user->hasAnyRole(['Administrador', 'Contabilidad']);

        foreach ($pagos as $pago) {
            $monto = $this->normalizarMonto($pago['monto'] ?? 0);
            if ($monto <= 0) continue;

            Pago::create([
                'orden_id' => $orden->id,
                'monto' => $monto,
                'metodo_pago' => $pago['metodo_pago'] ?? 'efectivo',
                'referencia_pago' => $pago['referencia_pago'] ?? null,
                'registrado_por' => $user->id,
                'aprobado' => $autoAprueba,
                'aprobado_por' => $autoAprueba ? $user->id : null,
            ]);
        }
    }

    /**
     * Guarda firma del cliente como imagen PNG.
     */
    protected function guardarFirma(Orden $orden, string $firmaBase64): string
    {
        $firmaPath = public_path("uploads/ordenes/{$orden->id}/firma");
        if (!File::exists($firmaPath)) {
            File::makeDirectory($firmaPath, 0755, true);
        }

        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $firmaBase64);
        $imageData = base64_decode($imageData);

        $fileName = "firma_" . time() . ".png";
        file_put_contents("{$firmaPath}/{$fileName}", $imageData);

        return "uploads/ordenes/{$orden->id}/firma/{$fileName}";
    }

    /**
     * Sincroniza el operario asignado por pieza segun el payload.
     *
     * Modos:
     * - 'borrador': persiste operario_actual_id / requiere_operario / estado
     *   en la pieza sin crear AsignacionPieza ni historial ni log. El operario
     *   efectivo se crea solo al generar la orden.
     * - 'inicial': la orden se acaba de generar; piezas con operario reciben
     *   AsignacionPieza tipo='inicial', piezas sin operario quedan completadas.
     * - 'edicion': la orden ya estaba generada. Se comparan valores antes/despues
     *   por pieza y se aplica la transicion correspondiente:
     *     null -> X : reset a pendiente/0%, nueva asignacion inicial, log 'pieza.operario_asignado'
     *     X -> null : completar 100%, cerrar asignacion/historial, log 'pieza.operario_removido'
     *     A -> B    : transferencia preservando avance, log 'pieza.transferida'
     *
     * $piezasPayload debe venir en el mismo orden que las piezas recien
     * sincronizadas en BD (por orden_visual).
     */
    protected function sincronizarOperariosPorPieza(Orden $orden, array $piezasPayload, User $asignadoPor, string $modo): void
    {
        $piezasDb = $orden->piezas()->orderBy('orden_visual')->get();

        foreach ($piezasDb as $index => $pieza) {
            $payload = $piezasPayload[$index] ?? null;
            if (!$payload) {
                continue;
            }

            $operarioNuevoRaw = $payload['operario_id'] ?? null;
            $operarioNuevo = ($operarioNuevoRaw === '' || $operarioNuevoRaw === null)
                ? null
                : (int) $operarioNuevoRaw;
            $operarioAnterior = $pieza->operario_actual_id ? (int) $pieza->operario_actual_id : null;

            if ($modo === 'borrador') {
                $this->aplicarBorradorOperario($pieza, $operarioNuevo);
                continue;
            }

            if ($modo === 'inicial') {
                $this->aplicarAsignacionInicial($pieza, $operarioNuevo, $asignadoPor);
                continue;
            }

            // modo 'edicion': transiciones.
            // OPCION A (fix rebote de piezas transferidas): Recepcion SOLO debe cambiar
            // el operario de una pieza si REALMENTE lo cambio en el formulario respecto
            // a lo que cargo (operario_original_id). Si no lo toco, se respeta la BD: un
            // operario pudo haber transferido / tomado / dejado en cola la pieza mientras
            // Recepcion tenia la orden abierta, y ese cambio NO se debe pisar.
            $tieneOriginal = array_key_exists('operario_original_id', $payload);
            $operarioOriginalRaw = $payload['operario_original_id'] ?? null;
            $operarioOriginal = ($operarioOriginalRaw === '' || $operarioOriginalRaw === null)
                ? null
                : (int) $operarioOriginalRaw;

            // Si el formulario no manda el original (compatibilidad) o Recepcion no
            // cambio ese campo, NO se toca la asignacion viva de la pieza.
            if (!$tieneOriginal || $operarioNuevo === $operarioOriginal) {
                continue;
            }

            // Recepcion SI cambio el operario a proposito: aplicar la transicion desde el
            // estado ACTUAL en BD (no desde el valor viejo del formulario).
            if ($operarioAnterior === $operarioNuevo) {
                continue;
            }

            if ($operarioAnterior === null && $operarioNuevo !== null) {
                $this->transicionAsignarOperario($pieza, $operarioNuevo, $asignadoPor);
            } elseif ($operarioAnterior !== null && $operarioNuevo === null) {
                $this->transicionRemoverOperario($pieza, $operarioAnterior, $asignadoPor);
            } else {
                $this->transicionTransferirOperario($pieza, $operarioAnterior, $operarioNuevo, $asignadoPor);
            }
        }
    }

    /**
     * Modo borrador: solo persiste los campos de la pieza, sin asignaciones ni logs.
     */
    protected function aplicarBorradorOperario(OrdenPieza $pieza, ?int $operarioNuevo): void
    {
        if ($operarioNuevo === null) {
            $pieza->update([
                'operario_actual_id' => null,
                'requiere_operario' => false,
                'estado' => 'pendiente',
                'porcentaje_avance' => 0,
            ]);
        } else {
            $pieza->update([
                'operario_actual_id' => $operarioNuevo,
                'requiere_operario' => true,
                'estado' => 'pendiente',
                'porcentaje_avance' => 0,
            ]);
        }
    }

    /**
     * Modo inicial (al generar la orden): crea AsignacionPieza inicial
     * o deja la pieza completada si no tiene operario.
     */
    protected function aplicarAsignacionInicial(OrdenPieza $pieza, ?int $operarioNuevo, User $asignadoPor): void
    {
        if ($operarioNuevo === null) {
            $pieza->update([
                'operario_actual_id' => null,
                'requiere_operario' => false,
                'estado' => 'completada',
                'porcentaje_avance' => 100,
            ]);
            return;
        }

        $pieza->update([
            'operario_actual_id' => $operarioNuevo,
            'requiere_operario' => true,
            'estado' => 'pendiente',
            'porcentaje_avance' => 0,
        ]);

        AsignacionPieza::create([
            'orden_pieza_id' => $pieza->id,
            'orden_id' => $pieza->orden_id,
            'asignado_desde_id' => null,
            'asignado_a_id' => $operarioNuevo,
            'asignado_por_id' => $asignadoPor->id,
            'tipo_asignacion' => 'inicial',
            'porcentaje_al_asignar' => 0,
            'activa' => true,
        ]);

        HistorialAvance::create([
            'orden_pieza_id' => $pieza->id,
            'operario_id' => $operarioNuevo,
            'porcentaje_desde' => 0,
            'porcentaje_hasta' => 0,
            'contribucion' => 0,
            'asignado_en' => now(),
            'completado_en' => null,
        ]);
    }

    /**
     * Transicion edicion: null -> operario. Resetea la pieza a pendiente/0%
     * y crea asignacion inicial.
     */
    protected function transicionAsignarOperario(OrdenPieza $pieza, int $operarioNuevo, User $asignadoPor): void
    {
        $original = $pieza->getOriginal();
        $pieza->update([
            'operario_actual_id' => $operarioNuevo,
            'requiere_operario' => true,
            'estado' => 'pendiente',
            'porcentaje_avance' => 0,
        ]);

        AsignacionPieza::create([
            'orden_pieza_id' => $pieza->id,
            'orden_id' => $pieza->orden_id,
            'asignado_desde_id' => null,
            'asignado_a_id' => $operarioNuevo,
            'asignado_por_id' => $asignadoPor->id,
            'tipo_asignacion' => 'inicial',
            'porcentaje_al_asignar' => 0,
            'activa' => true,
        ]);

        HistorialAvance::create([
            'orden_pieza_id' => $pieza->id,
            'operario_id' => $operarioNuevo,
            'porcentaje_desde' => 0,
            'porcentaje_hasta' => 0,
            'contribucion' => 0,
            'asignado_en' => now(),
            'completado_en' => null,
        ]);

        $nombreOperario = User::find($operarioNuevo)?->name ?? "#{$operarioNuevo}";
        $this->registrarActualizacion(
            'pieza.operario_asignado',
            "Operario '{$nombreOperario}' asignado a pieza '{$pieza->nombre}'",
            $pieza,
            $original,
            $pieza->orden_id
        );

        NotificacionService::ordenGenerada($pieza->orden, $operarioNuevo);
    }

    /**
     * Transicion edicion: operario -> null. Marca la pieza como completada al 100%,
     * cierra asignacion activa e historial abierto.
     */
    protected function transicionRemoverOperario(OrdenPieza $pieza, int $operarioAnterior, User $asignadoPor): void
    {
        $original = $pieza->getOriginal();
        $porcentajeActual = (float) $pieza->porcentaje_avance;

        // Cerrar asignacion activa
        AsignacionPieza::where('orden_pieza_id', $pieza->id)
            ->where('asignado_a_id', $operarioAnterior)
            ->where('activa', true)
            ->update(['activa' => false]);

        // Cerrar historial abierto del operario anterior
        HistorialAvance::where('orden_pieza_id', $pieza->id)
            ->where('operario_id', $operarioAnterior)
            ->whereNull('completado_en')
            ->update([
                'porcentaje_hasta' => $porcentajeActual,
                'contribucion' => DB::raw("({$porcentajeActual} - porcentaje_desde)"),
                'completado_en' => now(),
            ]);

        $pieza->update([
            'operario_actual_id' => null,
            'requiere_operario' => false,
            'estado' => 'completada',
            'porcentaje_avance' => 100,
        ]);

        $nombreOperario = User::find($operarioAnterior)?->name ?? "#{$operarioAnterior}";
        $this->registrarActualizacion(
            'pieza.operario_removido',
            "Operario '{$nombreOperario}' removido de pieza '{$pieza->nombre}'; marcada como completada",
            $pieza,
            $original,
            $pieza->orden_id
        );
    }

    /**
     * Transicion edicion: operario A -> operario B. Preserva el avance,
     * cierra asignacion/historial de A, crea asignacion tipo transferencia e
     * historial abierto para B.
     */
    protected function transicionTransferirOperario(OrdenPieza $pieza, int $operarioAnterior, int $operarioNuevo, User $asignadoPor): void
    {
        $original = $pieza->getOriginal();
        $porcentajeActual = (float) $pieza->porcentaje_avance;

        // Cerrar asignacion activa del operario anterior
        AsignacionPieza::where('orden_pieza_id', $pieza->id)
            ->where('asignado_a_id', $operarioAnterior)
            ->where('activa', true)
            ->update(['activa' => false]);

        // Cerrar historial abierto
        HistorialAvance::where('orden_pieza_id', $pieza->id)
            ->where('operario_id', $operarioAnterior)
            ->whereNull('completado_en')
            ->update([
                'porcentaje_hasta' => $porcentajeActual,
                'contribucion' => DB::raw("({$porcentajeActual} - porcentaje_desde)"),
                'completado_en' => now(),
            ]);

        // Nueva asignacion tipo transferencia
        AsignacionPieza::create([
            'orden_pieza_id' => $pieza->id,
            'orden_id' => $pieza->orden_id,
            'asignado_desde_id' => $operarioAnterior,
            'asignado_a_id' => $operarioNuevo,
            'asignado_por_id' => $asignadoPor->id,
            'tipo_asignacion' => 'transferencia',
            'porcentaje_al_asignar' => $porcentajeActual,
            'notas' => 'Transferida desde recepcion',
            'activa' => true,
        ]);

        // Historial abierto para el nuevo operario
        HistorialAvance::create([
            'orden_pieza_id' => $pieza->id,
            'operario_id' => $operarioNuevo,
            'porcentaje_desde' => $porcentajeActual,
            'porcentaje_hasta' => $porcentajeActual,
            'contribucion' => 0,
            'asignado_en' => now(),
            'completado_en' => null,
        ]);

        $pieza->update([
            'operario_actual_id' => $operarioNuevo,
            'requiere_operario' => true,
        ]);

        $nombreAnterior = User::find($operarioAnterior)?->name ?? "#{$operarioAnterior}";
        $nombreNuevo = User::find($operarioNuevo)?->name ?? "#{$operarioNuevo}";
        $this->registrarActualizacion(
            'pieza.transferida',
            "Pieza '{$pieza->nombre}' transferida de '{$nombreAnterior}' a '{$nombreNuevo}' (desde recepcion)",
            $pieza,
            $original,
            $pieza->orden_id
        );

        NotificacionService::ordenGenerada($pieza->orden, $operarioNuevo);
    }

    /**
     * Copia archivos de bosquejo de una orden a otra.
     */
    public function copiarArchivosBosquejo(OrdenBosquejo $bosquejo, int $nuevaOrdenId): array
    {
        $destDir = public_path("uploads/ordenes/{$nuevaOrdenId}/bosquejos");
        if (!File::exists($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $rutaArchivo = $bosquejo->ruta_archivo;
        $rutaMiniatura = $bosquejo->ruta_miniatura;

        if ($rutaArchivo && File::exists(public_path($rutaArchivo))) {
            $ext = pathinfo($rutaArchivo, PATHINFO_EXTENSION);
            $newFile = uniqid('bosquejo_') . '.' . $ext;
            File::copy(public_path($rutaArchivo), "{$destDir}/{$newFile}");
            $rutaArchivo = "uploads/ordenes/{$nuevaOrdenId}/bosquejos/{$newFile}";
        }

        if ($rutaMiniatura && $rutaMiniatura !== $bosquejo->ruta_archivo && File::exists(public_path($rutaMiniatura))) {
            $ext = pathinfo($rutaMiniatura, PATHINFO_EXTENSION);
            $newThumb = uniqid('thumb_') . '.' . $ext;
            File::copy(public_path($rutaMiniatura), "{$destDir}/{$newThumb}");
            $rutaMiniatura = "uploads/ordenes/{$nuevaOrdenId}/bosquejos/{$newThumb}";
        } else {
            $rutaMiniatura = $rutaArchivo;
        }

        return [
            'ruta_archivo' => $rutaArchivo,
            'ruta_miniatura' => $rutaMiniatura,
        ];
    }

    /**
     * Obtiene la letra correspondiente al indice (A, B, C... Z, AA, AB...).
     */
    protected function obtenerLetraPieza(int $index): string
    {
        $letra = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letra = chr(65 + ($index % 26)) . $letra;
            $index = intdiv($index, 26);
        }
        return $letra;
    }
}
