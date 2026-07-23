@extends('layouts.app')

@section('title', 'Orden ' . ($orden->numero_orden ?? 'Borrador #' . $orden->id))

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-sinden.page-header :title="'Orden ' . ($orden->numero_orden ?? 'Borrador #' . $orden->id)" description="Detalle completo de la orden">
        <x-slot name="actions">
            @php
                $previousUrl = url()->previous();
                $currentUrl  = url()->current();
                $appBase     = url('/');
                $backUrl = (
                    $previousUrl
                    && $previousUrl !== $currentUrl
                    && str_starts_with($previousUrl, $appBase)
                ) ? $previousUrl : route('dashboard');
            @endphp
            <x-sinden.button variant="outline" icon="bi bi-arrow-left"
                href="{{ $backUrl }}">Volver</x-sinden.button>
            @hasanyrole('Administrador|Recepcion|Contabilidad')
            @if($orden->estado_trabajo !== 'anulada')
                <x-sinden.button variant="primary" icon="bi bi-pencil"
                    href="{{ route('recepcion.ordenes.edit', $orden) }}">Editar</x-sinden.button>
            @endif
            @endhasanyrole
            @hasanyrole('Administrador|Recepcion')
            @if($orden->estado_trabajo !== 'anulada')
                <button type="button" class="btn btn-outline-secondary" onclick="copiarOrden()">
                    <i class="bi bi-copy me-1"></i>Copiar
                </button>
            @endif
            @endhasanyrole
            @if($orden->estado_trabajo !== 'anulada')
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDescargarPdf">
                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
            @endif
            @if(!in_array($orden->estado_trabajo, ['anulada', 'borrador']))
                @hasanyrole('Administrador|Recepcion')
                <button type="button" class="btn btn-outline-danger" onclick="$('#modalAnularOrden').modal('show')">
                    <i class="bi bi-x-circle me-1"></i>Anular
                </button>
                @endhasanyrole
            @endif
            @if($orden->estado_trabajo === 'borrador')
                @hasanyrole('Administrador|Recepcion')
                <button type="button" class="btn btn-outline-danger" onclick="eliminarBorrador()">
                    <i class="bi bi-trash me-1"></i>Borrar
                </button>
                @endhasanyrole
            @endif
        </x-slot>
    </x-sinden.page-header>

    {{-- Seccion 1: Encabezado + Estados --}}
    @include('ordenes.show._seccion-encabezado')

    <div class="row mt-4">
        <div class="col-lg-8">
            {{-- Seccion 2: Cliente --}}
            @include('ordenes.show._seccion-cliente')

            {{-- Seccion 3: Fechas --}}
            @include('ordenes.show._seccion-fechas')

            {{-- Seccion 4: Items + Totales --}}
            @include('ordenes.show._seccion-items')

            {{-- Seccion 5: Piezas (con bosquejos integrados) --}}
            @include('ordenes.show._seccion-piezas')
        </div>

        <div class="col-lg-4">
            {{-- Seccion 7: Pagos --}}
            @include('ordenes.show._seccion-pagos')

            {{-- Seccion 8: Firma --}}
            @include('ordenes.show._seccion-firma')

            {{-- Seccion 9: Fotos --}}
            @include('ordenes.show._seccion-fotos')

            {{-- Seccion 9a: Documentos adjuntos --}}
            @include('ordenes.show._seccion-documentos')

            {{-- Seccion 9b: Entregas --}}
            @include('ordenes.show._seccion-entregas')

            {{-- Seccion 10: Comentarios --}}
            @include('ordenes.show._seccion-comentarios')

            {{-- Seccion 11: Garantias --}}
            @include('ordenes.show._seccion-garantias')
        </div>
    </div>
</div>

