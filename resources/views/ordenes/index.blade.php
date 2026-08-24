@extends('layouts.app')

@section('title', 'Ordenes de Trabajo')

@push('styles')
<style>
    /* Fijar columna Acciones (ultima) para que siempre sea visible */
    #ordenesTable thead th:last-child,
    #ordenesTable tbody td:last-child {
        position: sticky;
        right: 0;
        z-index: 2;
        background-color: #fff;
        box-shadow: -4px 0 6px -4px rgba(0, 0, 0, 0.1);
    }
    #ordenesTable thead th:last-child {
        background-color: #f8f9fa;
        z-index: 3;
    }
    #ordenesTable tbody tr:hover td:last-child {
        background-color: #f5f5f5;
    }

    [data-theme="dark"] #ordenesTable thead th:last-child,
    [data-theme="dark"] #ordenesTable tbody td:last-child {
        background-color: #1e1e1e;
        box-shadow: -4px 0 6px -4px rgba(0, 0, 0, 0.5);
    }
    [data-theme="dark"] #ordenesTable thead th:last-child {
        background-color: #2a2a2a;
    }
    [data-theme="dark"] #ordenesTable tbody tr:hover td:last-child {
        background-color: #2a2a2a;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-sinden.page-header title="Ordenes de Trabajo" description="Buscar y gestionar ordenes">
        <x-slot name="actions">
            {{-- Excel disponible para todos los roles --}}
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="#" onclick="exportarListado('excel'); return false;">Excel</x-sinden.button>
            @hasanyrole('Administrador|Recepcion|Contabilidad')
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-pdf"
                href="#" onclick="exportarListado('pdf'); return false;">PDF</x-sinden.button>
            <div class="dropdown d-inline-block">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-files me-1"></i>PDF Masivo
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="pdfMasivoUnido(); return false;"><i class="bi bi-file-earmark-pdf me-2"></i>PDF Unido</a></li>
                    <li><a class="dropdown-item" href="#" onclick="pdfMasivoZip(); return false;"><i class="bi bi-file-zip me-2"></i>PDF en ZIP</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="dropdown-header">Bosquejos por fila</li>
                    <li>
                        <div class="px-3 py-1">
                            <select class="form-select form-select-sm" id="pdfMasivoCols">
                                <option value="1">1 por fila</option>
                                <option value="2" selected>2 por fila</option>
                                <option value="3">3 por fila</option>
                                <option value="4">4 por fila</option>
                            </select>
                        </div>
                    </li>
                </ul>
            </div>
            @endhasanyrole
            @hasanyrole('Administrador|Recepcion')
            <x-sinden.button variant="primary" icon="bi bi-plus-lg"
                href="{{ route('recepcion.ordenes.crear') }}">Nueva Orden</x-sinden.button>
            @endhasanyrole
        </x-slot>
    </x-sinden.page-header>

    {{-- Summary Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-file-earmark-text" :value="$totalOrdenes" title="Total Ordenes" color="primary" />
        <x-sinden.stat-card icon="bi bi-file-earmark" :value="$borradores" title="Borradores" color="secondary" />
        <x-sinden.stat-card icon="bi bi-gear" :value="$enProceso" title="En Proceso" color="warning" />
        <x-sinden.stat-card icon="bi bi-check-circle" :value="$ejecutadas" title="Ejecutadas" color="success" />
        <x-sinden.stat-card icon="bi bi-currency-dollar" :value="'$' . number_format($saldoPendienteTotal, 0, '.', ',')" title="Saldo Pendiente" color="danger" />
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body px-4 py-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">N° Orden</label>
                    <input type="text" class="form-control form-control-sm" id="filtroNumeroOrden" placeholder="Ej: OT-00001">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Cliente</label>
                    <input type="text" class="form-control form-control-sm" id="filtroCliente" placeholder="Nombre del cliente">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Fecha Desde</label>
                    <input type="date" class="form-control form-control-sm" id="filtroFechaDesde">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Fecha Hasta</label>
                    <input type="date" class="form-control form-control-sm" id="filtroFechaHasta">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Estado Trabajo</label>
                    <select class="form-select form-select-sm" id="filtroEstadoTrabajo">
                        <option value="">Todos</option>
                        <option value="borrador">Borrador</option>
                        <option value="generada">Generada</option>
                        <option value="en_ejecucion">En Ejecucion</option>
                        <option value="ejecutada_parcialmente">Ejec. Parcial</option>
                        <option value="ejecutada">Ejecutada</option>
                        <option value="anulada">Anulada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Estado Entrega</label>
                    <select class="form-select form-select-sm" id="filtroEstadoEntrega">
                        <option value="">Todos</option>
                        <option value="entregada_parcialmente">Entrega Parcial</option>
                        <option value="entregada">Entregada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Estado Pago</label>
                    <select class="form-select form-select-sm" id="filtroEstadoPago">
                        <option value="">Todos</option>
                        <option value="saldo_pendiente">Saldo Pendiente</option>
                        <option value="pagado">Pagado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-medium mb-1">Creado Por</label>
                    <select class="form-select form-select-sm" id="filtroCreador">
                        <option value="">Todos</option>
                        @foreach($creadores as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary" id="btnFiltrar">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiar" title="Borrar filtros">
                        <i class="bi bi-x-lg me-1"></i>Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold text-dark">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Listado de Ordenes
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="ordenesTable" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 ms-1 text-muted align-baseline"
                                        data-bs-toggle="popover"
                                        data-bs-trigger="focus"
                                        data-bs-placement="right"
                                        data-bs-html="true"
                                        data-bs-title="¿Para qué sirve este check?"
                                        data-bs-content="Selecciona una o varias órdenes para generar <strong>PDF Masivo</strong> (unido o en ZIP) desde el botón superior derecho.<br><br><small class='text-muted'>El checkbox del encabezado selecciona todas las órdenes visibles. Las órdenes en estado <em>Borrador</em> o <em>Anulada</em> no pueden seleccionarse.</small>"
                                        title="Ver explicación">
                                    <i class="bi bi-question-circle"></i>
                                </button>
                            </th>
                            <th>Orden</th>
                            <th>Cliente</th>
                            <th>Estado Trabajo</th>
                            <th>Estado Entrega</th>
                            <th>Estado Pago</th>
                            <th class="text-center" style="width: 140px;">% Total</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Saldo</th>
                            <th>Creacion</th>
                            <th>Fecha Entrega</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Anular --}}
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
                    Esta accion anulara la orden <strong id="anularOrdenNumero"></strong> y liberara todas las asignaciones.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Motivo de Anulacion <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motivoAnulacion" rows="3" placeholder="Ingrese el motivo..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarAnular">
                    <i class="bi bi-x-circle me-1"></i>Anular Orden
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var CSRF_TOKEN = '{{ csrf_token() }}';
var anularOrdenId = null;

$(function() {
    if (window.bootstrap) {
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
            new bootstrap.Popover(el);
        });
    }

    var table = $('#ordenesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("recepcion.ordenes.index") }}',
            data: function(d) {
                d.numero_orden = $('#filtroNumeroOrden').val();
                d.cliente = $('#filtroCliente').val();
                d.estado_trabajo = $('#filtroEstadoTrabajo').val();
                d.estado_entrega = $('#filtroEstadoEntrega').val();
                d.estado_pago = $('#filtroEstadoPago').val();
                d.fecha_desde = $('#filtroFechaDesde').val();
                d.fecha_hasta = $('#filtroFechaHasta').val();
                d.creado_por = $('#filtroCreador').val();
            }
        },
        columns: [
            {
                data: 'id', name: 'id', orderable: false, searchable: false, width: '30px', className: 'text-center',
                render: function(data, type, row) {
                    if (row.estado_trabajo === 'borrador' || row.estado_trabajo === 'anulada') return '';
                    return '<input type="checkbox" class="form-check-input row-select" value="' + data + '">';
                }
            },
            { data: 'numero_orden', name: 'numero_orden', width: '90px', className: 'fw-semibold' },
            { data: 'cliente_nombre', name: 'cliente.nombre' },
            { data: 'estado_trabajo_badge', name: 'estado_trabajo', className: 'text-center', orderable: true, searchable: false },
            { data: 'estado_entrega_badge', name: 'estado_entrega', className: 'text-center', orderable: true, searchable: false },
            { data: 'estado_pago_badge', name: 'estado_pago', className: 'text-center', orderable: true, searchable: false },
            { data: 'porcentaje_total_html', name: 'porcentaje_total_html', className: 'text-center', orderable: false, searchable: false, width: '140px' },
            { data: 'total_formatted', name: 'total', className: 'text-end', orderable: true, searchable: false },
            { data: 'saldo_formatted', name: 'saldo', className: 'text-end', orderable: true, searchable: false },
            { data: 'created_at', name: 'created_at', width: '95px', className: 'text-center' },
            { data: 'fecha_entrega', name: 'fecha_entrega', width: '95px', className: 'text-center' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '160px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        order: [[9, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal || 0;
            $('#totalRegistros').text(total + ' registro' + (total !== 1 ? 's' : ''));
        }
    });

    $('#btnFiltrar').on('click', function() { table.ajax.reload(); });
    $('#btnLimpiar').on('click', function() {
        $('#filtroNumeroOrden, #filtroCliente, #filtroFechaDesde, #filtroFechaHasta').val('');
        $('#filtroEstadoTrabajo, #filtroEstadoEntrega, #filtroEstadoPago, #filtroCreador').val('');
        table.ajax.reload();
    });

    // Enter para filtrar
    $('#filtroNumeroOrden, #filtroCliente, #filtroFechaDesde, #filtroFechaHasta').on('keypress', function(e) {
        if (e.which === 13) { e.preventDefault(); table.ajax.reload(); }
    });
    $('#filtroEstadoTrabajo, #filtroEstadoEntrega, #filtroEstadoPago, #filtroCreador').on('change', function() {
        table.ajax.reload();
    });

    $('#btnConfirmarAnular').on('click', function() {
        var motivo = $('#motivoAnulacion').val().trim();
        if (!motivo) {
            Swal.fire('Error', 'Debe ingresar un motivo de anulacion.', 'error');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: '{{ url("recepcion/ordenes") }}/' + anularOrdenId + '/anular',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            data: { motivo: motivo },
            success: function(data) {
                $('#modalAnularOrden').modal('hide');
                if (data.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                    table.ajax.reload(null, false);
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error al anular la orden.';
                Swal.fire('Error', msg, 'error');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });
});

function exportarListado(tipo) {
    var dt = $('#ordenesTable').DataTable();
    var params = {
        numero_orden: $('#filtroNumeroOrden').val(),
        cliente: $('#filtroCliente').val(),
        estado_trabajo: $('#filtroEstadoTrabajo').val(),
        estado_entrega: $('#filtroEstadoEntrega').val(),
        estado_pago: $('#filtroEstadoPago').val(),
        fecha_desde: $('#filtroFechaDesde').val(),
        fecha_hasta: $('#filtroFechaHasta').val(),
        busqueda: dt.search()
    };
    var qs = $.param(Object.keys(params).reduce(function(acc, k) {
        if (params[k]) acc[k] = params[k];
        return acc;
    }, {}));
    var base = tipo === 'excel'
        ? '{{ route("recepcion.ordenes.export-excel") }}'
        : '{{ route("recepcion.ordenes.export-pdf") }}';
    window.location.href = base + (qs ? ('?' + qs) : '');
}

function copiarOrden(ordenId) {
    Swal.fire({
        title: 'Copiar orden?',
        text: 'Se creara un nuevo borrador con los mismos items, bosquejos y piezas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#475569',
        confirmButtonText: 'Si, copiar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Copiando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });
            $.ajax({
                url: '{{ url("recepcion/ordenes") }}/' + ordenId + '/copiar',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                success: function(data) {
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Orden copiada', text: data.message, confirmButtonColor: '#475569' }).then(function() {
                            window.location.href = data.redirect;
                        });
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error al copiar.';
                    Swal.fire('Error', msg, 'error');
                }
            });
        }
    });
}

