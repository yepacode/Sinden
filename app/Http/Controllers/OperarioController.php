<?php

namespace App\Http\Controllers;

use App\Exports\OperarioComplementarExport;
use App\Exports\OperarioOrdenesAsignadasExport;
use App\Models\ConfiguracionSistema;
use App\Models\Orden;
use App\Models\OrdenPieza;
use App\Models\OrdenPiezaObservacion;
use App\Models\User;
use App\Services\BloqueoService;
use App\Services\DashboardService;
use App\Services\OperarioPiezaService;
use App\Services\OrdenEstadoService;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class OperarioController extends Controller
{
    use RegistraActividad;

    protected OperarioPiezaService $piezaService;
    protected BloqueoService $bloqueoService;
    protected OrdenEstadoService $estadoService;
    protected DashboardService $dashboardService;

    public function __construct(
        OperarioPiezaService $piezaService,
        BloqueoService $bloqueoService,
        OrdenEstadoService $estadoService,
        DashboardService $dashboardService
    ) {
        $this->piezaService = $piezaService;
        $this->bloqueoService = $bloqueoService;
        $this->estadoService = $estadoService;
        $this->dashboardService = $dashboardService;
    }

    // ==========================================
    // PAGINAS (retornan vistas)
    // ==========================================

    /**
     * GET /operario/panel - Dashboard del operario.
     */
    public function panel()
    {
        $user = auth()->user();
        $stats = $this->piezaService->getStatsOperario($user);
        $stats['garantias_pendientes'] = $this->dashboardService->getGarantiasOperario($user);

        return view('operario.panel', compact('stats'));
    }

    /**
     * GET /operario/ordenes-asignadas - Listado de ordenes asignadas.
     */
    public function ordenesAsignadas(Request $request)
    {
        $user = auth()->user();

        if ($request->ajax()) {
            // Solo ordenes con piezas asignadas al operario que aun esten pendientes (<100%).
            // Las piezas terminadas o liberadas a la cola no cuentan.
            $query = Orden::whereHas('piezas', function ($q) use ($user) {
                $q->where('operario_actual_id', $user->id)
                  ->where('porcentaje_avance', '<', 100);
            })
            ->with(['cliente'])
            ->noAnuladas()
            ->noBorradores()
            ->select('ordenes.*');

            return DataTables::eloquent($query)
                ->addColumn('cliente_nombre', function ($orden) {
                    return $orden->cliente->nombre ?? 'Sin cliente';
                })
                ->addColumn('fecha_creacion_fmt', function ($orden) {
                    return $orden->created_at ? $orden->created_at->format('d/m/Y') : '-';
                })
                ->addColumn('fecha_entrega_fmt', function ($orden) {
                    return $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : '-';
                })
                ->addColumn('hora_entrega_fmt', function ($orden) {
                    return $orden->hora_entrega_fmt
                        ? '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>' . e($orden->hora_entrega_fmt) . '</span>'
                        : '<span class="text-muted">-</span>';
                })
                ->addColumn('mis_piezas', function ($orden) use ($user) {
                    $total = $orden->piezas->count();
                    $mias = $orden->piezas
                        ->where('operario_actual_id', $user->id)
                        ->where('porcentaje_avance', '<', 100)
                        ->count();
                    return "{$mias} de {$total}";
                })
                ->addColumn('estado', function ($orden) {
                    return $this->badgeEstadoTrabajo($orden->estado_trabajo);
                })
                ->addColumn('acciones', function ($orden) {
                    $url = route('operario.ordenes.trabajar', $orden);
                    return '<div class="action-buttons justify-content-end">'
                        . '<a href="' . $url . '" class="action-btn view" title="Trabajar" data-tooltip="Trabajar"><i class="bi bi-tools"></i></a>'
                        . '</div>';
                })
                ->rawColumns(['estado', 'acciones', 'hora_entrega_fmt'])
                ->make(true);
        }

        return view('operario.ordenes-asignadas');
    }

    /**
     * GET /operario/ordenes-asignadas/export-excel - Exportar ordenes asignadas al operario.
     */
    public function exportOrdenesAsignadasExcel(Request $request)
    {
        $user = auth()->user();

        $ordenes = Orden::whereHas('piezas', function ($q) use ($user) {
            $q->where('operario_actual_id', $user->id)
              ->where('porcentaje_avance', '<', 100);
        })
            ->with(['cliente', 'piezas'])
            ->noAnuladas()
            ->noBorradores()
            ->orderBy('id', 'desc')
            ->get();

        return Excel::download(
            new OperarioOrdenesAsignadasExport($ordenes, $user->id),
            'ordenes-asignadas-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /operario/complementar/export-excel - Exportar piezas disponibles para complementar.
     */
    public function exportComplementarExcel(Request $request)
    {
        $piezas = OrdenPieza::whereNull('operario_actual_id')
            ->where('porcentaje_avance', '<', 100)
            ->where('estado', '!=', 'completada')
            ->whereHas('orden', function ($q) {
                $q->noAnuladas()->noBorradores()
                    ->where(function ($q2) {
                        $q2->whereNull('estado_entrega')
                            ->orWhere('estado_entrega', '!=', 'entregada');
                    });
            })
            ->with(['orden.cliente'])
            ->orderBy('id', 'desc')
            ->get();

        return Excel::download(
            new OperarioComplementarExport($piezas),
            'complementar-piezas-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /operario/ordenes/{orden} - Vista de trabajo.
     */
    public function trabajar(Orden $orden)
    {
        $user = auth()->user();

        // Verificar que la orden no sea borrador ni anulada
        if (in_array($orden->estado_trabajo, ['borrador', 'anulada'])) {
            return redirect()->route('operario.ordenes-asignadas')
                ->with('error', 'Esta orden no esta disponible para trabajar.');
        }

        // Cargar piezas asignadas al operario actual que aun no esten al 100%.
        // Las piezas terminadas se ocultan para que el operario no las vea
        // al recargar la vista despues de "Actualizar Orden".
        $piezas = $orden->piezas()
            ->where('operario_actual_id', $user->id)
            ->where('porcentaje_avance', '<', 100)
            ->with(['bosquejo', 'historialAvances.operario', 'fotos', 'asignaciones.asignadoDesde', 'observaciones.usuario'])
            ->orderBy('orden_visual')
            ->get();

        if ($piezas->isEmpty()) {
            return redirect()->route('operario.ordenes-asignadas')
                ->with('error', 'No tienes piezas pendientes en esta orden.');
        }

        // Intentar adquirir bloqueo
        $lockResult = $this->bloqueoService->bloquear($orden, $user);

        // Cargar datos de la orden + documentos adjuntos (subidos por recepcion)
        $orden->load(['cliente', 'documentos.subidoPorUsuario']);

        // Obtener operarios para dropdown de transferencia
        $operarios = User::role('Operario')
            ->activos()
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $timeoutInactividad = ConfiguracionSistema::get('timeout_inactividad_operario', 10);

        // Colores para barra de progreso multi-operario
        $coloresOperarios = [
            '#4A7C59', '#2196F3', '#FF9800', '#9C27B0', '#E91E63',
            '#00BCD4', '#795548', '#607D8B', '#FF5722', '#3F51B5',
        ];

        // Info de piezas para determinar si la orden se completa.
        // Cuenta TODAS las piezas al 100% (las propias ya filtradas y las de otros)
        // ya que ninguna pieza al 100% se muestra ahora en la vista.
        $totalPiezasOrden = $orden->piezas()->count();
        $piezasOtros100 = $orden->piezas()
            ->where('porcentaje_avance', '>=', 100)
            ->count();

        return view('operario.trabajar', compact(
            'orden', 'piezas', 'operarios', 'lockResult',
            'timeoutInactividad', 'coloresOperarios',
            'totalPiezasOrden', 'piezasOtros100'
        ));
    }

    /**
     * GET /operario/buscar - Vista de busqueda.
     */
    public function buscar()
    {
        return view('operario.buscar');
    }

    /**
     * GET /operario/buscar-orden - AJAX busqueda por numero.
     */
    public function buscarOrden(Request $request)
    {
        $numero = $request->input('q', '');

        if (strlen($numero) < 1) {
            return response()->json(['success' => false, 'error' => 'Ingresa un numero de orden.']);
        }

        // Agregar # si no lo tiene
        if (!str_starts_with($numero, '#')) {
            $numero = '#' . $numero;
        }

        $orden = Orden::where('numero_orden', $numero)
            ->noAnuladas()
            ->noBorradores()
            ->with(['cliente', 'piezas.operarioActual', 'piezas.historialAvances.operario'])
            ->first();

        if (!$orden) {
            return response()->json(['success' => false, 'error' => 'Orden no encontrada.']);
        }

        return response()->json([
            'success' => true,
            'orden' => [
                'id' => $orden->id,
                'numero_orden' => $orden->numero_orden,
                'cliente' => $orden->cliente->nombre ?? 'Sin cliente',
                'estado_trabajo' => $orden->estado_trabajo,
                'estado_entrega' => $orden->estado_entrega,
                'fecha_entrega' => $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : null,
                'total' => $orden->total,
                'notas' => $orden->notas,
                'piezas' => $orden->piezas->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'nombre' => $p->nombre,
                        'cantidad' => $p->cantidad,
                        'especificacion' => $p->especificacion,
                        'material' => $p->material,
                        'calibre' => $p->calibre,
                        'porcentaje_avance' => (float) $p->porcentaje_avance,
                        'estado' => $p->estado,
                        'operario' => $p->operarioActual ? $p->operarioActual->name : 'Sin operario',
                        'entregada' => $p->entregada,
                        'historial' => $p->historialAvances->map(function ($h) {
                            return [
                                'operario' => $h->operario->name ?? 'Desconocido',
                                'desde' => (float) $h->porcentaje_desde,
                                'hasta' => (float) $h->porcentaje_hasta,
                                'contribucion' => (float) $h->contribucion,
                                'fecha' => $h->created_at->format('d/m/Y H:i'),
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }

    /**
     * GET /operario/complementar - Vista de piezas disponibles.
     */
    public function complementar(Request $request)
    {
        if ($request->ajax()) {
            $query = OrdenPieza::whereNull('operario_actual_id')
                ->where('porcentaje_avance', '<', 100)
                ->where('estado', '!=', 'completada')
                ->whereHas('orden', function ($q) {
                    $q->noAnuladas()->noBorradores()
                        ->where(function ($q2) {
                            $q2->whereNull('estado_entrega')
                                ->orWhere('estado_entrega', '!=', 'entregada');
                        });
                })
                ->with(['orden.cliente', 'bosquejo', 'observaciones' => function ($q) {
                    $q->latest();
                }])
                ->select('orden_piezas.*');

            return DataTables::eloquent($query)
                ->addColumn('orden_numero', function ($pieza) {
                    return $pieza->orden->numero_orden ?? '-';
                })
                ->addColumn('bosquejo', function ($pieza) {
                    if ($pieza->bosquejo) {
                        $mini = asset($pieza->bosquejo->ruta_miniatura ?: $pieza->bosquejo->ruta_archivo);
                        $full = asset($pieza->bosquejo->ruta_archivo);
                        return '<img src="' . $mini . '" class="bosquejo-entrega-thumb" alt="Bosquejo" '
                            . 'title="Click para ver el bosquejo" '
                            . 'onclick="verBosquejoPieza(\'' . $full . '\', \'' . e($pieza->nombre) . '\')">';
                    }
                    return '<span class="text-muted small" title="Sin bosquejo"><i class="bi bi-image"></i></span>';
                })
                ->addColumn('ultimo_comentario', function ($pieza) {
                    $obs = $pieza->observaciones->first();
                    if ($obs) {
                        return '<span class="d-inline-block text-wrap" style="max-width:220px;"><i class="bi bi-chat-left-text me-1 text-info"></i>'
                            . e($obs->observacion) . '</span>';
                    }
                    return '<span class="text-muted small">—</span>';
                })
                ->addColumn('pieza_info', function ($pieza) {
                    $info = '<span class="fw-semibold">' . e($pieza->nombre) . '</span>';
                    if ($pieza->especificacion) {
                        $info .= '<br><small class="text-muted">' . e($pieza->especificacion) . '</small>';
                    }
                    return $info;
                })
                ->addColumn('progreso', function ($pieza) {
                    $pct = (float) $pieza->porcentaje_avance;
                    $color = $pct >= 50 ? 'warning' : ($pct > 0 ? 'info' : 'secondary');
                    return '<div class="d-flex align-items-center gap-2">'
                        . '<div class="progress flex-grow-1" style="height:8px;">'
                        . '<div class="progress-bar bg-' . $color . '" style="width:' . $pct . '%"></div>'
                        . '</div>'
                        . '<small class="text-muted fw-semibold">' . intval($pct) . '%</small>'
                        . '</div>';
                })
                ->addColumn('ultimo_operario', function ($pieza) {
                    $ultimaAsignacion = $pieza->asignaciones()
                        ->where('activa', false)
                        ->latest()
                        ->with('asignadoA')
                        ->first();
                    return $ultimaAsignacion ? $ultimaAsignacion->asignadoA->name : '-';
                })
                ->addColumn('cliente_nombre', function ($pieza) {
                    return $pieza->orden->cliente->nombre ?? '-';
                })
                ->addColumn('fecha_entrega', function ($pieza) {
                    return $pieza->orden->fecha_entrega
                        ? $pieza->orden->fecha_entrega->format('d/m/Y')
                        : '-';
                })
                ->addColumn('acciones', function ($pieza) {
                    return '<button class="btn btn-sm btn-primary btn-tomar-pieza" '
                        . 'data-pieza-id="' . $pieza->id . '" '
                        . 'data-pieza-nombre="' . e($pieza->nombre) . '">'
                        . '<i class="bi bi-hand-index me-1"></i>Tomar'
                        . '</button>';
                })
                ->rawColumns(['bosquejo', 'ultimo_comentario', 'pieza_info', 'progreso', 'acciones'])
                ->make(true);
        }

        return view('operario.complementar');
    }

    // ==========================================
    // AJAX: Trabajo con piezas
    // ==========================================

    /**
     * POST /operario/ordenes/{orden}/actualizar-avances - Batch update.
     */
    public function actualizarAvances(Request $request, Orden $orden)
    {
        $request->validate([
            'cambios' => 'required|array|min:1',
            'cambios.*.pieza_id' => 'required|integer',
            'cambios.*.porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        $user = auth()->user();
        $resultado = $this->piezaService->actualizarAvances($orden, $request->input('cambios'), $user);

        if ($resultado['success']) {
            // Registrar actividad
            $desc = "Avances actualizados: {$resultado['piezas_actualizadas']} pieza(s)";
            if (!empty($resultado['piezas_terminadas'])) {
                $desc .= '. Terminadas: ' . implode(', ', $resultado['piezas_terminadas']);
            }
            $cambiosAvance = [];
            foreach ($request->input('cambios', []) as $c) {
                $cambiosAvance['pieza_' . ($c['pieza_id'] ?? '?')] = [
                    'antes' => null,
                    'despues' => ($c['porcentaje'] ?? null) . '%',
                ];
            }
            $this->registrarActividad('pieza.avance_actualizado', $desc, $orden->id, [
                'tipo_cambio' => 'update',
                'modelo' => 'OrdenPieza',
                'modelo_id' => null,
                'cambios' => $cambiosAvance,
                'piezas_actualizadas' => $resultado['piezas_actualizadas'],
                'piezas_terminadas' => $resultado['piezas_terminadas'],
            ]);

            // Registrar avances disminuidos
            foreach ($resultado['avances_disminuidos'] as $disminuido) {
                $this->registrarActividad('pieza.avance_disminuido',
                    "Avance disminuido en '{$disminuido['pieza']}': {$disminuido['desde']}% -> {$disminuido['hasta']}%",
                    $orden->id,
                    [
                        'tipo_cambio' => 'update',
                        'modelo' => 'OrdenPieza',
                        'modelo_id' => $disminuido['pieza_id'] ?? null,
                        'cambios' => [
                            'porcentaje_avance' => [
                                'antes' => $disminuido['desde'] . '%',
                                'despues' => $disminuido['hasta'] . '%',
                            ],
                        ],
                        'pieza' => $disminuido['pieza'] ?? null,
                    ]
                );
            }
        }

        return response()->json($resultado);
    }

    /**
     * POST /operario/piezas/{pieza}/transferir
     */
    public function transferirPieza(Request $request, OrdenPieza $pieza)
    {
        $request->validate([
            'nuevo_operario_id' => 'required|integer|exists:users,id',
            'notas' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $operarioAnteriorId = $pieza->operario_actual_id;
        $resultado = $this->piezaService->transferirPieza(
            $pieza,
            $request->input('nuevo_operario_id'),
            $user,
            $request->input('notas')
        );

        if ($resultado['success']) {
            $this->registrarActividad('pieza.transferida',
                "Pieza '{$pieza->nombre}' transferida a {$resultado['nuevo_operario']}",
                $pieza->orden_id,
                [
                    'tipo_cambio' => 'update',
                    'modelo' => 'OrdenPieza',
                    'modelo_id' => $pieza->id,
                    'cambios' => [
                        'operario_actual_id' => [
                            'antes' => $operarioAnteriorId,
                            'despues' => (int) $request->input('nuevo_operario_id'),
                        ],
                    ],
                    'notas_transferencia' => $request->input('notas'),
                ]
            );
        }

        return response()->json($resultado);
    }

    /**
     * POST /operario/ordenes/{orden}/transferir-masivo
     * Transfiere de golpe todas las piezas del operario en la orden a un mismo operario.
     */
    public function transferirMasivo(Request $request, Orden $orden)
    {
        $request->validate([
            'nuevo_operario_id' => 'required|integer|exists:users,id',
            'notas' => 'nullable|string|max:500',
        ]);

        $user = auth()->user();
        $resultado = $this->piezaService->transferirPiezasMasivo(
            $orden,
            (int) $request->input('nuevo_operario_id'),
            $user,
            $request->input('notas')
        );

        if ($resultado['success']) {
            $this->registrarActividad('pieza.transferida',
                "Transferencia masiva de {$resultado['cantidad']} pieza(s) a {$resultado['nuevo_operario']}",
                $orden->id,
                [
                    'tipo_cambio' => 'update',
                    'modelo' => 'OrdenPieza',
                    'modelo_id' => null,
                    'cantidad_piezas' => $resultado['cantidad'],
                    'nuevo_operario_id' => (int) $request->input('nuevo_operario_id'),
                    'notas_transferencia' => $request->input('notas'),
                ]
            );
        }

        return response()->json($resultado);
    }

    /**
     * POST /operario/piezas/{pieza}/dejar-cola
     */
    public function dejarEnCola(Request $request, OrdenPieza $pieza)
    {
        $validated = $request->validate([
            'notas' => 'required|string|max:500',
        ], [
            'notas.required' => 'Debe indicar que falta por hacer en la pieza.',
            'notas.max' => 'El comentario no puede superar los 500 caracteres.',
        ]);

        $user = auth()->user();
        $operarioAnteriorId = $pieza->operario_actual_id;
        $resultado = $this->piezaService->dejarEnCola($pieza, $user, $validated['notas']);

        if ($resultado['success']) {
            $this->registrarActividad('pieza.liberada_a_pool',
                "Pieza '{$pieza->nombre}' dejada en cola general. Falta: {$validated['notas']}",
                $pieza->orden_id,
                [
                    'tipo_cambio' => 'update',
                    'modelo' => 'OrdenPieza',
                    'modelo_id' => $pieza->id,
                    'cambios' => [
                        'operario_actual_id' => ['antes' => $operarioAnteriorId, 'despues' => null],
                    ],
                ]
            );
        }

        return response()->json($resultado);
    }

    /**
     * POST /operario/piezas/{pieza}/tomar
     */
    public function tomarPieza(OrdenPieza $pieza)
    {
        $user = auth()->user();
        $operarioAnteriorId = $pieza->operario_actual_id;
        $resultado = $this->piezaService->tomarPieza($pieza, $user);

        if ($resultado['success']) {
            $this->registrarActividad('pieza.tomada_de_pool',
                "Pieza '{$resultado['pieza']}' tomada de cola general",
                $resultado['orden_id'],
                [
                    'tipo_cambio' => 'update',
                    'modelo' => 'OrdenPieza',
                    'modelo_id' => $pieza->id,
                    'cambios' => [
                        'operario_actual_id' => ['antes' => $operarioAnteriorId, 'despues' => $user->id],
                    ],
                ]
            );
        }

        return response()->json($resultado);
    }

    /**
     * POST /operario/piezas/{pieza}/foto
     */
    public function subirFoto(Request $request, OrdenPieza $pieza)
    {
        $request->validate([
            'foto' => 'required|image|max:30720', // Max 30MB
        ], [
            'foto.required' => 'Debe seleccionar una foto.',
            'foto.image' => 'El archivo debe ser una imagen (JPG, PNG, etc.).',
            'foto.max' => 'La foto no puede pesar mas de 30 MB. Tamano maximo permitido: 30 MB.',
            'foto.uploaded' => 'La foto supera el tamano maximo permitido (30 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $user = auth()->user();
        $foto = $this->piezaService->subirFoto($pieza, $request->file('foto'), $user);

        $this->registrarCreacion(
            'pieza.foto_subida',
            "Foto subida para pieza de orden {$pieza->orden->numero_orden}",
            $foto,
            $pieza->orden_id
        );

        return response()->json([
            'success' => true,
            'foto' => [
                'id' => $foto->id,
                'url' => asset($foto->ruta_archivo),
            ],
        ]);
    }

    /**
     * POST /operario/piezas/{pieza}/observacion
     */
    public function guardarObservacion(Request $request, OrdenPieza $pieza)
    {
        $request->validate([
            'observacion' => 'required|string|max:2000',
        ], [
            'observacion.required' => 'Debe escribir una observacion.',
            'observacion.max' => 'La observacion no puede superar los 2000 caracteres.',
        ]);

        $user = auth()->user();

        $observacion = OrdenPiezaObservacion::create([
            'orden_id' => $pieza->orden_id,
            'orden_pieza_id' => $pieza->id,
            'user_id' => $user->id,
            'observacion' => $request->input('observacion'),
        ]);

        $this->registrarCreacion(
            'pieza.observacion_agregada',
            "Observacion agregada a pieza de orden {$pieza->orden->numero_orden}",
            $observacion,
            $pieza->orden_id
        );

        return response()->json([
            'success' => true,
            'observacion' => [
                'id' => $observacion->id,
                'texto' => $observacion->observacion,
                'usuario' => $user->name,
                'fecha' => $observacion->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    // ==========================================
    // AJAX: Bloqueo
    // ==========================================

    /**
     * POST /operario/ordenes/{orden}/bloquear
     */
    public function bloquear(Orden $orden)
    {
        $result = $this->bloqueoService->bloquear($orden, auth()->user());
        return response()->json($result);
    }

    /**
     * POST /operario/ordenes/{orden}/heartbeat
     */
    public function heartbeat(Orden $orden)
    {
        $renewed = $this->bloqueoService->renovarBloqueo($orden, auth()->user());
        return response()->json(['success' => $renewed]);
    }

    /**
     * POST /operario/ordenes/{orden}/desbloquear
     */
    public function desbloquear(Orden $orden)
    {
        $released = $this->bloqueoService->desbloquear($orden, auth()->user());
        return response()->json(['success' => $released]);
    }

    /**
     * GET /operario/ordenes/{orden}/estado-bloqueo
     */
    public function estadoBloqueo(Orden $orden)
    {
        $orden->refresh();
        $status = $this->bloqueoService->verificarBloqueo($orden);
        return response()->json($status);
    }

    /**
     * GET /operario/operarios-disponibles
     */
    public function operariosDisponibles()
    {
        $operarios = User::role('Operario')
            ->activos()
            ->where('id', '!=', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'operarios' => $operarios]);
    }

    // ==========================================
    // Helpers
    // ==========================================

    protected function badgeEstadoTrabajo(string $estado): string
    {
        $config = [
            'borrador' => ['label' => 'BORRADOR', 'class' => 'secondary'],
            'generada' => ['label' => 'GENERADA', 'class' => 'info'],
            'en_ejecucion' => ['label' => 'EN EJECUCION', 'class' => 'warning'],
            'ejecutada_parcialmente' => ['label' => 'EJECUTADA PARC.', 'class' => 'warning'],
            'ejecutada' => ['label' => 'EJECUTADA', 'class' => 'success'],
            'anulada' => ['label' => 'ANULADA', 'class' => 'danger'],
        ];

        $c = $config[$estado] ?? ['label' => strtoupper($estado), 'class' => 'secondary'];

        return '<span class="badge bg-' . $c['class'] . '">' . $c['label'] . '</span>';
    }
}