{{-- Modal Agregar Pago --}}
<div class="modal fade" id="modalAgregarPago" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-medium">Monto <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="pagoMonto" min="1" step="1" max="{{ $orden->montoDisponibleNuevoPago() }}">
                    </div>
                    <small class="text-muted">Maximo permitido: ${{ number_format($orden->montoDisponibleNuevoPago(), 0, ',', '.') }}</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Metodo de Pago</label>
                    <select class="form-select" id="pagoMetodo">
                        @foreach(($tiposPago ?? collect()) as $tp)
                            <option value="{{ $tp->codigo }}">{{ $tp->codigo }} - {{ $tp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Referencia</label>
                    <input type="text" class="form-control" id="pagoReferencia" placeholder="No. de referencia (opcional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnRegistrarPago" onclick="registrarPago()">
                    <i class="bi bi-check-lg me-1"></i>Registrar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Anular --}}
{{-- Modal Descargar PDF --}}
<div class="modal fade" id="modalDescargarPdf" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-pdf me-2 text-primary"></i>Descargar PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-medium" for="selectBosquejosCols">Bosquejos por fila</label>
                <select class="form-select" id="selectBosquejosCols">
                    <option value="1">1 por fila</option>
                    <option value="2" selected>2 por fila (defecto)</option>
                    <option value="3">3 por fila</option>
                    <option value="4">4 por fila</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a class="btn btn-primary" id="btnDescargarPdf"
                   href="{{ route('recepcion.ordenes.pdf', [$orden, 'bosquejos_cols' => 2]) }}">
                    <i class="bi bi-download me-1"></i>Descargar
                </a>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        var select = document.getElementById('selectBosquejosCols');
        var btn = document.getElementById('btnDescargarPdf');
        var base = "{{ route('recepcion.ordenes.pdf', $orden) }}";
        if (select && btn) {
            select.addEventListener('change', function () {
                btn.href = base + '?bosquejos_cols=' + encodeURIComponent(this.value);
            });
        }
    })();
</script>

<div class="modal fade" id="modalAnularOrden" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Anular Orden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Esta accion anulara la orden y liberara todas las asignaciones de piezas.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Motivo de Anulacion <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motivoAnulacion" rows="3" placeholder="Ingrese el motivo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAnular" onclick="confirmarAnulacion()">
                    <i class="bi bi-x-circle me-1"></i>Anular Orden
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Registrar Garantia --}}
@hasanyrole('Administrador|Recepcion')
<div class="modal fade" id="modalRegistrarGarantia" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" x-data="{ cobrable: false }">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-shield-plus me-2"></i>Registrar Garantia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="garantiaLoading" class="text-center py-3 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 small">Cargando piezas...</p>
                </div>
                <div id="garantiaForm">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Pieza <span class="text-danger">*</span></label>
                        <select class="form-select" id="garantiaPiezaId">
                            <option value="">Seleccione una pieza...</option>
                        </select>
                        <small class="text-muted" id="garantiaPiezaInfo"></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Cantidad Devuelta <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="garantiaCantidad" min="1" value="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Motivo <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="garantiaMotivo" rows="3" placeholder="Describa el motivo de la devolucion..."></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="garantiaCobrable"
                                   x-model="cobrable">
                            <label class="form-check-label" for="garantiaCobrable">Cobrable al cliente</label>
                        </div>
                    </div>
                    <div class="mb-3" x-show="cobrable" x-cloak>
                        <label class="form-label fw-medium">Monto a Cobrar</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="garantiaMontoCobro" min="0" step="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Operario Asignado <span class="text-muted">(opcional)</span></label>
                        <select class="form-select" id="garantiaOperarioId">
                            <option value="">Sin asignar por ahora</option>
                        </select>
                        <small class="text-muted">Si asigna operario, la garantia pasara directamente a "En Proceso".</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="registrarGarantia()">
                    <i class="bi bi-shield-plus me-1"></i>Registrar
                </button>
            </div>
        </div>
    </div>
</div>
@endhasanyrole

{{-- Lightbox --}}
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-white" id="lightboxTitle"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body d-flex align-items-center justify-content-center p-2" style="position:relative;overflow:auto;">
                <img id="lightboxImage" src="" class="img-fluid" style="max-height:calc(100vh - 90px);">
                {{-- Boton grande de cerrar al centro-derecha (facil de tocar en tablet) --}}
                <button type="button" class="btn-cerrar-bosquejo btn-cerrar-bosquejo--centro-derecha" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/orden-detalle.js') }}"></script>
