<?php

namespace App\Http\Controllers;

use App\Exports\OrdenesExport;
use App\Models\Cliente;
use App\Models\ConfiguracionSistema;
use App\Models\GrupoBosquejo;
use App\Models\PlantillaBosquejo;
use App\Models\Orden;
use App\Models\OrdenBosquejo;
use App\Models\OrdenComentario;
use App\Models\OrdenDocumento;
use App\Models\OrdenItem;
use App\Models\OrdenPieza;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Models\User;
use App\Services\NotificacionService;
use App\Services\OrdenEstadoService;
use App\Services\OrdenService;
use App\Traits\RegistraActividad;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class OrdenController extends Controller
{
    use RegistraActividad;

    protected OrdenService $ordenService;
    protected OrdenEstadoService $estadoService;

    public function __construct(OrdenService $ordenService, OrdenEstadoService $estadoService)
    {
        $this->ordenService = $ordenService;
        $this->estadoService = $estadoService;
    }

    /**
     * GET /recepcion/ordenes/crear - Vista del wizard.
     */
    public function create()
    {
        $materiales = ConfiguracionSistema::get('materiales_disponibles', []);
        $calibres = ConfiguracionSistema::get('calibres_disponibles', []);
        $ivaDefecto = ConfiguracionSistema::get('porcentaje_iva_defecto', 19.00);
        $autoSaveInterval = ConfiguracionSistema::get('timeout_autoguardado_recepcion', 5) * 60 * 1000; // ms
        $operarios = User::role('Operario')->activos()->orderBy('name')->get(['id', 'name']);
        $gruposBosquejos = GrupoBosquejo::with('plantillas')->orderBy('nombre')->get();
        $bosquejosSueltos = PlantillaBosquejo::whereNull('grupo_bosquejo_id')
            ->orderBy('nombre')->get();

        // Cliente predeterminado
        $clientePredeterminadoId = ConfiguracionSistema::get('cliente_predeterminado_id');
        $clientePredeterminado = $clientePredeterminadoId
            ? Cliente::find($clientePredeterminadoId)
            : null;

        return view('ordenes.create', compact(
            'materiales', 'calibres', 'ivaDefecto', 'autoSaveInterval',
            'operarios', 'gruposBosquejos', 'bosquejosSueltos',
            'clientePredeterminado'
        ));
    }

    /**
     * POST /recepcion/ordenes/guardar - Guardar como borrador (AJAX).
     */
    public function guardar(Request $request)
    {
        $data = $request->all();
        $user = $request->user();

        // Si ya existe una orden, cargarla
        $orden = null;
        if (!empty($data['orden_id'])) {
            $orden = Orden::where('id', $data['orden_id'])
                ->where('estado_trabajo', 'borrador')
                ->first();
        }

        $esCreacion = empty($data['orden_id']);
        $valoresOriginales = $orden ? $orden->getOriginal() : [];

        try {
            $orden = $this->ordenService->guardarBorrador($data, $user, $orden);

            if ($esCreacion) {
                $this->registrarCreacion(
                    'orden.creada',
                    "Orden borrador creada (ID: {$orden->id})",
                    $orden,
                    $orden->id
                );
            } else {
                $orden->refresh();
                $this->registrarActualizacion(
                    'orden.actualizada',
                    "Orden borrador actualizada (ID: {$orden->id})",
                    $orden,
                    $valoresOriginales,
                    $orden->id
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'La orden ha sido guardada exitosamente.',
                'orden_id' => $orden->id,
                'bosquejos' => $orden->bosquejosSincronizados ?? [],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errores = $e->errors();
            $primerError = collect($errores)->flatten()->first();
            return response()->json([
                'success' => false,
                'message' => $primerError ?: 'Datos invalidos.',
                'errores' => $errores,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/ordenes/generar - Generar con numero consecutivo (AJAX).
     */
    public function generar(Request $request)
    {
        $data = $request->all();
        $user = $request->user();

        $orden = null;
        if (!empty($data['orden_id'])) {
            $orden = Orden::where('id', $data['orden_id'])
                ->where('estado_trabajo', 'borrador')
                ->first();
        }

        $valoresOriginales = $orden ? $orden->getOriginal() : [];
        $eraBorrador = $orden !== null;

        try {
            $orden = DB::transaction(function () use ($data, $user, $orden) {
                return $this->ordenService->generarOrden($data, $user, $orden);
            });

            $orden->refresh();

            // Recepcion termino de generar: liberar el candado de edicion.
            app(\App\Services\BloqueoService::class)->desbloquear($orden, $user);

            if ($eraBorrador) {
                $this->registrarActualizacion(
                    'orden.creada',
                    "Orden generada {$orden->numero_orden}",
                    $orden,
                    $valoresOriginales,
                    $orden->id
                );
            } else {
                $this->registrarCreacion(
                    'orden.creada',
                    "Orden generada {$orden->numero_orden}",
                    $orden,
                    $orden->id
                );
            }

            return response()->json([
                'success' => true,
                'message' => "La orden ha sido generada con numero {$orden->numero_orden}",
                'orden_id' => $orden->id,
                'numero_orden' => $orden->numero_orden,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response = $e->getResponse();
            if ($response) {
                return $response;
            }
            return response()->json([
                'success' => false,
                'message' => 'Falta diligenciar informacion para poder GENERAR ORDEN',
                'errores' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/ordenes/subir-bosquejo - Subir bosquejo mid-wizard (AJAX).
     */
    public function subirBosquejo(Request $request)
    {
        $ordenId = $request->input('orden_id');
        $tipoOrigen = $request->input('tipo_origen', 'archivo_local');
        $nombre = $request->input('nombre');

        // Subida de imagen base64 (dibujo tablet)
        if ($request->has('imagen_base64')) {
            $request->validate([
                'imagen_base64' => 'required|string',
            ]);

            $bosquejo = $this->ordenService->subirBase64ComoBosquejo(
                $request->input('imagen_base64'),
                $tipoOrigen,
                $ordenId ? (int) $ordenId : null,
                $nombre
            );

            return response()->json([
                'success' => true,
                'bosquejo' => $bosquejo,
            ]);
        }

        // Subida de archivo normal
        $request->validate([
            'archivo' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
        ], [
            'archivo.required' => 'Debe seleccionar una imagen.',
            'archivo.image' => 'El archivo debe ser una imagen.',
            'archivo.mimes' => 'Solo se permiten imagenes JPG, PNG y WebP.',
            'archivo.max' => 'La imagen no puede exceder 10 MB. Tamano maximo permitido: 10 MB.',
            'archivo.uploaded' => 'La imagen supera el tamano maximo permitido (10 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $bosquejo = $this->ordenService->subirBosquejoTemporal(
            $request->file('archivo'),
            $tipoOrigen,
            $ordenId ? (int) $ordenId : null,
            $nombre,
            $request->input('plantilla_bosquejo_id')
        );

        return response()->json([
            'success' => true,
            'bosquejo' => $bosquejo,
        ]);
    }

    /**
     * POST /recepcion/ordenes/crear-cliente-inline - Crear cliente desde wizard (AJAX).
     */
    public function crearClienteInline(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string',
            'correo' => 'nullable|email|max:255',
            'celular_1' => 'nullable|string|max:20',
            'celular_2' => 'nullable|string|max:20',
        ], [
            'nombre.required' => 'El nombre del cliente es obligatorio.',
            'correo.email' => 'El correo debe ser una direccion de email valida.',
        ]);

        $cliente = Cliente::create($validated);

        $this->registrarCreacion(
            'cliente.creado',
            "Cliente creado desde wizard: {$cliente->nombre}",
            $cliente
        );

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado exitosamente.',
            'cliente' => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'correo' => $cliente->correo,
                'celular_1' => $cliente->celular_1,
                'celular_2' => $cliente->celular_2,
                'direccion' => $cliente->direccion,
            ],
        ]);
    }

    /**
     * GET /recepcion/ordenes/operarios - Lista de operarios activos (AJAX).
     */
    public function listarOperarios()
    {
        $operarios = User::role('Operario')
            ->activos()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'operarios' => $operarios,
        ]);
    }

    /**
     * GET /recepcion/ordenes/grupos-bosquejos - Grupos con plantillas (AJAX).
     */
    public function listarGruposBosquejos()
    {
        $grupos = GrupoBosquejo::with(['plantillas' => function ($q) {
            $q->orderBy('nombre');
        }])->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'grupos' => $grupos,
        ]);
    }

    // ==========================================
    // FASE 6: Busqueda y Gestion
    // ==========================================

    /**
     * GET /recepcion/ordenes - Listado con DataTable server-side.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = $this->aplicarFiltrosListado($request);

            return DataTables::of($query)
                ->addColumn('cliente_nombre', function ($o) {
                    return $o->cliente->nombre ?? '-';
                })
                ->addColumn('estado_trabajo_badge', function ($o) {
                    return $this->badgeEstadoTrabajo($o->estado_trabajo);
                })
                ->addColumn('estado_entrega_badge', function ($o) {
                    return $this->badgeEstadoEntrega($o->estado_entrega);
                })
                ->addColumn('estado_pago_badge', function ($o) {
                    return $this->badgeEstadoPago($o->estado_pago);
                })
                ->addColumn('porcentaje_total_html', function ($o) {
                    return $this->badgePorcentajeTotal($o);
                })
                ->addColumn('total_formatted', function ($o) {
                    return '$' . number_format($o->total, 0, '.', ',');
                })
                ->addColumn('saldo_formatted', function ($o) {
                    if ($o->saldo > 0) {
                        $class = ' text-danger fw-semibold';
                    } elseif ($o->saldo < 0) {
                        $class = ' text-warning fw-semibold';
                    } else {
                        $class = '';
                    }
                    return '<span class="' . $class . '">$' . number_format($o->saldo, 0, '.', ',') . '</span>';
                })
                ->addColumn('acciones', function ($o) {
                    $puedeEscribir = auth()->user()->hasAnyRole(['Administrador', 'Recepcion']);
                    $puedeEditar   = auth()->user()->hasAnyRole(['Administrador', 'Recepcion', 'Contabilidad']);
                    $viewUrl = route('recepcion.ordenes.show', $o);

                    $html = '<div class="action-buttons justify-content-end">';
                    $html .= '<a href="' . $viewUrl . '" class="action-btn view" title="Ver"><i class="bi bi-eye"></i></a>';

                    if ($puedeEditar && $o->estado_trabajo !== 'anulada') {
                        $editUrl = route('recepcion.ordenes.edit', $o);
                        $html .= '<a href="' . $editUrl . '" class="action-btn edit" title="Editar"><i class="bi bi-pencil"></i></a>';
                    }

                    if ($puedeEscribir) {
                        $html .= '<button type="button" class="action-btn" title="Copiar" onclick="copiarOrden(' . $o->id . ')"><i class="bi bi-copy"></i></button>';
                    }

                    if ($o->estado_trabajo !== 'anulada') {
                        $pdfUrl = route('recepcion.ordenes.pdf', $o);
                        $pdfTitle = $o->estado_trabajo === 'borrador' ? 'Cotizacion PDF' : 'PDF';
                        $html .= '<a href="' . $pdfUrl . '" class="action-btn" title="' . $pdfTitle . '" target="_blank"><i class="bi bi-file-earmark-pdf"></i></a>';
                    }

                    if (!in_array($o->estado_trabajo, ['anulada', 'borrador'])) {
                        if ($puedeEscribir) {
                            $html .= '<button type="button" class="action-btn delete" title="Anular" onclick="anularOrden(' . $o->id . ', \'' . addslashes($o->numero_orden) . '\')"><i class="bi bi-x-circle"></i></button>';
                        }
                    }

                    if ($o->estado_trabajo === 'borrador' && $puedeEscribir) {
                        $html .= '<button type="button" class="action-btn delete" title="Eliminar borrador" onclick="eliminarBorrador(' . $o->id . ')"><i class="bi bi-trash"></i></button>';
                    }

                    $html .= '</div>';
                    return $html;
                })
                ->editColumn('numero_orden', function ($o) {
                    if ($o->numero_orden) {
                        return $o->numero_orden;
                    }

                    $html = '<span class="text-muted fst-italic">Borrador #' . $o->id . '</span>';
                    $diasExpiracion = \App\Models\ConfiguracionSistema::get('dias_expiracion_borradores', 30);
                    $diasTranscurridos = $o->updated_at->diffInDays(now());
                    $diasRestantes = $diasExpiracion - $diasTranscurridos;

                    if ($diasRestantes <= 3) {
                        $html .= ' <span class="badge bg-danger ms-1" title="Se eliminara por inactividad">Expira en ' . max(0, $diasRestantes) . 'd</span>';
                    } elseif ($diasRestantes <= 7) {
                        $html .= ' <span class="badge bg-warning text-dark ms-1" title="Se eliminara por inactividad">Expira en ' . $diasRestantes . 'd</span>';
                    }

                    return $html;
                })
                ->editColumn('created_at', function ($o) {
                    return $o->created_at ? $o->created_at->format('d/m/Y') : '-';
                })
                ->editColumn('fecha_entrega', function ($o) {
                    return $o->fecha_entrega ? $o->fecha_entrega->format('d/m/Y') : '-';
                })
                ->rawColumns(['numero_orden', 'estado_trabajo_badge', 'estado_entrega_badge', 'estado_pago_badge', 'porcentaje_total_html', 'saldo_formatted', 'acciones'])
                ->make(true);
        }

        // Stats para las cards
        $totalOrdenes = Orden::count();
        $borradores = Orden::borradores()->count();
        $enProceso = Orden::whereIn('estado_trabajo', ['generada', 'en_ejecucion', 'ejecutada_parcialmente'])->count();
        $ejecutadas = Orden::ejecutadas()->count();
        $saldoPendienteTotal = Orden::noAnuladas()->noBorradores()->where('saldo', '>', 0)->sum('saldo');

        $creadores = User::whereIn('id', Orden::whereNotNull('creado_por')->distinct()->pluck('creado_por'))
            ->activos()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('ordenes.index', compact(
            'totalOrdenes', 'borradores', 'enProceso', 'ejecutadas', 'saldoPendienteTotal', 'creadores'
        ));
    }

    /**
     * GET /recepcion/ordenes/{orden} - Detalle completo de la orden.
     */
    public function show(Orden $orden)
    {
        $orden->load([
            'cliente',
            'creador',
            'items',
            'bosquejos',
            'piezas.bosquejo',
            'piezas.operarioActual',
            'piezas.asignaciones.asignadoA',
            'piezas.historialAvances.operario',
            'piezas.observaciones.usuario',
            'pagos' => function ($q) {
                $q->withTrashed()->with(['registradoPorUsuario', 'aprobadoPorUsuario', 'rechazadoPorUsuario']);
            },
            'fotos.subidoPorUsuario',
            'entregas.piezas.ordenPieza',
            'entregas.entregadaPorUsuario',
            'entregas.fotos',
            'comentarios.usuario',
            'garantias.pieza',
            'garantias.operarioAsignado',
            'garantias.registradoPorUsuario',
            'ordenOriginal',
        ]);

        $resumenCategorias = $orden->items->groupBy('categoria')->map(function ($items, $cat) {
            return [
                'subtotal' => $items->sum('subtotal'),
                'iva' => $items->sum('monto_iva'),
                'total' => $items->sum('total'),
            ];
        });

        return view('ordenes.show', compact('orden', 'resumenCategorias'));
    }

    /**
     * GET /recepcion/ordenes/{orden}/editar - Wizard precargado para edicion.
     */
    public function edit(Orden $orden)
    {
        if ($orden->estado_trabajo === 'anulada') {
            return redirect()->route('recepcion.ordenes.show', $orden)
                ->with('error', 'No se puede editar una orden anulada.');
        }

        // Verificar si la orden esta bloqueada por un operario
        if ($orden->bloqueada_por && (int) $orden->bloqueada_por !== auth()->id()) {
            $bloqueoService = app(\App\Services\BloqueoService::class);
            $lockStatus = $bloqueoService->verificarBloqueo($orden);

            if ($lockStatus['locked']) {
                $bloqueador = \App\Models\User::find($lockStatus['locked_by_id']);
                $roleService = app(\App\Services\Auth\RoleService::class);

                if ($bloqueador && $roleService->getJerarquia(auth()->user()) > $roleService->getJerarquia($bloqueador)) {
                    // Mayor jerarquia: ofrecer forzar cierre
                    $bloqueoService->forzarCierre($orden, auth()->user());
                    return redirect()->route('recepcion.ordenes.show', $orden)
                        ->with('warning', "La orden esta siendo trabajada por {$bloqueador->name}. Se le ha notificado que la cierre. Intenta editar nuevamente en 1 minuto.");
                } else {
                    // Mismo rango o menor: no puede editar
                    return redirect()->route('recepcion.ordenes.show', $orden)
                        ->with('error', "Esta orden esta siendo editada por {$lockStatus['locked_by']}. Intenta mas tarde.");
                }
            }
        }

        // Recepcion adquiere el candado de la orden mientras la edita. Asi, si un
        // operario intenta abrirla, vera "orden no disponible - Recepcion editando",
        // y se evita la edicion simultanea que pisaba el trabajo de los operarios.
        app(\App\Services\BloqueoService::class)->bloquear($orden, auth()->user());

        $orden->load(['cliente', 'items', 'bosquejos', 'piezas', 'pagos']);

        $materiales = ConfiguracionSistema::get('materiales_disponibles', []);
        $calibres = ConfiguracionSistema::get('calibres_disponibles', []);
        $ivaDefecto = ConfiguracionSistema::get('porcentaje_iva_defecto', 19.00);
        $autoSaveInterval = ConfiguracionSistema::get('timeout_autoguardado_recepcion', 5) * 60 * 1000;
        $operarios = User::role('Operario')->activos()->orderBy('name')->get(['id', 'name']);
        $gruposBosquejos = GrupoBosquejo::with('plantillas')->orderBy('nombre')->get();
        $bosquejosSueltos = PlantillaBosquejo::whereNull('grupo_bosquejo_id')
            ->orderBy('nombre')->get();

        // Preparar datos JSON para el wizard JS
        $ordenData = [
            'id' => $orden->id,
            'cliente' => $orden->cliente ? [
                'id' => $orden->cliente->id,
                'nombre' => $orden->cliente->nombre,
                'celular_1' => $orden->cliente->celular_1,
                'correo' => $orden->cliente->correo,
            ] : null,
            'items' => $orden->items->map(fn ($item) => [
                'catalogo_item_id' => $item->catalogo_item_id,
                'codigo' => $item->codigo,
                'descripcion' => $item->descripcion,
                'cantidad' => $item->cantidad,
                'precio_unitario' => $item->precio_unitario,
                'porcentaje_iva' => $item->porcentaje_iva,
                'descuento_porcentaje' => $item->descuento_porcentaje,
                'categoria' => $item->categoria,
            ])->values(),
            'bosquejos' => $orden->bosquejos->map(fn ($b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'tipo_origen' => $b->tipo_origen,
                'ruta_archivo' => $b->ruta_archivo,
                'ruta_miniatura' => $b->ruta_miniatura,
                'plantilla_bosquejo_id' => $b->plantilla_bosquejo_id,
            ])->values(),
            'piezas' => $orden->piezas->sortBy('orden_visual')->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'cantidad' => $p->cantidad,
                'material' => $p->material,
                'calibre' => $p->calibre,
                'notas' => $p->notas,
                'orden_bosquejo_id' => $p->orden_bosquejo_id,
                'operario_actual_id' => $p->operario_actual_id,
            ])->values(),
            'pagos' => $orden->pagos->map(fn ($p) => [
                'monto' => $p->monto,
                'metodo_pago' => $p->metodo_pago,
                'referencia_pago' => $p->referencia_pago,
            ])->values(),
            'fecha_entrega' => $orden->fecha_entrega ? $orden->fecha_entrega->format('Y-m-d') : null,
            'hora_entrega' => $orden->hora_entrega,
            'notas' => $orden->notas,
            'ruta_firma_cliente' => $orden->ruta_firma_cliente,
            'estado_trabajo' => $orden->estado_trabajo,
        ];

        return view('ordenes.edit', compact(
            'orden', 'materiales', 'calibres', 'ivaDefecto', 'autoSaveInterval',
            'operarios', 'gruposBosquejos', 'bosquejosSueltos', 'ordenData'
        ));
    }

    /**
     * PUT /recepcion/ordenes/{orden} - Guardar cambios (AJAX).
     */
    public function update(Request $request, Orden $orden)
    {
        if ($orden->estado_trabajo === 'anulada') {
            return response()->json(['success' => false, 'message' => 'Orden anulada no se puede editar.'], 403);
        }

        $data = $request->all();
        $user = $request->user();
        $valoresOriginales = $orden->getOriginal();

        // Mantener vivo el candado de edicion de Recepcion mientras autoguarda/guarda.
        app(\App\Services\BloqueoService::class)->renovarBloqueo($orden, $user);

        try {
            $this->ordenService->guardarBorrador($data, $user, $orden);

            // Extraer y remover atributo transiente antes de cualquier save posterior
            $bosquejosSincronizados = $orden->bosquejosSincronizados ?? [];
            unset($orden->bosquejosSincronizados);

            if ($orden->estado_trabajo !== 'borrador') {
                $this->estadoService->recalcularTodo($orden);
            }

            $orden->refresh();

            $this->registrarActualizacion(
                'orden.actualizada',
                "Orden actualizada (ID: {$orden->id})",
                $orden,
                $valoresOriginales,
                $orden->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Orden actualizada exitosamente.',
                'orden_id' => $orden->id,
                'bosquejos' => $bosquejosSincronizados,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/ordenes/{orden}/liberar-edicion
     * Libera el candado de edicion cuando Recepcion sale del asistente (via beacon),
     * para que los operarios puedan volver a trabajar la orden de inmediato.
     */
    public function liberarEdicion(Orden $orden)
    {
        app(\App\Services\BloqueoService::class)->desbloquear($orden, auth()->user());
        return response()->json(['success' => true]);
    }

    /**
     * POST /recepcion/ordenes/{orden}/copiar - Copiar orden como nuevo borrador.
     */
    public function copiar(Orden $orden)
    {
        $user = auth()->user();

        try {
            $nuevaOrden = DB::transaction(function () use ($orden, $user) {
                $nueva = Orden::create([
                    'cliente_id' => $orden->cliente_id,
                    'creado_por' => $user->id,
                    'estado_trabajo' => 'borrador',
                    'notas' => $orden->notas,
                    'clonada_de_id' => $orden->id,
                ]);

                // Copiar items
                foreach ($orden->items as $item) {
                    OrdenItem::create([
                        'orden_id' => $nueva->id,
                        'catalogo_item_id' => $item->catalogo_item_id,
                        'codigo' => $item->codigo,
                        'descripcion' => $item->descripcion,
                        'cantidad' => $item->cantidad,
                        'precio_unitario' => $item->precio_unitario,
                        'porcentaje_iva' => $item->porcentaje_iva,
                        'descuento_porcentaje' => $item->descuento_porcentaje,
                        'descuento_monto' => $item->descuento_monto,
                        'categoria' => $item->categoria,
                        'subtotal' => $item->subtotal,
                        'monto_iva' => $item->monto_iva,
                        'total' => $item->total,
                    ]);
                }

                // Copiar bosquejos con archivos fisicos
                $mapBosquejos = [];
                foreach ($orden->bosquejos as $bosquejo) {
                    $archivos = $this->ordenService->copiarArchivosBosquejo($bosquejo, $nueva->id);
                    $nuevoBosquejo = OrdenBosquejo::create([
                        'orden_id' => $nueva->id,
                        'plantilla_bosquejo_id' => $bosquejo->plantilla_bosquejo_id,
                        'tipo_origen' => $bosquejo->tipo_origen,
                        'nombre' => $bosquejo->nombre,
                        'ruta_archivo' => $archivos['ruta_archivo'],
                        'ruta_miniatura' => $archivos['ruta_miniatura'],
                        'orden_visual' => $bosquejo->orden_visual,
                    ]);
                    $mapBosquejos[$bosquejo->id] = $nuevoBosquejo->id;
                }

                // Copiar piezas (sin avance, sin operario)
                foreach ($orden->piezas as $pieza) {
                    $bosquejoId = $pieza->orden_bosquejo_id
                        ? ($mapBosquejos[$pieza->orden_bosquejo_id] ?? null)
                        : null;

                    OrdenPieza::create([
                        'orden_id' => $nueva->id,
                        'orden_bosquejo_id' => $bosquejoId,
                        'nombre' => $pieza->nombre,
                        'nombre_automatico' => $pieza->nombre_automatico,
                        'cantidad' => $pieza->cantidad,
                        'material' => $pieza->material,
                        'calibre' => $pieza->calibre,
                        'especificacion' => $pieza->especificacion,
                        'notas' => $pieza->notas,
                        'porcentaje_avance' => 0,
                        'estado' => 'pendiente',
                        'entregada' => false,
                        'orden_visual' => $pieza->orden_visual,
                    ]);
                }

                // Recalcular totales
                $this->estadoService->recalcularTotales($nueva);
                $nueva->save();

                return $nueva;
            });

            $this->registrarCreacion(
                'orden.copiada',
                "Orden copiada desde " . ($orden->numero_orden ?? 'ID:' . $orden->id) . " como borrador (ID: {$nuevaOrden->id})",
                $nuevaOrden,
                $nuevaOrden->id,
                ['orden_original_id' => $orden->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Orden copiada como nuevo borrador.',
                'redirect' => route('recepcion.ordenes.edit', $nuevaOrden),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al copiar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/ordenes/{orden}/anular - Anular orden.
     */
    public function anular(Request $request, Orden $orden)
    {
        if ($orden->estado_trabajo === 'anulada') {
            return response()->json(['success' => false, 'message' => 'La orden ya esta anulada.'], 422);
        }
        if ($orden->estado_trabajo === 'borrador') {
            return response()->json(['success' => false, 'message' => 'No se puede anular un borrador.'], 422);
        }

        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        $valoresOriginales = $orden->getOriginal();

        try {
            DB::transaction(function () use ($orden) {
                $orden->asignaciones()->where('activa', true)->update(['activa' => false]);
                $orden->piezas()->update(['operario_actual_id' => null]);
                $orden->update([
                    'estado_trabajo' => 'anulada',
                    'estado_entrega' => null,
                    'estado_pago' => null,
                ]);
            });

            $orden->refresh();

            $this->registrarActualizacion(
                'orden.anulada',
                "Orden {$orden->numero_orden} anulada. Motivo: {$request->motivo}",
                $orden,
                $valoresOriginales,
                $orden->id,
                ['motivo' => $request->motivo]
            );

            return response()->json([
                'success' => true,
                'message' => "Orden {$orden->numero_orden} anulada exitosamente.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al anular: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /recepcion/ordenes/{orden} - Eliminar borrador (solo estado 'borrador').
     * Registra snapshot completo en registros_actividad y en laravel.log antes de eliminar.
     */
    public function destroy(Orden $orden)
    {
        if ($orden->estado_trabajo !== 'borrador') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden eliminar borradores. Use Anular para ordenes generadas.',
            ], 422);
        }

        $ordenId = $orden->id;
        $userId = auth()->id();
        $userName = auth()->user()->name ?? 'desconocido';

        $orden->load(['items', 'bosquejos', 'piezas', 'documentos', 'fotos', 'comentarios', 'asignaciones']);

        $snapshot = [
            'orden' => $orden->getAttributes(),
            'cliente_id' => $orden->cliente_id,
            'items' => $orden->items->map->getAttributes()->all(),
            'bosquejos' => $orden->bosquejos->map->getAttributes()->all(),
            'piezas' => $orden->piezas->map->getAttributes()->all(),
            'documentos' => $orden->documentos->map->getAttributes()->all(),
            'fotos' => $orden->fotos->map->getAttributes()->all(),
            'comentarios_count' => $orden->comentarios->count(),
            'asignaciones_count' => $orden->asignaciones->count(),
        ];

        $archivosFisicos = [];
        foreach ($orden->documentos as $doc) {
            if ($doc->ruta_archivo) $archivosFisicos[] = $doc->ruta_archivo;
        }
        foreach ($orden->bosquejos as $b) {
            if ($b->ruta_archivo) $archivosFisicos[] = $b->ruta_archivo;
            if ($b->ruta_miniatura) $archivosFisicos[] = $b->ruta_miniatura;
        }
        foreach ($orden->fotos as $f) {
            if ($f->ruta_archivo) $archivosFisicos[] = $f->ruta_archivo;
            if ($f->ruta_miniatura) $archivosFisicos[] = $f->ruta_miniatura;
        }
        if ($orden->ruta_firma_cliente) $archivosFisicos[] = $orden->ruta_firma_cliente;

        try {
            $this->registrarActividad(
                'orden.borrador_eliminado',
                "Borrador #{$ordenId} eliminado por {$userName}",
                null,
                [
                    'tipo_cambio' => 'delete',
                    'modelo' => 'Orden',
                    'modelo_id' => $ordenId,
                    'snapshot' => $snapshot,
                    'archivos_eliminados' => $archivosFisicos,
                ]
            );

            Log::info('Borrador eliminado', [
                'orden_id' => $ordenId,
                'user_id' => $userId,
                'user_name' => $userName,
                'archivos_eliminados' => $archivosFisicos,
                'snapshot' => $snapshot,
            ]);

            DB::transaction(function () use ($orden) {
                $orden->delete();
            });

            foreach ($archivosFisicos as $ruta) {
                $path = public_path($ruta);
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Borrador #{$ordenId} eliminado exitosamente.",
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar borrador', [
                'orden_id' => $ordenId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar borrador: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /recepcion/ordenes/{orden}/comentarios - Agregar comentario (AJAX).
     */
    public function agregarComentario(Request $request, Orden $orden)
    {
        $request->validate([
            'contenido' => 'required|string|max:2000',
        ]);

        $comentario = OrdenComentario::create([
            'orden_id' => $orden->id,
            'usuario_id' => auth()->id(),
            'contenido' => $request->contenido,
        ]);

        $this->registrarCreacion(
            'orden.comentario_agregado',
            "Comentario agregado a orden {$orden->numero_orden}",
            $comentario,
            $orden->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Comentario agregado.',
            'comentario' => [
                'id' => $comentario->id,
                'contenido' => $comentario->contenido,
                'usuario' => auth()->user()->name,
                'fecha' => $comentario->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * POST /recepcion/ordenes/{orden}/pagos - Registrar pago (AJAX).
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
        $autoAprueba = $user->hasAnyRole(['Administrador', 'Contabilidad']);

        $pago = Pago::create([
            'orden_id' => $orden->id,
            'monto' => $request->monto,
            'metodo_pago' => $request->metodo_pago,
            'referencia_pago' => $request->referencia_pago,
            'registrado_por' => $user->id,
            'aprobado' => $autoAprueba,
            'aprobado_por' => $autoAprueba ? $user->id : null,
        ]);

        $this->estadoService->recalcularTodo($orden);

        $this->registrarCreacion(
            'pago.registrado',
            'Pago de $' . number_format($request->monto, 0, '.', ',') . " registrado en orden " . ($orden->numero_orden ?? 'ID:' . $orden->id),
            $pago,
            $orden->id
        );

        if (!$autoAprueba) {
            NotificacionService::abonoPendienteAprobacion($pago, $orden);
        }

        $ordenFresh = $orden->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Pago registrado.' . (!$autoAprueba ? ' Pendiente de aprobacion por Contabilidad.' : ''),
            'pago' => [
                'id' => $pago->id,
                'monto' => '$' . number_format($pago->monto, 0, '.', ','),
                'metodo_pago' => $pago->metodo_pago,
                'aprobado' => $pago->aprobado,
                'fecha' => $pago->created_at->format('d/m/Y H:i'),
                'registrado_por' => $user->name,
            ],
            'nuevo_saldo' => '$' . number_format($ordenFresh->saldo, 0, '.', ','),
            'nuevo_total_pagado' => '$' . number_format($ordenFresh->total_pagado, 0, '.', ','),
            'estado_pago' => $ordenFresh->estado_pago,
        ]);
    }

    /**
     * Aplica los filtros del listado de ordenes a la consulta base.
     * Compartido entre index() y los exportadores para que respeten los filtros activos.
     */
    protected function aplicarFiltrosListado(Request $request)
    {
        $query = Orden::with(['cliente', 'piezas'])->select('ordenes.*');

        if ($request->filled('numero_orden')) {
            $query->where('numero_orden', 'like', '%' . $request->input('numero_orden') . '%');
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->input('cliente') . '%');
            });
        }
        if ($request->filled('estado_trabajo')) {
            $estados = is_array($request->input('estado_trabajo'))
                ? $request->input('estado_trabajo')
                : [$request->input('estado_trabajo')];
            $query->whereIn('estado_trabajo', $estados);
        }
        if ($request->filled('estado_entrega')) {
            $query->where('estado_entrega', $request->input('estado_entrega'));
        }
        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->input('estado_pago'));
        }
        if ($request->filled('creado_por')) {
            $query->where('creado_por', $request->input('creado_por'));
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }
        if ($request->filled('busqueda')) {
            $term = $request->input('busqueda');
            $query->where(function ($q) use ($term) {
                $q->where('numero_orden', 'like', '%' . $term . '%')
                  ->orWhereHas('cliente', function ($cq) use ($term) {
                      $cq->where('nombre', 'like', '%' . $term . '%');
                  });
            });
        }

        return $query;
    }

    /**
     * GET /recepcion/ordenes/export-excel
     */
    public function exportExcel(Request $request)
    {
        $ordenes = $this->aplicarFiltrosListado($request)->with('items')->orderBy('id', 'desc')->get();
        return Excel::download(new OrdenesExport($ordenes), 'ordenes-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * GET /recepcion/ordenes/export-pdf
     */
    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M'); // PDF puede ser pesado con muchas ordenes

        $query = $this->aplicarFiltrosListado($request);
        // Si no se filtro explicitamente por estado_trabajo, excluir anuladas (comportamiento previo)
        if (!$request->filled('estado_trabajo')) {
            $query->where('estado_trabajo', '!=', 'anulada');
        }
        $ordenes = $query->orderBy('id', 'desc')->get();
        $fecha = now()->timezone('America/Bogota')->format('d/m/Y H:i');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body { font-family: sans-serif; font-size: 10px; color: #1f2937; }
            h1 { color: #4A7C59; font-size: 16px; margin-bottom: 2px; }
            .fecha { color: #6b7280; font-size: 9px; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #4A7C59; color: white; padding: 6px 4px; text-align: left; font-size: 9px; }
            td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
            tr:nth-child(even) td { background: #f9fafb; }
            .text-end { text-align: right; }
            .footer { margin-top: 20px; font-size: 8px; color: #9ca3af; text-align: center; }
        </style></head><body>';
        $html .= '<h1>Listado de Ordenes de Trabajo</h1>';
        $html .= '<p class="fecha">Generado: ' . $fecha . ' | Total: ' . $ordenes->count() . ' ordenes</p>';
        $html .= '<table><thead><tr>';
        $html .= '<th>Orden</th><th>Cliente</th><th>Creacion</th><th>Entrega</th><th>Estado</th><th class="text-end">Total</th><th class="text-end">Saldo</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($ordenes as $o) {
            $estado = strtoupper(str_replace('_', ' ', $o->estado_trabajo));
            $html .= '<tr>';
            $html .= '<td>' . e($o->numero_orden ?? 'Borrador') . '</td>';
            $html .= '<td>' . e($o->cliente->nombre ?? '-') . '</td>';
            $html .= '<td>' . ($o->created_at ? $o->created_at->format('d/m/Y') : '-') . '</td>';
            $html .= '<td>' . ($o->fecha_entrega ? $o->fecha_entrega->format('d/m/Y') : '-') . '</td>';
            $html .= '<td>' . $estado . '</td>';
            $html .= '<td class="text-end">$' . number_format($o->total, 0, '.', ',') . '</td>';
            $html .= '<td class="text-end">$' . number_format($o->saldo, 0, '.', ',') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">SINDEN S.A.S. - ' . now()->year . '</div>';
        $html .= '</body></html>';

        return Pdf::loadHTML($html)
            ->setPaper('letter', 'landscape')
            ->download('ordenes-' . now()->format('Y-m-d') . '.pdf');
    }

    // === Helpers de Badges ===

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

    /**
     * POST /recepcion/ordenes/{orden}/documentos - Subir documento adjunto (cualquier tipo).
     */
    public function subirDocumento(Request $request, Orden $orden)
    {
        $request->validate([
            'archivo' => 'required|file|max:51200',
        ], [
            'archivo.required' => 'Debe seleccionar un archivo.',
            'archivo.file' => 'El archivo es invalido.',
            'archivo.max' => 'El archivo no puede superar 50 MB. Tamano maximo permitido: 50 MB.',
            'archivo.uploaded' => 'El archivo supera el tamano maximo permitido (50 MB). Reduzca el tamano e intente de nuevo.',
        ]);

        $file = $request->file('archivo');
        $dir = public_path("uploads/ordenes/{$orden->id}/documentos");
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = $file->getClientOriginalExtension();
        $nombreOriginal = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $tamanoBytes = $file->getSize();
        $nombreGuardado = 'doc_' . time() . '_' . Str::random(6) . ($ext ? ".{$ext}" : '');
        $file->move($dir, $nombreGuardado);

        $doc = OrdenDocumento::create([
            'orden_id'        => $orden->id,
            'nombre_original' => $nombreOriginal,
            'ruta_archivo'    => "uploads/ordenes/{$orden->id}/documentos/{$nombreGuardado}",
            'extension'       => $ext,
            'mime_type'       => $mimeType,
            'tamano_bytes'    => $tamanoBytes,
            'subido_por'      => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'documento' => [
                'id'              => $doc->id,
                'nombre_original' => $doc->nombre_original,
                'tamano_legible'  => $doc->tamano_legible,
                'icono'           => $doc->icono,
                'url_descarga'    => $doc->url_descarga,
                'url_eliminar'    => route('recepcion.ordenes.documentos.eliminar', ['orden' => $orden->id, 'documento' => $doc->id]),
                'subido_por'      => auth()->user()->name,
                'created_at'      => optional($doc->created_at)->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * GET /recepcion/ordenes/{orden}/documentos/{documento}/descargar - Descargar adjunto.
     */
    public function descargarDocumento(Orden $orden, OrdenDocumento $documento)
    {
        abort_if($documento->orden_id !== $orden->id, 404);
        $path = public_path($documento->ruta_archivo);
        abort_if(!file_exists($path), 404);
        return response()->download($path, $documento->nombre_original);
    }

    /**
     * DELETE /recepcion/ordenes/{orden}/documentos/{documento} - Eliminar adjunto.
     */
    public function eliminarDocumento(Orden $orden, OrdenDocumento $documento)
    {
        abort_if($documento->orden_id !== $orden->id, 404);
        $path = public_path($documento->ruta_archivo);
        if (file_exists($path)) {
            @unlink($path);
        }
        $documento->delete();
        return response()->json(['success' => true]);
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

    protected function badgePorcentajeTotal(Orden $orden): string
    {
        $pct = $orden->porcentaje_total;
        if ($pct === null) {
            return '<span class="text-muted">&mdash;</span>';
        }
        if ($pct < 40) {
            $color = 'bg-danger';
        } elseif ($pct < 80) {
            $color = 'bg-warning';
        } else {
            $color = 'bg-success';
        }
        $label = $pct . '%';
        return '<div class="d-flex align-items-center gap-2">'
            . '<div class="progress flex-grow-1" style="height: 8px; min-width: 70px;">'
            . '<div class="progress-bar ' . $color . '" role="progressbar" style="width: ' . $pct . '%;" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100"></div>'
            . '</div>'
            . '<span class="small fw-semibold" style="min-width: 32px;">' . $label . '</span>'
            . '</div>';
    }
}