function anularOrden(ordenId, numeroOrden) {
    anularOrdenId = ordenId;
    $('#anularOrdenNumero').text(numeroOrden || '#' + ordenId);
    $('#motivoAnulacion').val('');
    $('#modalAnularOrden').modal('show');
}

function eliminarBorrador(ordenId) {
    Swal.fire({
        title: 'Eliminar borrador?',
        html: 'Esta accion eliminara permanentemente el borrador <strong>#' + ordenId + '</strong> y todos sus items, bosquejos, piezas y archivos adjuntos.<br><br><span class="text-danger fw-semibold">No se puede deshacer.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Eliminando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

        $.ajax({
            url: '{{ url("recepcion/ordenes") }}/' + ordenId,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            success: function(data) {
                if (data.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                    $('#ordenesTable').DataTable().ajax.reload(null, false);
                } else {
                    Swal.fire('Error', data.message || 'No se pudo eliminar.', 'error');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error al eliminar el borrador.';
                Swal.fire('Error', msg, 'error');
            }
        });
    });
}

// Select All checkbox
$('#selectAll').on('change', function() {
    var checked = $(this).is(':checked');
    $('.row-select').prop('checked', checked);
});
$(document).on('change', '.row-select', function() {
    var total = $('.row-select').length;
    var selected = $('.row-select:checked').length;
    $('#selectAll').prop('checked', total > 0 && total === selected);
});

function getSelectedIds() {
    var ids = [];
    $('.row-select:checked').each(function() { ids.push($(this).val()); });
    return ids;
}

function pdfMasivoUnido() {
    var ids = getSelectedIds();
    if (ids.length === 0) {
        Swal.fire('Sin seleccion', 'Seleccione al menos una orden usando los checkboxes.', 'warning');
        return;
    }
    var cols = $('#pdfMasivoCols').val() || 2;
    window.open('{{ route("recepcion.ordenes.pdf-multiple") }}?ids=' + ids.join(',') + '&bosquejos_cols=' + cols, '_blank');
}

function pdfMasivoZip() {
    var ids = getSelectedIds();
    if (ids.length === 0) {
        Swal.fire('Sin seleccion', 'Seleccione al menos una orden usando los checkboxes.', 'warning');
        return;
    }
    var cols = $('#pdfMasivoCols').val() || 2;
    window.location.href = '{{ route("recepcion.ordenes.pdf-zip") }}?ids=' + ids.join(',') + '&bosquejos_cols=' + cols;
}
</script>
@endpush