<script>
var ORDEN_ID = {{ $orden->id }};
var CSRF_TOKEN = '{{ csrf_token() }}';
var ORDEN_SALDO_DISPONIBLE = {{ $orden->montoDisponibleNuevoPago() }};
@php
    $esContabilidad = request()->is('contabilidad/*');
@endphp
var ROUTES_DETALLE = {
    copiar: '{{ route("recepcion.ordenes.copiar", $orden) }}',
    anular: '{{ route("recepcion.ordenes.anular", $orden) }}',
    destroy: '{{ route("recepcion.ordenes.destroy", $orden) }}',
    comentarios: '{{ route("recepcion.ordenes.comentarios.store", $orden) }}',
    pagos: '{{ $esContabilidad ? route("contabilidad.ordenes.pagos.store", $orden) : route("recepcion.ordenes.pagos.store", $orden) }}',
    index: '{{ route("recepcion.ordenes.index") }}',
    edit: '{{ route("recepcion.ordenes.edit", $orden) }}',
    @hasanyrole('Administrador|Recepcion')
    garantiasPiezas: '{{ route("recepcion.garantias.piezas-entregadas", $orden) }}',
    garantiasStore: '{{ route("recepcion.garantias.store", $orden) }}',
    garantiasCambiarEstado: '{{ url("recepcion/garantias") }}',
    garantiasAsignarOperario: '{{ url("recepcion/garantias") }}',
    operarios: '{{ route("recepcion.ordenes.operarios") }}',
    @endhasanyrole
};

// Historial de entregas por pieza
$(document).on('click', '.btn-historial-entregas', function(e) {
    e.preventDefault();
    var piezaId = $(this).data('pieza-id');
    var piezaNombre = $(this).data('pieza-nombre');
    $('#modalEntregaPiezaNombre').text(piezaNombre);
    $('#modalEntregaLoading').removeClass('d-none');
    $('#modalEntregaContenido').addClass('d-none');
    $('#modalEntregaVacio').addClass('d-none');
    $('#modalEntregaResumen').text('');
    $('#modalEntregaTabla').empty();
    $('#modalHistorialEntregas').modal('show');

    $.ajax({
        url: '{{ url("recepcion/entregas-pendientes/pieza") }}/' + piezaId + '/historial',
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        success: function(data) {
            $('#modalEntregaLoading').addClass('d-none');
            $('#modalEntregaResumen').text(data.pieza.cantidad_entregada + ' de ' + data.pieza.cantidad + ' entregadas');

            if (data.entregas.length === 0) {
                $('#modalEntregaVacio').removeClass('d-none');
                return;
            }

            var html = '';
            data.entregas.forEach(function(ent) {
                html += '<tr>';
                html += '<td>' + ent.fecha + '</td>';
                html += '<td class="text-center"><span class="badge bg-primary">' + ent.cantidad + '</span></td>';
                html += '<td>' + ent.entregado_por + '</td>';
                html += '<td>';
                if (ent.fotos && ent.fotos.length > 0) {
                    ent.fotos.forEach(function(f) {
                        html += '<img src="' + f.url + '" class="border rounded me-1" style="width:40px;height:40px;object-fit:cover;cursor:pointer;" onclick="abrirLightbox(\'' + f.url.replace('{{ url("/") }}/', '') + '\', \'Foto Entrega\')" title="Ver foto">';
                    });
                } else {
                    html += '<span class="text-muted small">-</span>';
                }
                html += '</td>';
                html += '</tr>';
            });
            $('#modalEntregaTabla').html(html);
            $('#modalEntregaContenido').removeClass('d-none');
        },
        error: function() {
            $('#modalEntregaLoading').addClass('d-none');
            $('#modalEntregaVacio').removeClass('d-none').text('Error al cargar el historial.');
        }
    });
});
</script>
@endpush
