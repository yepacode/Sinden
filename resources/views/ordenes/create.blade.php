@extends('layouts.app')

@section('content')
<div class="container-fluid py-4" id="ordenWizardApp">

    {{-- Sticky Wizard Header --}}
    <div class="wizard-header-sticky" id="wizardHeaderSticky">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="page-title mb-0" style="white-space: nowrap;">Crear Orden</h1>
            <div class="wizard-steps mb-0">
                <div class="wizard-step active" data-step="1" onclick="irASeccion(1)">
                    <i class="bi bi-person"></i> <span class="d-none d-md-inline">Cliente</span>
                </div>
                <div class="wizard-step" data-step="2" onclick="irASeccion(2)">
                    <i class="bi bi-puzzle"></i> <span class="d-none d-md-inline">Piezas</span>
                </div>
                <div class="wizard-step" data-step="3" onclick="irASeccion(3)">
                    <i class="bi bi-cart3"></i> <span class="d-none d-md-inline">Items (Productos y servicios)</span>
                </div>
                <div class="wizard-step" data-step="4" onclick="irASeccion(4)">
                    <i class="bi bi-pen"></i> <span class="d-none d-md-inline">Firma</span>
                </div>
                <div class="wizard-step" data-step="5" onclick="irASeccion(5)">
                    <i class="bi bi-cash-coin"></i> <span class="d-none d-md-inline">Pagos</span>
                </div>
                <div class="wizard-step" data-step="6" onclick="irASeccion(6)">
                    <i class="bi bi-calendar3"></i> <span class="d-none d-md-inline">Fechas</span>
                </div>
            </div>
            <span id="autoguardadoIndicator" style="display:none;"><span id="autoguardadoTexto"></span></span>
        </div>
    </div>

    {{-- Hidden order ID --}}
    <input type="hidden" id="orden_id" value="">

    {{-- 7 Secciones --}}
    @include('ordenes.partials._seccion-cliente')
    @include('ordenes.partials._seccion-bosquejos-piezas')
    @include('ordenes.partials._seccion-items')
    @include('ordenes.partials._seccion-firma')
    @include('ordenes.partials._seccion-pagos')
    @include('ordenes.partials._seccion-fechas')
    @include('ordenes.partials._seccion-documentos')

    {{-- Botones de accion --}}
    <div class="d-flex justify-content-end gap-2 mb-4">
        <button type="button" class="btn btn-outline-primary btn-lg" id="btnGuardar" onclick="guardarOrden(false)">
            <i class="bi bi-save me-1"></i> Guardar Orden
        </button>
        <button type="button" class="btn btn-primary btn-lg" id="btnGenerar" onclick="generarOrden()">
            <i class="bi bi-check-circle me-1"></i> Generar Orden
        </button>
    </div>

</div>

{{-- ====================================== --}}
{{-- MODALES --}}
{{-- ====================================== --}}

{{-- Modal: Crear Cliente Inline --}}
<div class="modal fade" id="modalNuevoCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold"><i class="bi bi-person-plus me-2 text-primary"></i>Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-medium">Nombre <span class="text-danger">*</span></label>
                        <input type="text" id="nuevoClienteNombre" class="form-control" placeholder="Nombre completo del cliente">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Celular Principal</label>
                        <input type="text" id="nuevoClienteCelular1" class="form-control" placeholder="Ej: 3001234567">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Celular Secundario</label>
                        <input type="text" id="nuevoClienteCelular2" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Correo</label>
                        <input type="email" id="nuevoClienteCorreo" class="form-control" placeholder="correo@ejemplo.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Direccion</label>
                        <input type="text" id="nuevoClienteDireccion" class="form-control" placeholder="Direccion del cliente">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="crearClienteInline()">
                    <i class="bi bi-check-lg me-1"></i> Crear y Seleccionar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Seleccionar Bosquejo de Matriz --}}
