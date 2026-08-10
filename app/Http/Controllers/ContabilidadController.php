<?php

namespace App\Http\Controllers;

use App\Exports\ContabilidadOrdenesPendientesExport;
use App\Exports\HistorialFinancieroExport;
use App\Exports\PagosPendientesExport;
use App\Exports\ReporteItemsExport;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Services\DashboardService;
use App\Services\NotificacionService;
use App\Services\OrdenEstadoService;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ContabilidadController extends Controller
{
    use RegistraActividad;

    protected OrdenEstadoService $estadoService;
    protected DashboardService $dashboardService;

    public function __construct(OrdenEstadoService $estadoService, DashboardService $dashboardService)
    {
        $this->estadoService = $estadoService;
        $this->dashboardService = $dashboardService;
    }

    /**
     * GET /contabilidad/panel - Dashboard financiero.
     */
    public function panel()
    {
        $baseOrden = fn() => Orden::where('estado_pago', 'saldo_pendiente')
            ->whereNotIn('estado_trabajo', ['borrador', 'anulada']);

        $ordenesConSaldo = $baseOrden()->count();
        $abonosPorAprobar = Pago::where('aprobado', false)->count();
        $totalPendiente = $baseOrden()->sum('saldo');

        $recaudadoHoy = Pago::where('aprobado', true)
            ->whereDate('created_at', today())
            ->sum('monto');

        $recaudadoSemana = Pago::where('aprobado', true)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('monto');

        // Recaudo por metodo de pago (hoy)
        $porMetodoPago = Pago::where('aprobado', true)
            ->whereDate('created_at', today())
            ->selectRaw('metodo_pago, SUM(monto) as total')
            ->groupBy('metodo_pago')
            ->pluck('total', 'metodo_pago')
            ->toArray();

        // Ultimos 10 pagos aprobados recientes
        $ultimosPagos = Pago::where('aprobado', true)
            ->with(['orden.cliente', 'registradoPorUsuario'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        $garantiasCobrables = $this->dashboardService->getGarantiasCobrables();

        return view('contabilidad.panel', compact(
            'ordenesConSaldo',
            'abonosPorAprobar',
            'totalPendiente',
            'recaudadoHoy',
            'recaudadoSemana',
            'porMetodoPago',
            'ultimosPagos',
            'garantiasCobrables'
        ));
    }

    /**
     * GET /contabilidad/ordenes-pendientes - Ordenes con saldo pendiente.
     */
    public function ordenesPendientes(Request $request)
    {
        if ($request->ajax()) {
            $query = Orden::where('estado_pago', 'saldo_pendiente')
                ->whereNotIn('estado_trabajo', ['borrador', 'anulada'])
                ->with(['cliente', 'pagos'])
                ->select('ordenes.*');

            // Filtros
            if ($request->filled('numero_orden')) {
                $query->where('numero_orden', 'like', '%' . $request->input('numero_orden') . '%');
            }
            if ($request->filled('cliente')) {
                $query->whereHas('cliente', function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->input('cliente') . '%');
                });
            }
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
            }

            return DataTables::of($query)
                ->addColumn('cliente_nombre', function ($o) {
                    return $o->cliente->nombre ?? '-';
                })
                ->addColumn('total_formatted', function ($o) {
                    return '$' . number_format($o->total, 0, '.', ',');
                })
                ->addColumn('pagado_formatted', function ($o) {
                    return '<span class="text-success">$' . number_format($o->total_pagado, 0, '.', ',') . '</span>';
                })
                ->addColumn('saldo_formatted', function ($o) {
                    $class = $o->saldo > 0 ? 'text-danger fw-bold' : 'text-success';
                    return '<span class="' . $class . '" style="font-size:1rem">$' . number_format($o->saldo, 0, '.', ',') . '</span>';
                })
                ->addColumn('porcentaje_pagado', function ($o) {
                    $pct = $o->total > 0 ? round(($o->total_pagado / $o->total) * 100) : 0;
                    return '<div class="progress" style="height:6px;min-width:60px">'
                        . '<div class="progress-bar bg-success" style="width:' . $pct . '%"></div>'
                        . '</div>'
                        . '<small class="text-muted">' . $pct . '%</small>';
                })
                ->addColumn('estado_trabajo_badge', function ($o) {
                    return $this->badgeEstadoTrabajo($o->estado_trabajo);
                })
                ->addColumn('pagos_pendientes', function ($o) {
                    $count = $o->pagos->where('aprobado', false)->count();
                    if ($count > 0) {
                        return '<span class="badge bg-warning text-dark">' . $count . ' pend.</span>';
                    }
                    return '<span class="text-muted">-</span>';
                })
                ->addColumn('acciones', function ($o) {
                    $verUrl = route('contabilidad.ordenes.show', $o);
                    $html = '<div class="action-buttons justify-content-end">';
                    $html .= '<a href="' . $verUrl . '" class="action-btn view" title="Ver Orden" data-tooltip="Ver"><i class="bi bi-eye"></i></a>';
                    $disponible = $o->montoDisponibleNuevoPago();
                    $html .= '<button type="button" class="action-btn edit btn-agregar-pago" '
                        . 'data-orden-id="' . $o->id . '" '
                        . 'data-orden-numero="' . ($o->numero_orden ?? 'ID:' . $o->id) . '" '
                        . 'data-orden-cliente="' . ($o->cliente->nombre ?? '-') . '" '
                        . 'data-orden-saldo="' . number_format($o->saldo, 0, '.', ',') . '" '
                        . 'data-orden-saldo-num="' . $disponible . '" '
                        . 'title="Agregar Pago" data-tooltip="Agregar Pago"><i class="bi bi-plus-circle"></i></button>';
                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('numero_orden', function ($o) {
                    $url = route('contabilidad.ordenes.show', $o);
                    return '<a href="' . $url . '" class="fw-semibold text-decoration-none">' . ($o->numero_orden ?? '-') . '</a>';
                })
                ->rawColumns(['numero_orden', 'pagado_formatted', 'saldo_formatted', 'porcentaje_pagado', 'estado_trabajo_badge', 'pagos_pendientes', 'acciones'])
                ->make(true);
        }

        // Stats
        $baseQuery = fn() => Orden::where('estado_pago', 'saldo_pendiente')
            ->whereNotIn('estado_trabajo', ['borrador', 'anulada']);

        $totalOrdenes = $baseQuery()->count();
        $totalPendiente = $baseQuery()->sum('saldo');
        $abonosSinAprobar = Pago::where('aprobado', false)->count();
        $recaudadoHoy = Pago::where('aprobado', true)
            ->whereDate('created_at', today())
            ->sum('monto');

        return view('contabilidad.ordenes-pendientes', compact(
            'totalOrdenes', 'totalPendiente', 'abonosSinAprobar', 'recaudadoHoy'
        ));
    }

    /**
     * GET /contabilidad/ordenes-pendientes/export-excel - Exportar ordenes pendientes a Excel respetando filtros.
     */
    public function ordenesPendientesExportExcel(Request $request)
    {
        $query = Orden::where('estado_pago', 'saldo_pendiente')
            ->whereNotIn('estado_trabajo', ['borrador', 'anulada'])
            ->with(['cliente', 'pagos'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('numero_orden')) {
            $query->where('numero_orden', 'like', '%' . $request->input('numero_orden') . '%');
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->input('cliente') . '%');
            });
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        $ordenes = $query->get();

        return Excel::download(
            new ContabilidadOrdenesPendientesExport($ordenes),
            'ordenes-pendientes-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /contabilidad/pagos-pendientes/export-excel - Exportar pagos pendientes a Excel.
     */
    public function pagosPendientesExportExcel(Request $request)
    {
        $pagos = Pago::where('aprobado', false)
            ->with(['orden.cliente', 'registradoPorUsuario'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Excel::download(
            new PagosPendientesExport($pagos),
            'pagos-pendientes-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /contabilidad/pagos-pendientes - Pagos sin aprobar.
     */
    public function pagosPendientes(Request $request)
    {
        if ($request->ajax()) {
            $query = Pago::where('aprobado', false)
                ->with(['orden.cliente', 'registradoPorUsuario'])
                ->select('pagos.*');

            return DataTables::of($query)
                ->addColumn('fecha_formatted', function ($p) {
                    return $p->created_at->format('d/m/Y H:i');
                })
                ->addColumn('numero_orden', function ($p) {
                    $url = route('contabilidad.ordenes.show', $p->orden_id);
                    return '<a href="' . $url . '" class="fw-semibold text-decoration-none">' . ($p->orden->numero_orden ?? '-') . '</a>';
                })
                ->addColumn('cliente_nombre', function ($p) {
                    return $p->orden->cliente->nombre ?? '-';
                })
                ->addColumn('monto_formatted', function ($p) {
                    return '<span class="fw-bold" style="font-size:1rem">$' . number_format($p->monto, 0, '.', ',') . '</span>';
                })
                ->addColumn('metodo_badge', function ($p) {
                    return $this->badgeMetodoPago($p->metodo_pago);
                })
                ->addColumn('registrado_por_nombre', function ($p) {
                    return $p->registradoPorUsuario->name ?? '-';
                })
                ->addColumn('acciones', function ($p) {
                    $mapaPagos = TipoPago::mapaBadges();
                    $etiquetaMetodo = $mapaPagos[$p->metodo_pago]['etiqueta']
                        ?? ($mapaPagos[$p->metodo_pago]['nombre'] ?? ucfirst($p->metodo_pago));
                    $html = '<div class="action-buttons justify-content-end">';
                    $html .= '<button type="button" class="action-btn edit btn-aprobar-pago" '
                        . 'data-pago-id="' . $p->id . '" '
                        . 'data-pago-monto="' . number_format($p->monto, 0, '.', ',') . '" '
                        . 'data-pago-metodo="' . e($etiquetaMetodo) . '" '
                        . 'data-orden-numero="' . ($p->orden->numero_orden ?? '-') . '" '
                        . 'title="Aprobar" data-tooltip="Aprobar" style="width:36px;height:36px">'
                        . '<i class="bi bi-check-lg"></i></button>';
                    $html .= '<button type="button" class="action-btn delete btn-rechazar-pago" '
                        . 'data-pago-id="' . $p->id . '" '
                        . 'data-pago-monto="' . number_format($p->monto, 0, '.', ',') . '" '
                        . 'data-orden-numero="' . ($p->orden->numero_orden ?? '-') . '" '
                        . 'title="Rechazar" data-tooltip="Rechazar" style="width:36px;height:36px">'
                        . '<i class="bi bi-x-lg"></i></button>';
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('checkbox', function ($p) {
                    return '<input type="checkbox" class="form-check-input pago-checkbox" value="' . $p->id . '" data-monto="' . $p->monto . '" style="width:20px;height:20px;cursor:pointer">';
                })
                ->filterColumn('numero_orden', function ($query, $keyword) {
                    $query->whereHas('orden', function ($q) use ($keyword) {
                        $q->where('numero_orden', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('cliente_nombre', function ($query, $keyword) {
                    $query->whereHas('orden.cliente', function ($q) use ($keyword) {
                        $q->where('nombre', 'like', "%{$keyword}%");
                    });
                })
                ->orderColumn('fecha_formatted', function ($query, $order) {
                    $query->orderBy('created_at', $order);
                })
                ->rawColumns(['checkbox', 'numero_orden', 'monto_formatted', 'metodo_badge', 'acciones'])
                ->make(true);
        }

        // Stats
        $porAprobar = Pago::where('aprobado', false)->count();
        $montoPendiente = Pago::where('aprobado', false)->sum('monto');
        $aprobadosHoy = Pago::where('aprobado', true)
            ->whereDate('updated_at', today())
            ->count();

        return view('contabilidad.pagos-pendientes', compact(
            'porAprobar', 'montoPendiente', 'aprobadosHoy'
        ));
    }

    /**
     * POST /contabilidad/pagos/{pago}/aprobar - Aprobar pago individual.
     */
    public function aprobarPago(Pago $pago)
    {
        if ($pago->aprobado) {
            return response()->json(['success' => false, 'message' => 'Este pago ya esta aprobado.'], 422);
        }

        $disponible = $pago->orden->montoDisponibleAprobacion($pago->id);
        if ((float) $pago->monto > $disponible + 0.005) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede aprobar: el monto del pago ($' . number_format($pago->monto, 0, '.', ',') .
                             ') excede el saldo disponible de la orden ($' . number_format($disponible, 0, '.', ',') . '). Considere rechazarlo.',
            ], 422);
        }

        $valoresOriginales = $pago->getOriginal();
        $pago->update([
            'aprobado' => true,
            'aprobado_por' => auth()->id(),
        ]);

        $this->estadoService->recalcularTodo($pago->orden);

        $this->registrarActualizacion(
            'pago.aprobado',
            'Pago de $' . number_format($pago->monto, 0, '.', ',') . ' aprobado (Orden ' . ($pago->orden->numero_orden ?? 'ID:' . $pago->orden_id) . ')',
            $pago,
            $valoresOriginales,
            $pago->orden_id
        );

        NotificacionService::pagoAprobado($pago);

        $ordenFresh = $pago->orden->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Pago aprobado exitosamente.',
            'orden' => [
                'saldo' => '$' . number_format($ordenFresh->saldo, 0, '.', ','),
                'total_pagado' => '$' . number_format($ordenFresh->total_pagado, 0, '.', ','),
                'estado_pago' => $ordenFresh->estado_pago,
            ],
            'stats' => $this->statsPagosPendientes(),
        ]);
    }

    /**
     * POST /contabilidad/pagos/aprobar-masivo - Aprobar multiples pagos.
     */
    public function aprobarPagosMasivo(Request $request)
    {
        $request->validate([
            'pago_ids' => 'required|array|min:1',
            // NO usar exists:pagos,id: es estricto y con un solo id "fantasma" (un pago
            // que ya se aprobo/quito pero quedo en la seleccion del navegador) tumbaba
            // TODO el lote con "pago_ids.0 es invalido". El controlador ya filtra abajo
            // por aprobado=false + whereIn, asi que los ids invalidos se ignoran solos.
            'pago_ids.*' => 'required|integer',
        ]);

        $pagos = Pago::with('orden')
            ->where('aprobado', false)
            ->whereIn('id', $request->input('pago_ids'))
            ->get();

        if ($pagos->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No hay pagos pendientes para aprobar.'], 422);
        }

        // Validar que la suma del lote por orden no exceda el saldo disponible.
        // Si alguna orden quedaria con saldo negativo, se rechaza el lote completo.
        $errores = [];
        foreach ($pagos->groupBy('orden_id') as $ordenId => $pagosOrden) {
            $orden = $pagosOrden->first()->orden;
            if (!$orden) {
                continue;
            }
            $sumaLote = (float) $pagosOrden->sum('monto');
            $aprobadosActuales = (float) $orden->pagos()->where('aprobado', true)->sum('monto');
            if (($aprobadosActuales + $sumaLote) > (float) $orden->total + 0.005) {
                $disponible = max(0, (float) $orden->total - $aprobadosActuales);
                $errores[] = 'Orden ' . ($orden->numero_orden ?? 'ID:' . $ordenId) .
                             ': el lote suma $' . number_format($sumaLote, 0, '.', ',') .
                             ' pero solo hay $' . number_format($disponible, 0, '.', ',') . ' disponibles.';
            }
        }
        if (!empty($errores)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede aprobar el lote: ' . implode(' | ', $errores),
            ], 422);
        }

        $aprobados = 0;
        $montoTotal = 0;
        $ordenesAfectadas = collect();

        DB::beginTransaction();
        try {
            foreach ($pagos as $pago) {
                $valoresOriginales = $pago->getOriginal();
                $pago->update([
                    'aprobado' => true,
                    'aprobado_por' => auth()->id(),
                ]);

                $aprobados++;
                $montoTotal += $pago->monto;
                $ordenesAfectadas->push($pago->orden_id);

                $this->registrarActualizacion(
                    'pago.aprobado',
                    'Pago de $' . number_format($pago->monto, 0, '.', ',') . ' aprobado (Orden ' . ($pago->orden->numero_orden ?? 'ID:' . $pago->orden_id) . ')',
                    $pago,
                    $valoresOriginales,
                    $pago->orden_id,
                    ['masivo' => true]
                );

                NotificacionService::pagoAprobado($pago);
            }

            // Recalcular cada orden afectada
            $ordenesAfectadas->unique()->each(function ($ordenId) {
                $orden = Orden::find($ordenId);
                if ($orden) {
                    $this->estadoService->recalcularTodo($orden);
                }
            });

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $aprobados . ' pago(s) aprobado(s) por un total de $' . number_format($montoTotal, 0, '.', ','),
                'aprobados' => $aprobados,
                'monto_total' => '$' . number_format($montoTotal, 0, '.', ','),
                'stats' => $this->statsPagosPendientes(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar pagos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /contabilidad/ordenes/{orden}/pagos - Agregar pago (auto-aprobado).
     */
    public function agregarPago(Request $request, Orden $orden)
    {
        if (in_array($orden->estado_trabajo, ['anulada', 'borrador'])) {
            return response()->json(['success' => false, 'message' => 'No se puede agregar pago a esta orden.'], 422);
        }

        $codigosValidos = TipoPago::activos()->pluck('codigo')->toArray();
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo_pago' => ['required', \Illuminate\Validation\Rule::in($codigosValidos)],
            'referencia_pago' => 'nullable|string|max:255',
        ]);

        $disponible = $orden->montoDisponibleNuevoPago();
        if ((float) $request->monto > $disponible + 0.005) {
            return response()->json([
                'success' => false,
                'message' => 'El monto excede el saldo disponible. Maximo: $' . number_format($disponible, 0, '.', ','),
            ], 422);
        }

        $user = auth()->user();

        $pago = Pago::create([
            'orden_id' => $orden->id,
            'monto' => $request->monto,
            'metodo_pago' => $request->metodo_pago,
            'referencia_pago' => $request->referencia_pago,
            'registrado_por' => $user->id,
            'aprobado' => true,
            'aprobado_por' => $user->id,
        ]);

        $this->estadoService->recalcularTodo($orden);

        $this->registrarCreacion(
            'pago.registrado',
            'Pago de $' . number_format($request->monto, 0, '.', ',') . ' registrado y aprobado en orden ' . ($orden->numero_orden ?? 'ID:' . $orden->id),
            $pago,
            $orden->id
        );

        $ordenFresh = $orden->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Pago registrado y aprobado.',
            'pago' => [
                'id' => $pago->id,
                'monto' => '$' . number_format($pago->monto, 0, '.', ','),
                'metodo_pago' => $pago->metodo_pago,
                'fecha' => $pago->created_at->format('d/m/Y H:i'),
            ],
            'nuevo_saldo' => '$' . number_format($ordenFresh->saldo, 0, '.', ','),
            'nuevo_total_pagado' => '$' . number_format($ordenFresh->total_pagado, 0, '.', ','),
            'estado_pago' => $ordenFresh->estado_pago,
        ]);
    }

    /**
     * DELETE /contabilidad/pagos/{pago}/rechazar - Rechazar pago pendiente.
     */
    public function rechazarPago(Request $request, Pago $pago)
    {
        if ($pago->aprobado) {
            return response()->json(['success' => false, 'message' => 'No se puede rechazar un pago ya aprobado.'], 422);
        }

        $ordenId = $pago->orden_id;
        $monto = $pago->monto;
        $metodo = $pago->metodo_pago;
        $ordenNumero = $pago->orden->numero_orden ?? 'ID:' . $ordenId;

        $pago->update([
            'rechazado_por' => auth()->id(),
            'motivo_rechazo' => $request->input('motivo_rechazo'),
        ]);

        $this->registrarEliminacion(
            'pago.rechazado',
            'Pago de $' . number_format($monto, 0, '.', ',') . ' rechazado (Orden ' . $ordenNumero . ')',
            $pago,
            $ordenId,
            ['motivo_rechazo' => $request->input('motivo_rechazo')]
        );

        $pago->delete(); // Soft delete: marca deleted_at

        $orden = Orden::find($ordenId);
        if ($orden) {
            $this->estadoService->recalcularTodo($orden);
        }

        NotificacionService::pagoRechazado($pago, $ordenNumero, $ordenId);

        return response()->json([
            'success' => true,
            'message' => 'Pago rechazado.',
            'stats' => $this->statsPagosPendientes(),
        ]);
    }

    /**
     * GET /contabilidad/historial-financiero - Todas las ordenes con resumen financiero.
     */
    public function historialFinanciero(Request $request)
    {
        if ($request->ajax()) {
            $query = Orden::whereNotIn('estado_trabajo', ['borrador', 'anulada'])
                ->with(['cliente', 'pagos'])
                ->select('ordenes.*');

            // Filtros
            if ($request->filled('numero_orden')) {
                $query->where('numero_orden', 'like', '%' . $request->input('numero_orden') . '%');
            }
            if ($request->filled('cliente')) {
                $query->whereHas('cliente', function ($q) use ($request) {
                    $q->where('nombre', 'like', '%' . $request->input('cliente') . '%');
                });
            }
            if ($request->filled('estado_pago') && $request->input('estado_pago') !== 'todos') {
                $filtro = $request->input('estado_pago');
                if ($filtro === 'sin_pagos') {
                    $query->where('total_pagado', 0);
                } elseif ($filtro === 'pagada') {
                    $query->where('total_pagado', '>', 0)->where('saldo', '<=', 0);
                } elseif ($filtro === 'saldo_pendiente') {
                    $query->where('saldo', '>', 0)->where('total_pagado', '>', 0);
                }
            }
            if ($request->filled('fecha_desde')) {
                $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
            }

            return DataTables::of($query)
                ->addColumn('checkbox', function ($o) {
                    return '<input type="checkbox" class="form-check-input fila-check" checked'
                        . ' data-total="' . $o->total . '"'
                        . ' data-subtotal="' . $o->subtotal . '"'
                        . ' data-iva="' . $o->monto_iva . '"'
                        . ' data-pagado="' . $o->total_pagado . '"'
                        . ' data-saldo="' . $o->saldo . '">';
                })
                ->addColumn('cliente_nombre', fn($o) => $o->cliente->nombre ?? '-')
                ->addColumn('fecha_creacion', fn($o) => $o->created_at->format('d/m/Y'))
                ->addColumn('total_formatted', fn($o) => '$' . number_format($o->total, 0, '.', ','))
                ->addColumn('pagado_formatted', function ($o) {
                    return '<span class="text-success">$' . number_format($o->total_pagado, 0, '.', ',') . '</span>';
                })
                ->addColumn('saldo_formatted', function ($o) {
                    if ($o->saldo > 0) {
                        return '<span class="text-danger fw-bold">$' . number_format($o->saldo, 0, '.', ',') . '</span>';
                    }
                    return '<span class="text-success">$0</span>';
                })
                ->addColumn('porcentaje_pagado', function ($o) {
                    $pct = $o->total > 0 ? round(($o->total_pagado / $o->total) * 100) : 0;
                    $color = $pct >= 100 ? 'bg-success' : ($pct > 0 ? 'bg-warning' : 'bg-secondary');
                    return '<div class="progress" style="height:6px;min-width:60px">'
                        . '<div class="progress-bar ' . $color . '" style="width:' . $pct . '%"></div>'
                        . '</div>'
                        . '<small class="text-muted">' . $pct . '%</small>';
                })
                ->addColumn('estado_pago_badge', function ($o) {
                    if ($o->saldo <= 0 && $o->total_pagado > 0) {
                        return '<span class="status-badge success">PAGADA</span>';
                    }
                    if ($o->total_pagado > 0) {
                        return '<span class="status-badge danger">SALDO PEND.</span>';
                    }
                    return '<span class="status-badge secondary">SIN PAGOS</span>';
                })
                ->addColumn('num_pagos', function ($o) {
                    $count = $o->pagos->count();
                    if ($count > 0) {
                        return '<span class="badge bg-primary bg-opacity-10 text-primary border">' . $count . '</span>';
                    }
                    return '<span class="text-muted">0</span>';
                })
                ->addColumn('acciones', function ($o) {
                    $verUrl = route('contabilidad.ordenes.show', $o);
                    $html = '<div class="action-buttons justify-content-end">';
                    $html .= '<a href="' . $verUrl . '" class="action-btn view" title="Ver Orden" data-tooltip="Ver"><i class="bi bi-eye"></i></a>';
                    $html .= '<button type="button" class="action-btn edit btn-ver-pagos" '
                        . 'data-orden-id="' . $o->id . '" '
                        . 'data-orden-numero="' . ($o->numero_orden ?? 'ID:' . $o->id) . '" '
                        . 'title="Ver Pagos" data-tooltip="Ver Pagos"><i class="bi bi-receipt"></i></button>';
                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('numero_orden', function ($o) {
                    $url = route('contabilidad.ordenes.show', $o);
                    return '<a href="' . $url . '" class="fw-semibold text-decoration-none">' . ($o->numero_orden ?? '-') . '</a>';
                })
                ->rawColumns(['checkbox', 'numero_orden', 'pagado_formatted', 'saldo_formatted', 'porcentaje_pagado', 'estado_pago_badge', 'num_pagos', 'acciones'])
                ->make(true);
        }

        // Stats
        $baseQuery = fn() => Orden::whereNotIn('estado_trabajo', ['borrador', 'anulada']);

        $totalOrdenes = $baseQuery()->count();
        $ordenesPagadas = $baseQuery()->where('total_pagado', '>', 0)->where('saldo', '<=', 0)->count();
        $totalRecaudado = Pago::where('aprobado', true)->sum('monto');
        $totalPorCobrar = $baseQuery()->sum('saldo');

        return view('contabilidad.historial-financiero', compact(
            'totalOrdenes', 'ordenesPagadas', 'totalRecaudado', 'totalPorCobrar'
        ));
    }

    /**
     * GET /contabilidad/historial-financiero/export - Exportar historial financiero a Excel.
     */
    public function historialFinancieroExport(Request $request)
    {
        $query = Orden::whereNotIn('estado_trabajo', ['borrador', 'anulada'])
            ->with(['cliente', 'pagos'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('numero_orden')) {
            $query->where('numero_orden', 'like', '%' . $request->input('numero_orden') . '%');
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->input('cliente') . '%');
            });
        }
        if ($request->filled('estado_pago') && $request->input('estado_pago') !== 'todos') {
            $filtro = $request->input('estado_pago');
            if ($filtro === 'sin_pagos') {
                $query->where('total_pagado', 0);
            } elseif ($filtro === 'pagada') {
                $query->where('total_pagado', '>', 0)->where('saldo', '<=', 0);
            } elseif ($filtro === 'saldo_pendiente') {
                $query->where('saldo', '>', 0)->where('total_pagado', '>', 0);
            }
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        $ordenes = $query->get();

        return Excel::download(
            new HistorialFinancieroExport($ordenes),
            'historial-financiero-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * GET /contabilidad/ordenes/{orden}/pagos - Pagos de una orden (JSON para modal).
     */
    public function pagosOrden(Orden $orden)
    {
        $pagos = $orden->pagos()
            ->withTrashed()
            ->with(['registradoPorUsuario', 'aprobadoPorUsuario', 'rechazadoPorUsuario'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'fecha' => $p->created_at->format('d/m/Y H:i'),
                    'monto' => '$' . number_format($p->monto, 0, '.', ','),
                    'monto_raw' => $p->monto,
                    'metodo_pago' => ucfirst($p->metodo_pago),
                    'metodo_badge' => $this->badgeMetodoPago($p->metodo_pago),
                    'referencia_pago' => $p->referencia_pago ?: '-',
                    'registrado_por' => $p->registradoPorUsuario->name ?? '-',
                    'aprobado' => $p->aprobado,
                    'aprobado_por' => $p->aprobadoPorUsuario->name ?? null,
                    'fecha_aprobacion' => $p->aprobado && $p->updated_at ? $p->updated_at->format('d/m/Y H:i') : null,
                    'rechazado' => $p->trashed(),
                    'rechazado_por' => $p->rechazadoPorUsuario->name ?? null,
                    'motivo_rechazo' => $p->motivo_rechazo,
                    'fecha_rechazo' => $p->deleted_at ? $p->deleted_at->format('d/m/Y H:i') : null,
                ];
            });

        return response()->json([
            'success' => true,
            'orden' => [
                'numero' => $orden->numero_orden ?? 'ID:' . $orden->id,
                'cliente' => $orden->cliente->nombre ?? '-',
                'total' => '$' . number_format($orden->total, 0, '.', ','),
                'total_pagado' => '$' . number_format($orden->total_pagado, 0, '.', ','),
                'saldo' => '$' . number_format(abs($orden->saldo), 0, '.', ','),
                'saldo_raw' => (float) $orden->saldo,
                'estado_pago' => $orden->estado_pago,
            ],
            'pagos' => $pagos,
        ]);
    }

    /**
     * GET /contabilidad/reporte-items - Reporte de ventas por items (ordenes pagadas).
     */
    public function reporteItems(Request $request)
    {
        if ($request->ajax()) {
            $query = OrdenItem::query()
                ->join('ordenes', 'orden_items.orden_id', '=', 'ordenes.id')
                ->whereNotIn('ordenes.estado_trabajo', ['borrador', 'anulada'])
                ->select('orden_items.*', 'ordenes.numero_orden', 'ordenes.created_at as fecha_orden');

            if ($request->filled('estado_pago') && in_array($request->estado_pago, ['pagado', 'saldo_pendiente'])) {
                $query->where('ordenes.estado_pago', $request->estado_pago);
            }

            // Filtros
            if ($request->filled('busqueda')) {
                $busqueda = $request->busqueda;
                $query->where(function ($q) use ($busqueda) {
                    $q->where('orden_items.codigo', 'like', "%{$busqueda}%")
                      ->orWhere('orden_items.descripcion', 'like', "%{$busqueda}%");
                });
            }

            if ($request->filled('categoria') && $request->categoria !== 'todas') {
                $query->where('orden_items.categoria', $request->categoria);
            }

            if ($request->filled('fecha_desde')) {
                $query->whereDate('ordenes.created_at', '>=', $request->fecha_desde);
            }

            if ($request->filled('fecha_hasta')) {
                $query->whereDate('ordenes.created_at', '<=', $request->fecha_hasta);
            }

            // Calcular totales con los filtros aplicados
            $totalesQuery = OrdenItem::query()
                ->join('ordenes', 'orden_items.orden_id', '=', 'ordenes.id')
                ->whereNotIn('ordenes.estado_trabajo', ['borrador', 'anulada']);

            if ($request->filled('estado_pago') && in_array($request->estado_pago, ['pagado', 'saldo_pendiente'])) {
                $totalesQuery->where('ordenes.estado_pago', $request->estado_pago);
            }

            if ($request->filled('busqueda')) {
                $busqueda2 = $request->busqueda;
                $totalesQuery->where(function ($q) use ($busqueda2) {
                    $q->where('orden_items.codigo', 'like', "%{$busqueda2}%")
                      ->orWhere('orden_items.descripcion', 'like', "%{$busqueda2}%");
                });
            }
            if ($request->filled('categoria') && $request->categoria !== 'todas') {
                $totalesQuery->where('orden_items.categoria', $request->categoria);
            }
            if ($request->filled('fecha_desde')) {
                $totalesQuery->whereDate('ordenes.created_at', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $totalesQuery->whereDate('ordenes.created_at', '<=', $request->fecha_hasta);
            }

            $totales = $totalesQuery->selectRaw('
                SUM(orden_items.subtotal) as sum_subtotal,
                SUM(orden_items.monto_iva) as sum_iva,
                SUM(orden_items.total) as sum_total,
                SUM(orden_items.descuento_monto) as sum_descuento
            ')->first();

            return DataTables::of($query)
                ->with([
                    'totales' => [
                        'subtotal' => '$' . number_format($totales->sum_subtotal ?? 0, 0, '.', ','),
                        'iva' => '$' . number_format($totales->sum_iva ?? 0, 0, '.', ','),
                        'total' => '$' . number_format($totales->sum_total ?? 0, 0, '.', ','),
                        'descuento' => '$' . number_format($totales->sum_descuento ?? 0, 0, '.', ','),
                    ]
                ])
                ->addColumn('numero_orden_link', function ($item) {
                    $url = route('contabilidad.ordenes.show', $item->orden_id);
                    return '<a href="' . $url . '" class="text-decoration-none fw-semibold">' . e($item->numero_orden) . '</a>';
                })
                ->addColumn('fecha_orden_formatted', function ($item) {
                    return \Carbon\Carbon::parse($item->fecha_orden)->format('d/m/Y');
                })
                ->addColumn('categoria_badge', function ($item) {
                    return $this->badgeCategoria($item->categoria);
                })
                ->addColumn('cantidad_formatted', function ($item) {
                    return number_format($item->cantidad, 2);
                })
                ->addColumn('precio_formatted', function ($item) {
                    return '$' . number_format($item->precio_unitario, 0, '.', ',');
                })
                ->addColumn('descuento_formatted', function ($item) {
                    if ($item->descuento_porcentaje > 0) {
                        return '<span class="text-danger">' . \App\Helpers\Format::cantidad($item->descuento_porcentaje) . '%</span>'
                            . '<div class="small text-muted">-$' . number_format($item->descuento_monto, 0, '.', ',') . '</div>';
                    }
                    return '<span class="text-muted">-</span>';
                })
                ->addColumn('subtotal_formatted', function ($item) {
                    return '$' . number_format($item->subtotal, 0, '.', ',');
                })
                ->addColumn('iva_formatted', function ($item) {
                    return '$' . number_format($item->monto_iva, 0, '.', ',');
                })
                ->addColumn('total_formatted', function ($item) {
                    return '<span class="fw-bold">$' . number_format($item->total, 0, '.', ',') . '</span>';
                })
                ->rawColumns(['numero_orden_link', 'categoria_badge', 'total_formatted', 'descuento_formatted'])
                ->make(true);
        }

        // Stats para la vista
        $baseQuery = fn() => OrdenItem::query()
            ->join('ordenes', 'orden_items.orden_id', '=', 'ordenes.id')
            ->whereNotIn('ordenes.estado_trabajo', ['borrador', 'anulada']);

        $totalServicios = $baseQuery()->where('orden_items.categoria', 'servicio')->sum('orden_items.total');
        $totalMateriales = $baseQuery()->where('orden_items.categoria', 'material')->sum('orden_items.total');
        $totalProductos = $baseQuery()->where('orden_items.categoria', 'producto_terminado')->sum('orden_items.total');
        $totalSinIva = $baseQuery()->sum('orden_items.subtotal');
        $totalIva = $baseQuery()->sum('orden_items.monto_iva');
        $granTotal = $baseQuery()->sum('orden_items.total');
        $totalDescuentos = $baseQuery()->sum('orden_items.descuento_monto');
        $totalItems = $baseQuery()->count();

        return view('contabilidad.reporte-items', compact(
            'totalServicios', 'totalMateriales', 'totalProductos', 'totalSinIva', 'totalIva', 'granTotal', 'totalDescuentos', 'totalItems'
        ));
    }

    /**
     * GET /contabilidad/reporte-items/export - Exportar reporte a Excel.
     */
    public function reporteItemsExport(Request $request)
    {
        $query = OrdenItem::query()
            ->join('ordenes', 'orden_items.orden_id', '=', 'ordenes.id')
            ->whereNotIn('ordenes.estado_trabajo', ['borrador', 'anulada'])
            ->select('orden_items.*', 'ordenes.numero_orden', 'ordenes.created_at as fecha_orden')
            ->orderBy('ordenes.created_at', 'desc');

        if ($request->filled('estado_pago') && in_array($request->estado_pago, ['pagado', 'saldo_pendiente'])) {
            $query->where('ordenes.estado_pago', $request->estado_pago);
        }

        if ($request->filled('busqueda')) {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('orden_items.codigo', 'like', "%{$busqueda}%")
                  ->orWhere('orden_items.descripcion', 'like', "%{$busqueda}%");
            });
        }

        if ($request->filled('categoria') && $request->categoria !== 'todas') {
            $query->where('orden_items.categoria', $request->categoria);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('ordenes.created_at', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('ordenes.created_at', '<=', $request->fecha_hasta);
        }

        $items = $query->get();

        return Excel::download(
            new ReporteItemsExport($items),
            'reporte-ventas-items-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    // ---- Badge helpers ----

    protected function statsPagosPendientes(): array
    {
        return [
            'por_aprobar' => Pago::where('aprobado', false)->count(),
            'monto_pendiente' => '$' . number_format(Pago::where('aprobado', false)->sum('monto'), 0, '.', ','),
            'aprobados_hoy' => Pago::where('aprobado', true)->whereDate('updated_at', today())->count(),
        ];
    }

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

    protected function badgeEstadoPago(?string $estado): string
    {
        if (!$estado) return '<span class="text-muted">-</span>';
        $map = [
            'saldo_pendiente' => ['danger', 'SALDO PEND.'],
            'pagado' => ['success', 'PAGADO'],
        ];
        $cfg = $map[$estado] ?? ['secondary', strtoupper($estado)];
        return '<span class="status-badge ' . $cfg[0] . '">' . $cfg[1] . '</span>';
    }

    protected function badgeCategoria(string $categoria): string
    {
        $map = [
            'servicio' => ['info', 'SERVICIO'],
            'material' => ['warning', 'MATERIAL'],
            'producto_terminado' => ['success', 'PROD. TERMINADO'],
        ];
        $cfg = $map[$categoria] ?? ['secondary', strtoupper($categoria)];
        return '<span class="status-badge ' . $cfg[0] . '">' . $cfg[1] . '</span>';
    }

    protected function badgeMetodoPago(string $metodo): string
    {
        $mapa = TipoPago::mapaBadges();
        $sec = TipoPago::paletaColores()['secondary'];
        $cfg = $mapa[$metodo] ?? ['icono' => 'bi-three-dots', 'nombre' => ucfirst($metodo), 'etiqueta' => ucfirst($metodo), 'hex' => $sec['hex'], 'hex_dark' => $sec['hex_dark'], 'bg' => $sec['bg']];
        $texto = $cfg['etiqueta'] ?? $cfg['nombre'];
        // --m-fg-dark: color de texto claro para modo oscuro (lo aplica gva-global.css
        // con [data-theme="dark"] .badge-metodo). En claro se usa el 'color' inline.
        $hexDark = $cfg['hex_dark'] ?? $cfg['hex'];
        return '<span class="badge border badge-metodo" style="--m-fg-dark: ' . $hexDark . '; background-color: ' . $cfg['bg'] . '; color: ' . $cfg['hex'] . '; border-color: ' . $cfg['hex'] . '33 !important;"><i class="bi ' . $cfg['icono'] . ' me-1"></i>' . e($texto) . '</span>';
    }
}
