<?php

namespace App\Services;

use App\Models\AsignacionPieza;
use App\Models\HistorialAvance;
use App\Models\Notificacion;
use App\Models\Orden;
use App\Services\NotificacionService;
use App\Models\OrdenFoto;
use App\Models\OrdenPieza;
use App\Models\OrdenPiezaObservacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OperarioPiezaService
{
    protected OrdenEstadoService $estadoService;

    public function __construct(OrdenEstadoService $estadoService)
    {
        $this->estadoService = $estadoService;
    }

    /**
     * Actualiza el avance de una pieza (batch: multiples piezas de una orden).
     * Retorna resumen de lo que paso.
     */
    public function actualizarAvances(Orden $orden, array $cambios, User $operario): array
    {
        $resultado = [
            'success' => true,
            'piezas_actualizadas' => 0,
            'piezas_terminadas' => [],
            'avances_disminuidos' => [],
            'orden_ejecutada' => false,
        ];

        DB::beginTransaction();
        try {
            foreach ($cambios as $cambio) {
                $pieza = OrdenPieza::where('id', $cambio['pieza_id'])
                    ->where('orden_id', $orden->id)
                    ->where('operario_actual_id', $operario->id)
                    ->first();

                if (!$pieza) {
                    continue;
                }

                $porcentajeAnterior = (float) $pieza->porcentaje_avance;
                $nuevoPorcentaje = max(0, min(100, (float) $cambio['porcentaje']));

                // Si no cambio, saltar
                if (abs($porcentajeAnterior - $nuevoPorcentaje) < 0.01) {
                    continue;
                }

                // Cerrar historial abierto actual
                $historialAbierto = HistorialAvance::where('orden_pieza_id', $pieza->id)
                    ->where('operario_id', $operario->id)
                    ->whereNull('completado_en')
                    ->latest()
                    ->first();

                if ($historialAbierto) {
                    $historialAbierto->update([
                        'porcentaje_hasta' => $nuevoPorcentaje,
                        'contribucion' => $nuevoPorcentaje - (float) $historialAbierto->porcentaje_desde,
                        'completado_en' => now(),
                    ]);
                } else {
                    // Crear historial completo si no habia uno abierto
                    HistorialAvance::create([
                        'orden_pieza_id' => $pieza->id,
                        'operario_id' => $operario->id,
                        'porcentaje_desde' => $porcentajeAnterior,
                        'porcentaje_hasta' => $nuevoPorcentaje,
                        'contribucion' => $nuevoPorcentaje - $porcentajeAnterior,
                        'asignado_en' => now(),
                        'completado_en' => now(),
                    ]);
                }

                // Crear nuevo historial abierto si no llego al 100%
                if ($nuevoPorcentaje < 100) {
                    HistorialAvance::create([
                        'orden_pieza_id' => $pieza->id,
                        'operario_id' => $operario->id,
                        'porcentaje_desde' => $nuevoPorcentaje,
                        'porcentaje_hasta' => $nuevoPorcentaje,
                        'contribucion' => 0,
                        'asignado_en' => now(),
                        'completado_en' => null,
                    ]);
                }

                // Actualizar pieza
                $pieza->porcentaje_avance = $nuevoPorcentaje;
                $pieza->estado = $nuevoPorcentaje >= 100 ? 'completada' : ($nuevoPorcentaje > 0 ? 'en_proceso' : 'pendiente');
                $pieza->save();

                $resultado['piezas_actualizadas']++;

                // Pieza terminada
                if ($nuevoPorcentaje >= 100) {
                    $resultado['piezas_terminadas'][] = $pieza->nombre;
                    NotificacionService::piezaCompletada($pieza, $operario);
                }

                // Avance disminuido
                if ($nuevoPorcentaje < $porcentajeAnterior) {
                    $resultado['avances_disminuidos'][] = [
                        'pieza' => $pieza->nombre,
                        'desde' => $porcentajeAnterior,
                        'hasta' => $nuevoPorcentaje,
                    ];

                    $this->notificarAvanceDisminuido($pieza, $operario, $porcentajeAnterior, $nuevoPorcentaje);
                }
            }

            // Refrescar piezas y recalcular estados de la orden
            $orden->load('piezas');
            $this->estadoService->recalcularTodo($orden);

            $resultado['orden_ejecutada'] = $orden->estado_trabajo === 'ejecutada';

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $resultado['success'] = false;
            $resultado['error'] = $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Transfiere una pieza a otro operario.
     */
    public function transferirPieza(OrdenPieza $pieza, int $nuevoOperarioId, User $operarioActual, ?string $notas = null): array
    {
        if ((int) $pieza->operario_actual_id !== $operarioActual->id) {
            return ['success' => false, 'error' => 'No tienes esta pieza asignada.'];
        }

        $nuevoOperario = User::find($nuevoOperarioId);
        if (!$nuevoOperario || !$nuevoOperario->isOperario()) {
            return ['success' => false, 'error' => 'Operario destino no valido.'];
        }

        DB::beginTransaction();
        try {
            // Cerrar asignacion activa actual
            AsignacionPieza::where('orden_pieza_id', $pieza->id)
                ->where('asignado_a_id', $operarioActual->id)
                ->where('activa', true)
                ->update(['activa' => false]);

            // Cerrar historial abierto
            HistorialAvance::where('orden_pieza_id', $pieza->id)
                ->where('operario_id', $operarioActual->id)
                ->whereNull('completado_en')
                ->update([
                    'porcentaje_hasta' => $pieza->porcentaje_avance,
                    'contribucion' => DB::raw("({$pieza->porcentaje_avance} - porcentaje_desde)"),
                    'completado_en' => now(),
                ]);

            // Crear nueva asignacion
            AsignacionPieza::create([
                'orden_pieza_id' => $pieza->id,
                'orden_id' => $pieza->orden_id,
                'asignado_desde_id' => $operarioActual->id,
                'asignado_a_id' => $nuevoOperarioId,
                'asignado_por_id' => $operarioActual->id,
                'tipo_asignacion' => 'transferencia',
                'porcentaje_al_asignar' => $pieza->porcentaje_avance,
                'notas' => $notas,
                'activa' => true,
            ]);

            // Crear historial abierto para nuevo operario
            HistorialAvance::create([
                'orden_pieza_id' => $pieza->id,
                'operario_id' => $nuevoOperarioId,
                'porcentaje_desde' => $pieza->porcentaje_avance,
                'porcentaje_hasta' => $pieza->porcentaje_avance,
                'contribucion' => 0,
                'asignado_en' => now(),
                'completado_en' => null,
            ]);

            // Actualizar pieza
            $pieza->update(['operario_actual_id' => $nuevoOperarioId]);

            // Si la transferencia trae notas, registrarlas tambien como observacion
            // de la pieza para que sean visibles en "Ver observaciones" del operario destino.
            $notasLimpias = trim((string) $notas);
            if ($notasLimpias !== '') {
                OrdenPiezaObservacion::create([
                    'orden_id' => $pieza->orden_id,
                    'orden_pieza_id' => $pieza->id,
                    'user_id' => $operarioActual->id,
                    'observacion' => "Transferencia a {$nuevoOperario->name}: {$notasLimpias}",
                ]);
            }

            DB::commit();

            return ['success' => true, 'nuevo_operario' => $nuevoOperario->name];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deja la pieza en cola general (sin operario).
     */
    public function dejarEnCola(OrdenPieza $pieza, User $operario): array
    {
        if ((int) $pieza->operario_actual_id !== $operario->id) {
            return ['success' => false, 'error' => 'No tienes esta pieza asignada.'];
        }

        DB::beginTransaction();
        try {
            // Cerrar asignacion activa
            AsignacionPieza::where('orden_pieza_id', $pieza->id)
                ->where('asignado_a_id', $operario->id)
                ->where('activa', true)
                ->update(['activa' => false]);

            // Cerrar historial abierto
            HistorialAvance::where('orden_pieza_id', $pieza->id)
                ->where('operario_id', $operario->id)
                ->whereNull('completado_en')
                ->update([
                    'porcentaje_hasta' => $pieza->porcentaje_avance,
                    'contribucion' => DB::raw("({$pieza->porcentaje_avance} - porcentaje_desde)"),
                    'completado_en' => now(),
                ]);

            // Liberar pieza
            $pieza->update(['operario_actual_id' => null]);

            DB::commit();

            return ['success' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Operario toma una pieza de la cola general.
     * Usa lockForUpdate para prevenir race conditions.
     */
    public function tomarPieza(OrdenPieza $pieza, User $operario): array
    {
        return DB::transaction(function () use ($pieza, $operario) {
            // Lock the row to prevent two operarios taking same piece
            $piezaLocked = OrdenPieza::where('id', $pieza->id)
                ->whereNull('operario_actual_id')
                ->where('porcentaje_avance', '<', 100)
                ->lockForUpdate()
                ->first();

            if (!$piezaLocked) {
                return ['success' => false, 'error' => 'La pieza ya fue tomada por otro operario.'];
            }

            // Crear asignacion
            AsignacionPieza::create([
                'orden_pieza_id' => $piezaLocked->id,
                'orden_id' => $piezaLocked->orden_id,
                'asignado_desde_id' => null,
                'asignado_a_id' => $operario->id,
                'asignado_por_id' => $operario->id,
                'tipo_asignacion' => 'complemento',
                'porcentaje_al_asignar' => $piezaLocked->porcentaje_avance,
                'activa' => true,
            ]);

            // Crear historial abierto
            HistorialAvance::create([
                'orden_pieza_id' => $piezaLocked->id,
                'operario_id' => $operario->id,
                'porcentaje_desde' => $piezaLocked->porcentaje_avance,
                'porcentaje_hasta' => $piezaLocked->porcentaje_avance,
                'contribucion' => 0,
                'asignado_en' => now(),
                'completado_en' => null,
            ]);

            // Asignar pieza
            $piezaLocked->update(['operario_actual_id' => $operario->id]);

            return ['success' => true, 'pieza' => $piezaLocked->nombre, 'orden_id' => $piezaLocked->orden_id];
        });
    }

    /**
     * Sube una foto de avance para una pieza.
     */
    public function subirFoto(OrdenPieza $pieza, $archivo, User $operario): OrdenFoto
    {
        $ordenId = $pieza->orden_id;
        $directorio = public_path("uploads/ordenes/{$ordenId}/fotos");

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $nombreArchivo = 'foto_' . $pieza->id . '_' . time() . '.' . $archivo->getClientOriginalExtension();
        $archivo->move($directorio, $nombreArchivo);

        return OrdenFoto::create([
            'orden_id' => $ordenId,
            'orden_pieza_id' => $pieza->id,
            'tipo_foto' => 'avance',
            'ruta_archivo' => "uploads/ordenes/{$ordenId}/fotos/{$nombreArchivo}",
            'ruta_miniatura' => null,
            'subido_por' => $operario->id,
            'aprobada' => false,
        ]);
    }

    /**
     * Obtiene estadisticas para el dashboard del operario.
     */
    public function getStatsOperario(User $operario): array
    {
        // Solo piezas pendientes (<100%) en ordenes activas (no anuladas/borradores).
        // Debe coincidir con el filtro del listado de "Mis Ordenes Asignadas".
        $piezasAsignadas = OrdenPieza::where('operario_actual_id', $operario->id)
            ->where('porcentaje_avance', '<', 100)
            ->whereHas('orden', function ($q) {
                $q->noAnuladas()->noBorradores();
            })
            ->get();

        $ordenesIds = $piezasAsignadas->pluck('orden_id')->unique();

        $piezasEnProceso = $piezasAsignadas->filter(function ($p) {
            return $p->porcentaje_avance > 0 && $p->porcentaje_avance < 100;
        })->count();

        $paraComplementar = OrdenPieza::whereNull('operario_actual_id')
            ->where('porcentaje_avance', '<', 100)
            ->whereHas('orden', function ($q) {
                $q->noAnuladas()->noBorradores()
                    ->where(function ($q2) {
                        $q2->whereNull('estado_entrega')
                            ->orWhere('estado_entrega', '!=', 'entregada');
                    });
            })
            ->count();

        $completadasHoy = HistorialAvance::where('operario_id', $operario->id)
            ->whereDate('completado_en', today())
            ->where('porcentaje_hasta', '>=', 100)
            ->count();

        return [
            'ordenes_asignadas' => $ordenesIds->count(),
            'piezas_en_proceso' => $piezasEnProceso,
            'para_complementar' => $paraComplementar,
            'completadas_hoy' => $completadasHoy,
        ];
    }

    /**
     * Notifica al usuario configurado cuando un avance disminuye.
     */
    protected function notificarAvanceDisminuido(OrdenPieza $pieza, User $operario, float $desde, float $hasta): void
    {
        $usuarios = User::role(['Administrador', 'Contabilidad'])->activos()->get();

        foreach ($usuarios as $usuario) {
            Notificacion::create([
                'usuario_id' => $usuario->id,
                'tipo' => 'avance_disminuido',
                'titulo' => 'Avance disminuido',
                'contenido' => "{$operario->name} bajo el avance de '{$pieza->nombre}' (Orden #{$pieza->orden->numero_orden}) de {$desde}% a {$hasta}%",
                'url' => "/recepcion/ordenes/{$pieza->orden_id}",
                'leida' => false,
            ]);
        }
    }
}
