<?php

namespace App\Http\Controllers;

use App\Exports\GarantiasExport;
use App\Exports\OperarioGarantiasExport;
use App\Models\DevolucionGarantia;
use App\Models\Orden;
use App\Models\User;
use App\Traits\RegistraActividad;
use Illuminate\Http\Request;
use App\Services\NotificacionService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class GarantiaController extends Controller
{
    use RegistraActividad;

    /**
     * GET /recepcion/garantias - Listado de todas las garantias (Admin/Recepcion).
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = DevolucionGarantia::with(['orden.cliente', 'pieza', 'operarioAsignado'])
                ->select('devoluciones_garantia.*');

            if ($request->filled('estado')) {
                $estados = (array) $request->input('estado');
                $estados = array_filter($estados, fn($e) => $e !== '' && $e !== null);
                if (!empty($estados)) {
                    $query->whereIn('estado', $estados);
                }
            }

            return DataTables::of($query)
                ->addColumn('orden_numero', function ($g) {
                    $num = $g->orden->numero_orden ?? '-';
                    $url = route('recepcion.ordenes.show', $g->orden_id);
                    return '<a href="' . $url . '" class="fw-semibold text-primary">' . $num . '</a>';
                })
                ->addColumn('pieza_nombre', function ($g) {
                    return $g->pieza->nombre ?? '-';
                })
                ->addColumn('cliente_nombre', function ($g) {
                    return $g->orden->cliente->nombre ?? '-';
                })
                ->addColumn('motivo_corto', function ($g) {
                    return '<span title="' . e($g->motivo) . '">' . \Str::limit($g->motivo, 50) . '</span>';
                })
                ->addColumn('cobrable_display', function ($g) {
                    if ($g->cobrable) {
                        return '<span class="text-danger fw-semibold">$' . number_format($g->monto_cobro, 0, '.', ',') . '</span>';
                    }
                    return '<span class="text-muted">-</span>';
                })
                ->addColumn('estado_badge', function ($g) {
                    return $this->badgeEstadoGarantia($g->estado);
                })
                ->addColumn('operario_nombre', function ($g) {
                    return $g->operarioAsignado->name ?? '<span class="text-muted">Sin asignar</span>';
                })
                ->editColumn('created_at', function ($g) {
                    return $g->created_at ? $g->created_at->format('d/m/Y H:i') : '-';
                })
                ->addColumn('acciones', function ($g) {
                    $url = route('recepcion.ordenes.show', $g->orden_id);
                    $html = '<div class="action-buttons justify-content-end">';
                    $html .= '<a href="' . $url . '#garantias" class="action-btn view" title="Ver Orden"><i class="bi bi-eye"></i></a>';

                    if ($g->estado === 'abierta') {
                        $html .= '<button type="button" class="action-btn btn-asignar-operario-garantia" data-id="' . $g->id . '" title="Asignar Operario"><i class="bi bi-person-plus"></i></button>';
                    }
                    if ($g->estado === 'completada') {
                        $html .= '<button type="button" class="action-btn btn-reentrega-garantia" data-id="' . $g->id . '" title="Marcar Reentregada"><i class="bi bi-box-arrow-right"></i></button>';
                    }

                    $html .= '</div>';
                    return $html;
                })
                ->rawColumns(['orden_numero', 'motivo_corto', 'cobrable_display', 'estado_badge', 'operario_nombre', 'acciones'])
                ->make(true);
        }

        $abiertas = DevolucionGarantia::where('estado', 'abierta')->count();
        $enProceso = DevolucionGarantia::where('estado', 'en_proceso')->count();
        $completadas = DevolucionGarantia::where('estado', 'completada')->count();
        $totalCobrables = DevolucionGarantia::where('cobrable', true)
            ->whereIn('estado', ['abierta', 'en_proceso', 'completada'])
            ->sum('monto_cobro');

        return view('garantias.index', compact('abiertas', 'enProceso', 'completadas', 'totalCobrables'));
    }

    /**
     * GET /recepcion/garantias/export-excel - Exportar garantias a Excel respetando filtros.
     */
    public function exportExcel(Request $request)
    {
        $query = DevolucionGarantia::with(['orden.cliente', 'pieza', 'operarioAsignado'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $estados = (array) $request->input('estado');
            $estados = array_filter($estados, fn($e) => $e !== '' && $e !== null);
            if (!empty($estados)) {
                $query->whereIn('estado', $estados);
            }
        }

        $garantias = $query->get();

        return Excel::download(
            new GarantiasExport($garantias),
            'garantias-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    /**
     * POST /recepcion/ordenes/{orden}/garantias - Registrar nueva garantia.
     */
    public function store(Request $request, Orden $orden)
    {
        $request->validate([
            'orden_pieza_id' => 'required|exists:orden_piezas,id',
            'cantidad_devuelta' => 'required|integer|min:1',
            'motivo' => 'required|string|max:2000',
            'cobrable' => 'nullable|boolean',
            'monto_cobro' => 'nullable|numeric|min:0',
            'operario_asignado_id' => 'nullable|exists:users,id',
        ]);

        $pieza = $orden->piezas()->where('id', $request->orden_pieza_id)->firstOrFail();

        if ($pieza->cantidad_entregada <= 0) {
            return response()->json(['success' => false, 'message' => 'Esta pieza no tiene unidades entregadas.'], 422);
        }

        $garantiasActivas = $pieza->garantias()
            ->where('estado', '!=', 'reentregada')
            ->sum('cantidad_devuelta');

        $disponible = $pieza->cantidad_entregada - $garantiasActivas;

        if ($request->cantidad_devuelta > $disponible) {
            return response()->json([
                'success' => false,
                'message' => "Solo hay {$disponible} unidad(es) disponible(s) para garantia."
            ], 422);
        }

        $cobrable = $request->boolean('cobrable');

        $garantia = DevolucionGarantia::create([
            'orden_id' => $orden->id,
            'orden_pieza_id' => $pieza->id,
            'cantidad_devuelta' => $request->cantidad_devuelta,
            'motivo' => $request->motivo,
            'cobrable' => $cobrable,
            'monto_cobro' => $cobrable ? $request->monto_cobro : null,
            'estado' => $request->operario_asignado_id ? 'en_proceso' : 'abierta',
            'operario_asignado_id' => $request->operario_asignado_id,
            'registrado_por' => auth()->id(),
        ]);

        $this->registrarCreacion(
            'garantia.registrada',
            "Garantia registrada para pieza '{$pieza->nombre}' (x{$request->cantidad_devuelta})",
            $garantia,
            $orden->id,
            ['pieza' => $pieza->nombre]
        );

        NotificacionService::garantiaRegistrada($garantia, $orden);

        return response()->json([
            'success' => true,
            'message' => 'Garantia registrada correctamente.',
            'garantia' => $garantia->load(['pieza', 'operarioAsignado']),
        ]);
    }

    /**
     * POST /recepcion/garantias/{garantia}/estado - Cambiar estado de garantia.
     */
    public function cambiarEstado(Request $request, DevolucionGarantia $garantia)
    {
        $request->validate([
            'estado' => 'required|in:en_proceso,completada,reentregada',
        ]);

        $nuevo = $request->estado;
        $actual = $garantia->estado;

        $transiciones = [
            'abierta' => ['en_proceso'],
            'en_proceso' => ['completada'],
            'completada' => ['reentregada'],
        ];

        if (!isset($transiciones[$actual]) || !in_array($nuevo, $transiciones[$actual])) {
            return response()->json([
                'success' => false,
                'message' => "No se puede pasar de '{$actual}' a '{$nuevo}'."
            ], 422);
        }

        if ($nuevo === 'en_proceso' && !$garantia->operario_asignado_id) {
            return response()->json([
                'success' => false,
                'message' => 'Debe asignar un operario antes de pasar a en proceso.'
            ], 422);
        }

        $valoresOriginales = $garantia->getOriginal();
        $garantia->estado = $nuevo;

        if ($nuevo === 'completada') {
            $garantia->completada_en = now();
        }
        if ($nuevo === 'reentregada') {
            $garantia->reentregada_en = now();
        }

        $garantia->save();

        $pieza = $garantia->pieza;
        $etiquetas = [
            'en_proceso' => 'en proceso',
            'completada' => 'completada',
            'reentregada' => 'reentregada',
        ];

        $this->registrarActualizacion(
            "garantia.{$nuevo}",
            "Garantia de pieza '{$pieza->nombre}' marcada como {$etiquetas[$nuevo]}",
            $garantia,
            $valoresOriginales,
            $garantia->orden_id
        );

        if ($nuevo === 'completada') {
            NotificacionService::garantiaCompletada($garantia);
        }
        if ($nuevo === 'reentregada') {
            NotificacionService::garantiaReentregada($garantia);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado a ' . strtoupper(str_replace('_', ' ', $nuevo)) . '.',
        ]);
    }

    /**
     * POST /recepcion/garantias/{garantia}/asignar-operario - Asignar operario a garantia.
     */
    public function asignarOperario(Request $request, DevolucionGarantia $garantia)
    {
        $request->validate([
            'operario_asignado_id' => 'required|exists:users,id',
        ]);

        $valoresOriginales = $garantia->getOriginal();
        $garantia->operario_asignado_id = $request->operario_asignado_id;

        if ($garantia->estado === 'abierta') {
            $garantia->estado = 'en_proceso';
        }

        $garantia->save();

        $operario = User::find($request->operario_asignado_id);
        $pieza = $garantia->pieza;

        $this->registrarActualizacion(
            'garantia.en_proceso',
            "Operario '{$operario->name}' asignado a garantia de pieza '{$pieza->nombre}'",
            $garantia,
            $valoresOriginales,
            $garantia->orden_id,
            ['operario_nombre' => $operario->name]
        );

        NotificacionService::garantiaAsignada($garantia);

        return response()->json([
            'success' => true,
            'message' => "Operario '{$operario->name}' asignado correctamente.",
        ]);
    }

    /**
     * POST /operario/garantias/{garantia}/completar - Operario marca garantia como completada.
     */
    public function completarTrabajo(DevolucionGarantia $garantia)
    {
        $user = auth()->user();

        if ($garantia->operario_asignado_id !== $user->id && !$user->hasRole('Administrador')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para completar esta garantia.'
            ], 403);
        }

        if ($garantia->estado !== 'en_proceso') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden completar garantias en proceso.'
            ], 422);
        }

        $valoresOriginales = $garantia->getOriginal();
        $garantia->update([
            'estado' => 'completada',
            'completada_en' => now(),
        ]);

        $pieza = $garantia->pieza;

        $this->registrarActualizacion(
            'garantia.completada',
            "Trabajo de garantia completado para pieza '{$pieza->nombre}'",
            $garantia,
            $valoresOriginales,
            $garantia->orden_id,
            ['operario' => $user->name]
        );

        NotificacionService::garantiaCompletada($garantia);

        return response()->json([
            'success' => true,
            'message' => 'Trabajo de garantia marcado como completado.',
        ]);
    }

    /**
     * GET /recepcion/ordenes/{orden}/piezas-entregadas - Piezas elegibles para garantia.
     */
    public function piezasEntregadas(Orden $orden)
    {
        $piezas = $orden->piezas()
            ->where('cantidad_entregada', '>', 0)
            ->with(['garantias' => function ($q) {
                $q->where('estado', '!=', 'reentregada');
            }])
            ->get()
            ->map(function ($pieza) {
                $garantiasActivas = $pieza->garantias->sum('cantidad_devuelta');
                $disponible = $pieza->cantidad_entregada - $garantiasActivas;
                return [
                    'id' => $pieza->id,
                    'nombre' => $pieza->nombre,
                    'cantidad' => $pieza->cantidad,
                    'cantidad_entregada' => $pieza->cantidad_entregada,
                    'disponible_garantia' => max(0, $disponible),
                    'material' => $pieza->material,
                    'calibre' => $pieza->calibre,
                ];
            })
            ->filter(fn($p) => $p['disponible_garantia'] > 0)
            ->values();

        return response()->json($piezas);
    }

    /**
     * GET /operario/garantias - Garantias asignadas al operario actual.
     */
    public function misGarantias(Request $request)
    {
        if ($request->ajax()) {
            $query = DevolucionGarantia::where('operario_asignado_id', auth()->id())
                ->where('estado', 'en_proceso')
                ->with(['orden.cliente', 'pieza'])
                ->select('devoluciones_garantia.*');

            return DataTables::of($query)
                ->addColumn('orden_numero', function ($g) {
                    $num = $g->orden->numero_orden ?? '-';
                    $url = route('recepcion.ordenes.show', $g->orden_id);
                    return '<a href="' . $url . '" class="fw-semibold text-primary">' . $num . '</a>';
                })
                ->addColumn('pieza_nombre', function ($g) {
                    return $g->pieza->nombre ?? '-';
                })
                ->addColumn('cliente_nombre', function ($g) {
                    return $g->orden->cliente->nombre ?? '-';
                })
                ->addColumn('motivo_corto', function ($g) {
                    return '<span title="' . e($g->motivo) . '">' . \Str::limit($g->motivo, 80) . '</span>';
                })
                ->editColumn('created_at', function ($g) {
                    return $g->created_at ? $g->created_at->format('d/m/Y H:i') : '-';
                })
                ->addColumn('acciones', function ($g) {
                    return '<button type="button" class="btn btn-success btn-sm btn-completar-garantia" data-id="' . $g->id . '">'
                        . '<i class="bi bi-check-lg me-1"></i>Completar</button>';
                })
                ->rawColumns(['orden_numero', 'motivo_corto', 'acciones'])
                ->make(true);
        }

        $pendientes = DevolucionGarantia::where('operario_asignado_id', auth()->id())
            ->where('estado', 'en_proceso')
            ->count();

        return view('operario.garantias', compact('pendientes'));
    }

    /**
     * GET /operario/garantias/export-excel - Exportar garantias asignadas al operario.
     */
    public function exportMisGarantiasExcel(Request $request)
    {
        $garantias = DevolucionGarantia::where('operario_asignado_id', auth()->id())
            ->where('estado', 'en_proceso')
            ->with(['orden.cliente', 'pieza'])
            ->orderBy('created_at', 'asc')
            ->get();

        return Excel::download(
            new OperarioGarantiasExport($garantias),
            'mis-garantias-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    protected function badgeEstadoGarantia(string $estado): string
    {
        $map = [
            'abierta' => ['warning', 'ABIERTA'],
            'en_proceso' => ['info', 'EN PROCESO'],
            'completada' => ['success', 'COMPLETADA'],
            'reentregada' => ['primary', 'REENTREGADA'],
        ];
        $cfg = $map[$estado] ?? ['secondary', strtoupper($estado)];
        return '<span class="status-badge ' . $cfg[0] . '">' . $cfg[1] . '</span>';
    }
}