<div class="modal fade" id="modalBosquejoMatriz" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold"><i class="bi bi-grid-3x3 me-2 text-primary"></i>Seleccionar de Matriz de Bosquejos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3" id="matrizBosquejosContent">
                @forelse($gruposBosquejos as $grupo)
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-semibold mb-0">
                                <i class="bi bi-folder me-1 text-warning"></i> {{ $grupo->nombre }}
                                <span class="badge bg-light text-muted border ms-1">{{ $grupo->plantillas->count() }}</span>
                            </h6>
                            @if($grupo->plantillas->count() > 0)
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="insertarGrupoCompleto({{ $grupo->id }})">
                                    <i class="bi bi-folder-plus me-1"></i> Insertar Grupo ({{ $grupo->plantillas->count() }} piezas)
                                </button>
                            @endif
                        </div>
                        <div class="row g-2">
                            @foreach($grupo->plantillas as $plantilla)
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <div class="card border cursor-pointer plantilla-card"
                                         onclick="seleccionarPlantillaMatriz({{ $plantilla->id }}, '{{ addslashes($plantilla->nombre) }}', '{{ $plantilla->ruta_archivo }}', '{{ $plantilla->ruta_miniatura ?: $plantilla->ruta_archivo }}')">
                                        <img src="{{ asset($plantilla->ruta_miniatura ?: $plantilla->ruta_archivo) }}"
                                             class="card-img-top" alt="{{ $plantilla->nombre }}"
                                             style="aspect-ratio:1/1; width:100%; height:auto; object-fit:contain; background:#f8f9fa; padding:6px;">
                                        <div class="card-body p-2 text-center">
                                            <small class="text-truncate d-block">{{ $plantilla->nombre }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                @endforelse

                @if(isset($bosquejosSueltos) && $bosquejosSueltos->count() > 0)
                    <div class="mb-3">
                        <h6 class="fw-semibold mb-2">
                            <i class="bi bi-image me-1 text-info"></i> {{ \App\Models\ConfiguracionSistema::get('nombre_bosquejos_genericos', 'Genericos') }}
                            <span class="badge bg-light text-muted border ms-1">{{ $bosquejosSueltos->count() }}</span>
                        </h6>
                        <div class="row g-2">
                            @foreach($bosquejosSueltos as $plantilla)
                                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                    <div class="card border cursor-pointer plantilla-card"
                                         onclick="seleccionarPlantillaMatriz({{ $plantilla->id }}, '{{ addslashes($plantilla->nombre) }}', '{{ $plantilla->ruta_archivo }}', '{{ $plantilla->ruta_miniatura ?: $plantilla->ruta_archivo }}')">
                                        <img src="{{ asset($plantilla->ruta_miniatura ?: $plantilla->ruta_archivo) }}"
                                             class="card-img-top" alt="{{ $plantilla->nombre }}"
                                             style="aspect-ratio:1/1; width:100%; height:auto; object-fit:contain; background:#f8f9fa; padding:6px;">
                                        <div class="card-body p-2 text-center">
                                            <small class="text-truncate d-block">{{ $plantilla->nombre }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($gruposBosquejos->count() === 0 && (!isset($bosquejosSueltos) || $bosquejosSueltos->count() === 0))
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                        <p>No hay bosquejos en la matriz. Agregue primero desde el modulo Bosquejos Matriz.</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Dibujo Tablet (Fabric.js Profesional) --}}
<div class="modal fade" id="modalDibujoTablet" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl">
        <div class="modal-content" style="height:100%; display:flex; flex-direction:column;">
            {{-- Toolbar --}}
            <div class="modal-header border-0 py-1 px-2" style="min-height:auto;">
                <div class="d-flex gap-1 flex-wrap align-items-center w-100" id="dibujoToolbar">
                    {{-- Herramientas de dibujo --}}
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary dibujo-tool active" data-tool="pencil" title="Dibujo libre"><i class="bi bi-pencil-fill"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="line" title="Linea"><i class="bi bi-slash-lg"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="rect" title="Rectangulo"><i class="bi bi-square"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="ellipse" title="Elipse"><i class="bi bi-circle"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="arrow" title="Flecha"><i class="bi bi-arrow-up-right"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="text" title="Texto/Medidas"><i class="bi bi-fonts"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="select" title="Seleccionar/Mover"><i class="bi bi-cursor"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="eraser" title="Borrador (eliminar objeto)"><i class="bi bi-eraser-fill"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="white-brush" title="Borrador blanco (borrar fondo)"><i class="bi bi-eraser"></i></button>
                        <button type="button" class="btn btn-outline-secondary dibujo-tool" data-tool="pan" title="Mover lienzo"><i class="bi bi-arrows-move"></i></button>
                    </div>
                    <span class="vr mx-1"></span>
                    {{-- Colores --}}
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-dark dibujo-color active" data-color="#000000" title="Negro" style="min-width:30px;">&nbsp;</button>
                        <button type="button" class="btn btn-danger dibujo-color" data-color="#dc3545" title="Rojo" style="min-width:30px;">&nbsp;</button>
                        <button type="button" class="btn btn-primary dibujo-color" data-color="#0d6efd" title="Azul" style="min-width:30px;">&nbsp;</button>
                        <button type="button" class="btn dibujo-color" data-color="#198754" title="Verde" style="min-width:30px;background-color:#198754;color:white;">&nbsp;</button>
                    </div>
                    <span class="vr mx-1"></span>
                    {{-- Grosor --}}
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary dibujo-width" data-width="1" title="Ultra Fino">
                            <span style="display:inline-block;width:14px;height:1px;background:currentColor;vertical-align:middle;"></span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary dibujo-width active" data-width="3" title="Fino">
                            <span style="display:inline-block;width:14px;height:2px;background:currentColor;vertical-align:middle;"></span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary dibujo-width" data-width="6" title="Medio">
                            <span style="display:inline-block;width:14px;height:4px;background:currentColor;vertical-align:middle;"></span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary dibujo-width" data-width="10" title="Grueso">
                            <span style="display:inline-block;width:14px;height:6px;background:currentColor;vertical-align:middle;"></span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary dibujo-width" data-width="20" title="Ultra Grueso">
                            <span style="display:inline-block;width:14px;height:10px;background:currentColor;vertical-align:middle;"></span>
                        </button>
                    </div>
                    {{-- Tamano de texto (visible solo con herramienta texto) --}}
                    <span class="vr mx-1 dibujo-text-size-group d-none"></span>
                    <div class="btn-group btn-group-sm dibujo-text-size-group d-none" role="group" title="Tamano de texto">
                        <button type="button" class="btn btn-outline-secondary dibujo-fontsize" data-fontsize="14" title="Pequeno" style="font-size:10px;font-weight:bold;">A</button>
                        <button type="button" class="btn btn-outline-secondary dibujo-fontsize" data-fontsize="20" title="Mediano" style="font-size:13px;font-weight:bold;">A</button>
                        <button type="button" class="btn btn-outline-secondary dibujo-fontsize active" data-fontsize="28" title="Grande" style="font-size:16px;font-weight:bold;">A</button>
                        <button type="button" class="btn btn-outline-secondary dibujo-fontsize" data-fontsize="40" title="Muy Grande" style="font-size:20px;font-weight:bold;">A</button>
                        <button type="button" class="btn btn-outline-secondary dibujo-fontsize" data-fontsize="60" title="Enorme" style="font-size:24px;font-weight:bold;">A</button>
                    </div>
                    <span class="vr mx-1"></span>
                    {{-- Acciones --}}
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-warning" onclick="deshacerDibujo()" title="Deshacer (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button type="button" class="btn btn-outline-info" onclick="rehacerDibujo()" title="Rehacer (Ctrl+Y)"><i class="bi bi-arrow-clockwise"></i></button>
                        <button type="button" class="btn btn-outline-danger" onclick="limpiarDibujo()" title="Limpiar todo"><i class="bi bi-trash"></i></button>
                    </div>
                    <span class="vr mx-1"></span>
                    {{-- Zoom --}}
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="zoomDibujo(1)" title="Zoom +"><i class="bi bi-zoom-in"></i></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="zoomDibujo(-1)" title="Zoom -"><i class="bi bi-zoom-out"></i></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="zoomDibujo(0)" title="Ajustar 100%"><i class="bi bi-fullscreen"></i></button>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
                </div>
            </div>
            {{-- Canvas --}}
            <div class="modal-body py-0 px-0 overflow-hidden" id="dibujoCanvasWrapper" style="flex:1; min-height:0; touch-action:none; position:relative; background-color:#e0e0e0; display:flex; align-items:center; justify-content:center;">
                <canvas id="dibujoCanvas"></canvas>
            </div>
            {{-- Footer --}}
            <div class="modal-footer border-0 py-2">
                <span class="text-muted small me-auto" id="dibujoZoomLabel">100%</span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <span class="badge bg-warning text-dark d-inline-flex align-items-center" style="white-space: normal; max-width: 260px;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Si no guarda, se perderan los cambios
                </span>
                <button type="button" class="btn btn-primary" id="btnGuardarDibujo" onclick="guardarDibujoComoImagen()">
                    <i class="bi bi-save me-1"></i> Guardar Dibujo
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal: Camara para Piezas --}}
<div class="modal fade" id="modalCamaraPieza" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 py-2 px-3">
                <h6 class="modal-title fw-semibold"><i class="bi bi-camera me-2 text-primary"></i>Tomar Foto</h6>
                <button type="button" class="btn-close" onclick="camaraPiezaCerrar()"></button>
            </div>
            <div class="modal-body text-center py-2">
                <video id="camaraPiezaVideo" autoplay playsinline class="img-fluid rounded" style="max-height: 400px;"></video>
                <canvas id="camaraPiezaCanvas" style="display: none;"></canvas>
            </div>
            <div class="modal-footer border-0 py-2">
                <button type="button" class="btn btn-secondary" onclick="camaraPiezaCerrar()">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="camaraPiezaCapturar()">
                    <i class="bi bi-camera-fill me-1"></i>Capturar
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .plantilla-card { transition: transform 0.15s, box-shadow 0.15s; }
    .plantilla-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .cursor-pointer { cursor: pointer; }
    .border-dashed { border-style: dashed !important; }
