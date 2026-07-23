<?php

namespace App\Http\Controllers;

use App\Exports\EntregasHistorialExport;
use App\Exports\EntregasPendientesExport;
use App\Models\Entrega;
use App\Models\EntregaPieza;
use App\Models\Notificacion;
use App\Models\Orden;
use App\Models\OrdenFoto;
use App\Models\OrdenPieza;
use App\Models\User;
use App\Services\OrdenEstadoService;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class EntregaController extends Controller
{
    use RegistraActividad;

    protected OrdenEstadoService $estadoService;

    public function __construct(OrdenEstadoService $estadoService)
    {
        $this->estadoService = $estadoService;
    }

    /**
     * GET /recepcion/entregas-pendientes - Listado de ordenes con piezas pendientes de entregar.
     */
    public function pendientes(Request $request)
    {
        if ($request->ajax()) {
            $query = Orden::whereHas('piezas', function ($q) {
                $q->whereColumn('cantidad_entregada', '<', 'cantidad');
            })
                ->whereNotIn('estado_trabajo', ['borrador', 'anulada'])
                ->with('cliente')
                ->select('ordenes.*');

            return DataTables::of($query)
                ->addColumn('cliente_nombre', function ($o) {
                    return $o->cliente->nombre ?? '-';
                })
                ->addColumn('porcentaje', function ($o) {
                    $totalUnidades = (int) $o->piezas->sum('cantidad');
                    $unidadesEntregadas = (int) $o->piezas->sum('cantidad_entregada');
                    $porcentaje = $totalUnidades > 0 ? round(($unidadesEntregadas / $totalUnidades) * 100) : 0;

                    if ($porcentaje >= 100) {
                        $color = 'success';
                    } elseif ($porcentaje >= 50) {
                        $color = 'info';
                    } elseif ($porcentaje > 0) {
                        $color = 'warning';
                    } else {
                        $color = 'danger';
                    }

                    return '<div class="d-flex align-items-center gap-2" style="min-width: 140px;">'
                        . '<div class="progress flex-grow-1" style="height: 8px;">'
                        . '<div class="progress-bar bg-' . $color . '" role="progressbar" style="width: ' . $porcentaje . '%" aria-valuenow="' . $porcentaje . '" aria-valuemin="0" aria-valuemax="100"></div>'
                        . '</div>'
                        . '<span class="fw-semibold text-' . $color . '" style="min-width: 40px;">' . $porcentaje . '%</span>'
                        . '</div>';
                })
                ->addColumn('estado_trabajo_badge', function ($o) {
                    return $this->badgeEstadoTrabajo($o->estado_trabajo);
                })
                ->addColumn('estado_entrega_badge', function ($o) {
                    return $this->badgeEstadoEntrega($o->estado_entrega);
                })
                ->addColumn('acciones', function ($o) {
                    $flujoUrl = route('recepcion.entregas.flujo', $o);
                    $html = '<div class="action-buttons justify-content-end">';
                    $html .= '<a href="' . $flujoUrl . '" class="action-btn view" title="Entregar" data-tooltip="Entregar"><i class="bi bi-box-arrow-right"></i></a>';
                    $html .= '<button type="button" class="action-btn btn-entrega-rapida" data-orden-id="' . $o->id . '" title="Entrega Rapida" data-tooltip="Entrega Rapida"><i class="bi bi-lightning"></i></button>';
                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('numero_orden', function ($o) {
                    return '<span class="fw-semibold">' . ($o->numero_orden ?? '-') . '</span>';
                })
                ->editColumn('fecha_entrega', function ($o) {
                    if (!$o->fecha_entrega) return '<span class="text-muted">-</span>';
                    $hoy = now()->startOfDay();
                    $fecha = $o->fecha_entrega;
                    $class = '';
                    if ($fecha->lt($hoy)) {
                        $class = ' text-danger fw-semibold';
                    } elseif ($fecha->eq($hoy)) {
                        $class = ' text-warning fw-semibold';
                    }
                    return '<span class="' . $class . '">' . $fecha->format('d/m/Y') . '</span>';
                })
                ->rawColumns(['numero_orden', 'porcentaje', 'estado_trabajo_badge', 'estado_entrega_badge', 'fecha_entrega', 'acciones'])
                ->make(true);
        }

        // Stats para cards
        $baseQuery = fn() => Orden::whereHas('piezas', function ($q) {
            $q->whereColumn('cantidad_entregada', '<', 'cantidad');
        })->whereNotIn('estado_trabajo', ['borrador', 'anulada']);

        $totalPendientes = $baseQuery()->count();

        $piezasPendientes = OrdenPieza::whereColumn('cantidad_entregada', '<', 'cantidad')
            ->whereHas('orden', function ($q) {
                $q->whereNotIn('estado_trabajo', ['borrador', 'anulada']);
            })
            ->count();

        $entregasHoy = Entrega::whereDate('created_at', today())->count();

        $entregasVencidas = $baseQuery()->whereDate('fecha_entrega', '<', today())->count();

        return view('entregas.pendientes', compact(
            'totalPendientes', 'piezasPendientes', 'entregasHoy', 'entregasVencidas'
        ));
    }

    /**
     * GET /recepcion/entregas-pendientes/export-excel - Exportar entregas pendientes a Excel.
     */
    public function exportPendientesExcel(Request $request)
    {
        $ordenes = Orden::whereHas('piezas', function ($q) {
            $q->whereColumn('cantidad_entregada', '<', 'cantidad');
        })
            ->whereNotIn('estado_trabajo', ['borrador', 'anulada'])
            ->with(['cliente', 'piezas'])
            ->orderBy('id', 'desc')
            ->get();

        return Excel::download(
            new EntregasPendientesExport($ordenes),
            'entregas-pendientes-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /recepcion/entregas-historial/export-excel - Exportar historial de entregas a Excel.
     */
    public function exportHistorialExcel(Request $request)
    {
        $entregasPiezas = EntregaPieza::with([
            'entrega.entregadaPorUsuario',
            'entrega.orden.cliente',
            'ordenPieza',
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        return Excel::download(
            new EntregasHistorialExport($entregasPiezas),
            'entregas-historial-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /recepcion/entregas-pendientes/{orden}/flujo - Wizard de entrega.
     */
    public function flujo(Orden $orden)
    {
        if (in_array($orden->estado_trabajo, ['borrador', 'anulada'])) {
            return redirect()->route('recepcion.entregas-pendientes')
                ->with('error', 'Esta orden no permite entregas.');
        }

        $piezasEntregables = $orden->piezas()
            ->whereColumn('cantidad_entregada', '<', 'cantidad')
            ->with('bosquejo')
            ->orderBy('orden_visual')
            ->get()
            ->map(function ($p) {
                $p->cantidad_pendiente = $p->cantidad - $p->cantidad_entregada;
                // URLs del bosquejo para mostrar miniatura y abrir la imagen completa en la entrega
                $p->bosquejo_miniatura = $p->bosquejo
                    ? asset($p->bosquejo->ruta_miniatura ?: $p->bosquejo->ruta_archivo)
                    : null;
                $p->bosquejo_imagen = $p->bosquejo
                    ? asset($p->bosquejo->ruta_archivo)
                    : null;
                return $p;
            });

        if ($piezasEntregables->isEmpty()) {
            return redirect()->route('recepcion.entregas-pendientes')
                ->with('info', 'No hay piezas pendientes para entregar en esta orden.');
        }

        $piezasEntregadas = $orden->piezas()
            ->where('cantidad_entregada', '>', 0)
            ->with('bosquejo')
            ->orderBy('orden_visual')
            ->get();

        $totalUnidades = $orden->piezas->sum('cantidad');
        $unidadesPendientes = $orden->piezas->sum(fn($p) => max(0, $p->cantidad - $p->cantidad_entregada));

        $orden->load('cliente');

        return view('entregas.flujo', compact('orden', 'piezasEntregables', 'piezasEntregadas', 'totalUnidades', 'unidadesPendientes'));
    }

    /**
     * POST /recepcion/entregas-pendientes/{orden}/entregar - Entrega parcial/total de piezas.
     */
    public function entregarPiezas(Request $request, Orden $orden)
    {
        $request->validate([
            'piezas' => 'required|array|min:1',
            'piezas.*.pieza_id' => 'required|integer',
            'piezas.*.cantidad' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $entregadas = [];

        DB::beginTransaction();
        try {
            // Crear evento de entrega
            $entrega = Entrega::create([
                'orden_id' => $orden->id,
                'entregada_por' => $user->id,
            ]);

            foreach ($request->input('piezas') as $item) {
                $pieza = OrdenPieza::where('id', $item['pieza_id'])
                    ->where('orden_id', $orden->id)
                    ->whereColumn('cantidad_entregada', '<', 'cantidad')
                    ->first();

                if (!$pieza) continue;

                $cantidadPendiente = $pieza->cantidad - $pieza->cantidad_entregada;
                $cantidadAEntregar = min($item['cantidad'], $cantidadPendiente);

                if ($cantidadAEntregar <= 0) continue;

                $cantidadEntregadaAntes = $pieza->cantidad_entregada;
                $entregadaAntes = (bool) $pieza->entregada;

                // Registrar detalle de entrega
                EntregaPieza::create([
                    'entrega_id' => $entrega->id,
                    'orden_pieza_id' => $pieza->id,
                    'cantidad' => $cantidadAEntregar,
                ]);

                // Actualizar pieza
                $nuevaCantidadEntregada = $pieza->cantidad_entregada + $cantidadAEntregar;
                $updateData = ['cantidad_entregada' => $nuevaCantidadEntregada];

                if ($nuevaCantidadEntregada >= $pieza->cantidad) {
                    $updateData['entregada'] = true;
                    $updateData['entregada_en'] = now();
                    $updateData['entregada_por'] = $user->id;
                    $updateData['estado'] = 'entregada';
                }

                $pieza->update($updateData);

                $entregadas[] = $pieza->nombre . " ({$cantidadAEntregar}/{$pieza->cantidad})";

                $this->registrarActividad(
                    'pieza.entregada',
                    "Entrega de {$cantidadAEntregar} unidad(es) de '{$pieza->nombre}' (Orden {$orden->numero_orden})",
                    $orden->id,
                    [
                        'tipo_cambio' => 'update',
                        'modelo' => 'OrdenPieza',
                        'modelo_id' => $pieza->id,
                        'cambios' => [
                            'cantidad_entregada' => [
                                'antes' => $cantidadEntregadaAntes,
                                'despues' => $pieza->cantidad_entregada,
                            ],
                            'entregada' => [
                                'antes' => $entregadaAntes,
                                'despues' => (bool) $pieza->entregada,
                            ],
                        ],
                        'pieza_nombre' => $pieza->nombre,
                        'entrega_id' => $entrega->id,
                    ]
                );

                if ($pieza->porcentaje_avance == 0) {
                    $this->notificarEntregaSinAvance($orden, $pieza);
                }
            }

            // Vincular fotos si fueron subidas previamente
            $fotoIds = $request->input('foto_ids', []);
            if (!empty($fotoIds)) {
                OrdenFoto::whereIn('id', $fotoIds)
                    ->where('orden_id', $orden->id)
                    ->whereNull('entrega_id')
                    ->update(['entrega_id' => $entrega->id]);
            }

            $orden->load('piezas');
            $this->estadoService->recalcularTodo($orden);

            DB::commit();

            $count = count($entregadas);
            return response()->json([
                'success' => true,
                'message' => $count . ' pieza(s) entregada(s) exitosamente.',
                'entrega_id' => $entrega->id,
                'piezas_entregadas' => $count,
                'estado_entrega' => $orden->estado_entrega,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la entrega: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/entregas-pendientes/{orden}/entrega-rapida - Entrega todo lo pendiente.
     */
    public function entregaRapida(Orden $orden)
    {
        if (in_array($orden->estado_trabajo, ['borrador', 'anulada'])) {
            return response()->json([
                'success' => false,
                'message' => 'Esta orden no permite entregas.',
            ], 422);
        }

        $piezasPendientes = $orden->piezas()
            ->whereColumn('cantidad_entregada', '<', 'cantidad')
            ->get();

        if ($piezasPendientes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay piezas pendientes para entrega rapida.',
            ], 422);
        }

        $user = auth()->user();
        $entregadas = [];

        DB::beginTransaction();
        try {
            $entrega = Entrega::create([
                'orden_id' => $orden->id,
                'entregada_por' => $user->id,
            ]);

            foreach ($piezasPendientes as $pieza) {
                $cantidadAEntregar = $pieza->cantidad - $pieza->cantidad_entregada;
                $cantidadEntregadaAntes = $pieza->cantidad_entregada;

                EntregaPieza::create([
                    'entrega_id' => $entrega->id,
                    'orden_pieza_id' => $pieza->id,
                    'cantidad' => $cantidadAEntregar,
                ]);

                $pieza->update([
                    'cantidad_entregada' => $pieza->cantidad,
                    'entregada' => true,
                    'entregada_en' => now(),
                    'entregada_por' => $user->id,
                    'estado' => 'entregada',
                ]);

                $entregadas[] = $pieza->nombre;

                $this->registrarActividad(
                    'pieza.entregada',
                    "Entrega rapida de {$cantidadAEntregar} unidad(es) de '{$pieza->nombre}' (Orden {$orden->numero_orden})",
                    $orden->id,
                    [
                        'tipo_cambio' => 'update',
                        'modelo' => 'OrdenPieza',
                        'modelo_id' => $pieza->id,
                        'cambios' => [
                            'cantidad_entregada' => [
                                'antes' => $cantidadEntregadaAntes,
                                'despues' => $pieza->cantidad,
                            ],
                            'entregada' => ['antes' => false, 'despues' => true],
                            'estado' => ['antes' => 'pendiente', 'despues' => 'entregada'],
                        ],
                        'pieza_nombre' => $pieza->nombre,
                        'entrega_id' => $entrega->id,
                    ]
                );

                if ($pieza->porcentaje_avance == 0) {
                    $this->notificarEntregaSinAvance($orden, $pieza);
                }
            }

            $orden->load('piezas');
            $this->estadoService->recalcularTodo($orden);

            DB::commit();

            $count = count($entregadas);
            return response()->json([
                'success' => true,
                'message' => $count . ' pieza(s) entregada(s) exitosamente.',
                'piezas_entregadas' => $count,
                'estado_entrega' => $orden->estado_entrega,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la entrega rapida: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/entregas-pendientes/{orden}/foto-entrega - Sube foto de entrega.
     */
    public function subirFotoEntrega(Request $request, Orden $orden)
    {
        $request->validate([
            'foto' => 'required|image|max:30720', // Max 30MB
        ], [
            'foto.required' => 'Debe seleccionar una foto.',
            'foto.image' => 'El archivo debe ser una imagen (JPG, PNG, etc.).',
            'foto.max' => 'La foto no puede pesar mas de 30 MB. Tamano maximo permitido: 30 MB.',
            'foto.uploaded' => 'La foto supera el tamano maximo permitido (30 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $directorio = public_path("uploads/ordenes/{$orden->id}/fotos");
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $archivo = $request->file('foto');
        $nombreArchivo = 'entrega_' . time() . '_' . rand(100, 999) . '.' . $archivo->getClientOriginalExtension();
        $archivo->move($directorio, $nombreArchivo);

        $rutaRelativa = "uploads/ordenes/{$orden->id}/fotos/{$nombreArchivo}";

        $foto = OrdenFoto::create([
            'orden_id' => $orden->id,
            'orden_pieza_id' => null,
            'entrega_id' => $request->input('entrega_id'),
            'tipo_foto' => 'entrega',
            'ruta_archivo' => $rutaRelativa,
            'ruta_miniatura' => null,
            'subido_por' => auth()->id(),
            'aprobada' => false,
        ]);

        $this->registrarCreacion(
            'entrega.foto_subida',
            "Foto de entrega subida para orden {$orden->numero_orden}",
            $foto,
            $orden->id
        );

        return response()->json([
            'success' => true,
            'foto' => [
                'id' => $foto->id,
                'url' => asset($rutaRelativa),
            ],
        ]);
    }

    /**
     * DELETE /recepcion/entregas-pendientes/{orden}/foto-entrega/{foto} - Elimina foto aun no vinculada a entrega.
     */
    public function eliminarFotoEntrega(Orden $orden, OrdenFoto $foto)
    {
        if ($foto->orden_id !== $orden->id || $foto->tipo_foto !== 'entrega' || $foto->entrega_id !== null) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar esta foto.'], 403);
        }

        if ($foto->subido_por !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $rutaAbsoluta = public_path($foto->ruta_archivo);
        if (is_file($rutaAbsoluta)) {
            @unlink($rutaAbsoluta);
        }

        $this->registrarEliminacion(
            'entrega.foto_eliminada',
            "Foto de entrega eliminada para orden {$orden->numero_orden}",
            $foto,
            $orden->id
        );

        $foto->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /recepcion/entregas-historial - Historial de entregas realizadas.
     */
    public function historial(Request $request)
    {
        if ($request->ajax()) {
            $query = EntregaPieza::with([
                'entrega.entregadaPorUsuario',
                'entrega.orden.cliente',
                'ordenPieza',
            ])->select('entrega_piezas.*');

            return DataTables::of($query)
                ->addColumn('fecha_entrega_formatted', function ($ep) {
                    return $ep->created_at ? $ep->created_at->format('d/m/Y H:i') : '-';
                })
                ->addColumn('numero_orden', function ($ep) {
                    $orden = $ep->entrega->orden ?? null;
                    if (!$orden) return '-';
                    $url = route('recepcion.ordenes.show', $orden->id);
                    return '<a href="' . $url . '" class="fw-semibold text-decoration-none">' . ($orden->numero_orden ?? '-') . '</a>';
                })
                ->addColumn('cliente_nombre', function ($ep) {
                    return $ep->entrega->orden->cliente->nombre ?? '-';
                })
                ->addColumn('pieza_nombre', function ($ep) {
                    return $ep->ordenPieza->nombre ?? '-';
                })
                ->addColumn('cantidad_entregada', function ($ep) {
                    $pieza = $ep->ordenPieza;
                    return '<span class="text-center d-block">' . $ep->cantidad . ' / ' . ($pieza->cantidad ?? '-') . '</span>';
                })
                ->addColumn('material', function ($ep) {
                    return $ep->ordenPieza->material ?? '-';
                })
                ->addColumn('calibre', function ($ep) {
                    return $ep->ordenPieza->calibre ?? '-';
                })
                ->addColumn('entregado_por_nombre', function ($ep) {
                    return $ep->entrega->entregadaPorUsuario->name ?? '-';
                })
                ->filterColumn('numero_orden', function ($query, $keyword) {
                    $query->whereHas('entrega.orden', function ($q) use ($keyword) {
                        $q->where('numero_orden', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('cliente_nombre', function ($query, $keyword) {
                    $query->whereHas('entrega.orden.cliente', function ($q) use ($keyword) {
                        $q->where('nombre', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('pieza_nombre', function ($query, $keyword) {
                    $query->whereHas('ordenPieza', function ($q) use ($keyword) {
                        $q->where('nombre', 'like', "%{$keyword}%");
                    });
                })
                ->orderColumn('fecha_entrega_formatted', function ($query, $order) {
                    $query->orderBy('created_at', $order);
                })
                ->rawColumns(['numero_orden', 'cantidad_entregada'])
                ->make(true);
        }

        $totalEntregadas = EntregaPieza::count();

        $entregadasHoy = EntregaPieza::whereDate('created_at', today())->count();

        $entregadasSemana = EntregaPieza::where('created_at', '>=', now()->subDays(7))->count();

        return view('entregas.historial', compact(
            'totalEntregadas', 'entregadasHoy', 'entregadasSemana'
        ));
    }

    /**
     * GET /recepcion/entregas-pendientes/pieza/{pieza}/historial - Historial de entregas de una pieza.
     */
    public function historialPieza($piezaId)
    {
        $pieza = OrdenPieza::findOrFail($piezaId);

        $entregas = EntregaPieza::where('orden_pieza_id', $pieza->id)
            ->with(['entrega.entregadaPorUsuario', 'entrega.fotos'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ep) {
                $fotos = $ep->entrega->fotos->map(function ($f) {
                    return [
                        'id' => $f->id,
                        'url' => asset($f->ruta_archivo),
                    ];
                });

                return [
                    'id' => $ep->id,
                    'fecha' => $ep->created_at ? $ep->created_at->format('d/m/Y H:i') : '-',
                    'cantidad' => $ep->cantidad,
                    'entregado_por' => $ep->entrega->entregadaPorUsuario->name ?? '-',
                    'fotos' => $fotos,
                ];
            });

        return response()->json([
            'pieza' => [
                'id' => $pieza->id,
                'nombre' => $pieza->nombre,
                'cantidad' => $pieza->cantidad,
                'cantidad_entregada' => $pieza->cantidad_entregada,
            ],
            'entregas' => $entregas,
        ]);
    }

    // ---- Notificaciones ----

    protected function notificarEntregaSinAvance(Orden $orden, OrdenPieza $pieza): void
    {
        $usuarios = User::role(['Administrador', 'Contabilidad'])->activos()->get();

        foreach ($usuarios as $usuario) {
            Notificacion::create([
                'usuario_id' => $usuario->id,
                'tipo' => 'entrega_sin_avance',
                'titulo' => 'Entrega sin avance de trabajo',
                'contenido' => "Se entrego '{$pieza->nombre}' de la Orden #{$orden->numero_orden} con 0% de avance de trabajo",
                'url' => "/recepcion/ordenes/{$orden->id}",
                'leida' => false,
            ]);
        }
    }

    // ---- Badge helpers ----

    protected function badgeEstadoTrabajo(string $estado): string
    {
        $map = [
            'borrador' => ['secondary', 'BORRADOR'],
            'generada' => ['info', 'GENERADA'],
            'en_ejecucion' => ['warning', 'EN EJECUCION'],
            'ejecutada_parcialmente' => ['warning', 'EJEC. PARCIAL'],
            'ejecutada' => ['success', 'EJECUTADA'],
            'anulada' => ['danger', 'ANULADA'],
        ];
        $cfg = $map[$estado] ?? ['secondary', strtoupper($estado)];
        return '<span class="status-badge ' . $cfg[0] . '">' . $cfg[1] . '</span>';
    }

    protected function badgeEstadoEntrega(?string $estado): string
    {
        if (!$estado) return '<span class="text-muted">-</span>';
        $map = [
            'entregada_parcialmente' => ['info', 'ENTREGA PARCIAL'],
            'entregada' => ['success', 'ENTREGADA'],
        ];
        $cfg = $map[$estado] ?? ['secondary', strtoupper($estado)];
        return '<span class="status-badge ' . $cfg[0] . '">' . $cfg[1] . '</span>';
    }
}