</style>
@endpush

@push('scripts')
<script>
// Datos del servidor para JS
var WIZARD_CONFIG = {
    materiales: @json($materiales),
    calibres: @json($calibres),
    operarios: @json($operarios),
    ivaDefecto: {{ $ivaDefecto }},
    autoSaveInterval: {{ $autoSaveInterval }},
    csrfToken: '{{ csrf_token() }}'
};
window.TIPOS_PAGO = @json(($tiposPago ?? collect())->map(fn($t) => ['codigo' => $t->codigo, 'nombre' => $t->nombre]));
window.TIPOS_PAGO_MAPA = @json($tiposPagoMapa ?? []);

var ROUTES = {
    guardar: '{{ route("recepcion.ordenes.guardar") }}',
    generar: '{{ route("recepcion.ordenes.generar") }}',
    subirBosquejo: '{{ route("recepcion.ordenes.subir-bosquejo") }}',
    crearCliente: '{{ route("recepcion.ordenes.crear-cliente-inline") }}',
    clienteAutocomplete: '{{ route("recepcion.clientes.autocomplete") }}',
    itemAutocomplete: '{{ route("recepcion.items.autocomplete") }}',
    panel: '{{ route("recepcion.panel") }}'
};
</script>
<script src="{{ asset('js/firma-canvas.js') }}"></script>
<script src="{{ asset('js/dibujo-canvas.js') }}"></script>
<script src="{{ asset('js/orden-wizard.js') }}?v={{ filemtime(public_path('js/orden-wizard.js')) }}"></script>
@endpush
