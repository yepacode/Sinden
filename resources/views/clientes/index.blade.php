@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-sinden.page-header title="Clientes" description="Gestion de clientes de la empresa">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="{{ route('recepcion.clientes.export-excel') }}">Excel</x-sinden.button>
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-pdf"
                href="{{ route('recepcion.clientes.export-pdf') }}">PDF</x-sinden.button>
            @can('crear_clientes')
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload me-1"></i>Importar Excel
            </button>
            @endcan
            <x-sinden.button variant="primary" icon="bi bi-plus-lg"
                href="{{ route('recepcion.clientes.create') }}">Nuevo Cliente</x-sinden.button>
        </x-slot>
    </x-sinden.page-header>

    {{-- Summary Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-people" :value="$totalClientes" title="Total Clientes" color="primary" />
        <x-sinden.stat-card icon="bi bi-person-check" :value="$clientesActivos" title="Activos" color="success" />
        <x-sinden.stat-card icon="bi bi-person-x" :value="$clientesInactivos" title="Inactivos" color="danger" />
        <x-sinden.stat-card icon="bi bi-person-plus" :value="$clientesRecientes" title="Ultimos 30 dias" color="info" />
    </div>

    {{-- DataTable --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold text-dark">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Listado de Clientes
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="clientesTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Cedula/NIT</th>
                            <th>Correo</th>
                            <th>Celular (WhatsApp)</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    {{-- Historial de Importaciones --}}
    @can('crear_clientes')
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold text-dark">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Historial de Importaciones
                </h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="cargarHistorialImport()">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div id="historialImportContainer">
                <p class="text-muted small">Cargando historial...</p>
            </div>
        </div>
    </div>
    @endcan
</div>

{{-- Modal de Importacion --}}
@can('crear_clientes')
<x-sinden.modal id="importModal" title="Importar Clientes desde Excel" size="lg">
    <div class="mb-3">
        <p class="text-muted mb-2">Suba un archivo Excel (.xlsx) con los clientes a importar. Si la cedula/NIT ya existe, ese cliente se actualiza; si no, se crea uno nuevo.</p>
        <a href="{{ route('recepcion.clientes.import-template') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Descargar Plantilla
        </a>
    </div>
    <hr>
    <form id="importForm" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="archivoImport" class="form-label fw-semibold">Archivo Excel</label>
            <input type="file" class="form-control" id="archivoImport" name="archivo" accept=".xlsx,.xls" required>
            <div class="form-text">Formatos aceptados: .xlsx, .xls. Maximo 5MB.</div>
        </div>
        <div id="importProgress" class="d-none">
            <div class="progress mb-2" style="height: 6px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 100%"></div>
            </div>
            <p class="text-muted small text-center mb-0"><i class="bi bi-hourglass-split me-1"></i>Procesando archivo, por favor espere...</p>
        </div>
        <div id="importResult" class="d-none mt-3"></div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-success" id="btnImportar" onclick="ejecutarImportacion()">
            <i class="bi bi-upload me-1"></i>Importar
        </button>
    </x-slot>
</x-sinden.modal>
@endcan
@endsection

@push('scripts')
<script>
$(function() {
    var table = $('#clientesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("recepcion.clientes.index") }}',
        columns: [
            { data: 'id', name: 'id', width: '55px', className: 'text-center text-muted' },
            { data: 'nombre', name: 'nombre', className: 'fw-semibold' },
            { data: 'cedula', name: 'cedula' },
            { data: 'correo', name: 'correo' },
            { data: 'celular_1', name: 'celular_1' },
            { data: 'estado', name: 'activo', orderable: true, searchable: false, className: 'text-center' },
            { data: 'created_at', name: 'created_at', width: '100px', className: 'text-center' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '120px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        order: [[0, 'desc']],
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
});

function toggleActivo(clienteId, nombre) {
    Swal.fire({
        title: 'Cambiar estado?',
        text: 'Desea cambiar el estado del cliente "' + nombre + '"?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#475569',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, cambiar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            fetch('{{ url("recepcion/clientes") }}/' + clienteId + '/toggle-activo', {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(r) {
                if (!r.ok) return r.json().then(function(d) { throw d; });
                return r.json();
            })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: data.message,
                        showConfirmButton: false,
                        timer: 3000
                    });
                    $('#clientesTable').DataTable().ajax.reload(null, false);
                }
            })
            .catch(function(err) {
                var msg = (err && err.message) ? err.message : 'No se pudo cambiar el estado';
                Swal.fire('Error', msg, 'error');
            });
        }
    });
}

@can('crear_clientes')
// ===== Importacion masiva de clientes =====
function ejecutarImportacion() {
    var fileInput = document.getElementById('archivoImport');
    if (!fileInput.files.length) {
        Swal.fire('Atencion', 'Debe seleccionar un archivo Excel.', 'warning');
        return;
    }

    var formData = new FormData();
    formData.append('archivo', fileInput.files[0]);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    var btn = document.getElementById('btnImportar');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Procesando...';
    document.getElementById('importProgress').classList.remove('d-none');
    document.getElementById('importResult').classList.add('d-none');

    fetch('{{ route("recepcion.clientes.import-excel") }}', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(r) { return r.json().then(function(d) { return {ok: r.ok, data: d}; }); })
    .then(function(res) {
        document.getElementById('importProgress').classList.add('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload me-1"></i>Importar';

        if (res.ok && res.data.success) {
            var d = res.data.data;
            var icon = d.errores > 0 ? 'warning' : 'success';
            var resultHtml = '<div class="alert alert-' + (d.errores > 0 ? 'warning' : 'success') + ' mb-0">';
            resultHtml += '<h6 class="alert-heading mb-2"><i class="bi bi-check-circle me-1"></i>Importacion Completada</h6>';
            resultHtml += '<div class="d-flex gap-3 flex-wrap">';
            resultHtml += '<span class="badge bg-success"><i class="bi bi-plus-circle me-1"></i>' + d.creados + ' creados</span>';
            resultHtml += '<span class="badge bg-primary"><i class="bi bi-pencil me-1"></i>' + d.actualizados + ' actualizados</span>';
            if (d.errores > 0) {
                resultHtml += '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>' + d.errores + ' errores</span>';
            }
            resultHtml += '<span class="badge bg-secondary"><i class="bi bi-list-ol me-1"></i>' + d.total + ' total</span>';
            resultHtml += '</div></div>';
            document.getElementById('importResult').innerHTML = resultHtml;
            document.getElementById('importResult').classList.remove('d-none');

            Swal.fire({
                toast: true, position: 'top-end', icon: icon,
                title: res.data.message,
                showConfirmButton: false, timer: 4000
            });

            $('#clientesTable').DataTable().ajax.reload(null, false);
            cargarHistorialImport();
            fileInput.value = '';
        } else {
            var msg = res.data.message || 'Error al procesar el archivo.';
            if (res.data.errors && res.data.errors.archivo) {
                msg = res.data.errors.archivo[0];
            }
            Swal.fire('Error', msg, 'error');
        }
    })
    .catch(function(err) {
        document.getElementById('importProgress').classList.add('d-none');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-upload me-1"></i>Importar';
        Swal.fire('Error', 'Error de conexion al procesar el archivo.', 'error');
    });
}

function cargarHistorialImport() {
    var container = document.getElementById('historialImportContainer');
    if (!container) return;

    fetch('{{ route("recepcion.clientes.import-history") }}', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.length) {
            container.innerHTML = '<p class="text-muted small mb-0">No hay importaciones registradas.</p>';
            return;
        }

        var estadoBadge = function(estado) {
            var map = {
                'completado': '<span class="badge bg-success">Completado</span>',
                'completado_con_errores': '<span class="badge bg-warning text-dark">Con errores</span>',
                'fallido': '<span class="badge bg-danger">Fallido</span>',
                'procesando': '<span class="badge bg-info">Procesando</span>'
            };
            return map[estado] || '<span class="badge bg-secondary">' + estado + '</span>';
        };

        var html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">';
        html += '<thead><tr class="text-muted small">';
        html += '<th>Fecha</th><th>Usuario</th><th>Archivo</th><th class="text-center">Total</th>';
        html += '<th class="text-center">Creados</th><th class="text-center">Actualizados</th>';
        html += '<th class="text-center">Errores</th><th class="text-center">Estado</th><th class="text-center">Detalle</th>';
        html += '</tr></thead><tbody>';

        data.forEach(function(imp) {
            html += '<tr>';
            html += '<td class="small">' + imp.fecha + '</td>';
            html += '<td class="small">' + imp.usuario + '</td>';
            html += '<td class="small text-truncate" style="max-width:200px" title="' + imp.nombre_archivo + '">' + imp.nombre_archivo + '</td>';
            html += '<td class="text-center"><span class="badge bg-secondary">' + imp.total_filas + '</span></td>';
            html += '<td class="text-center"><span class="badge bg-success">' + imp.creados + '</span></td>';
            html += '<td class="text-center"><span class="badge bg-primary">' + imp.actualizados + '</span></td>';
            html += '<td class="text-center"><span class="badge bg-' + (imp.errores > 0 ? 'danger' : 'secondary') + '">' + imp.errores + '</span></td>';
            html += '<td class="text-center">' + estadoBadge(imp.estado) + '</td>';
            html += '<td class="text-center"><button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="verDetalleImport(' + imp.id + ')" title="Ver detalle"><i class="bi bi-eye"></i></button></td>';
            html += '</tr>';
            html += '<tr class="d-none" id="detalle-row-' + imp.id + '"><td colspan="9" class="bg-light p-0"></td></tr>';
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    })
    .catch(function() {
        container.innerHTML = '<p class="text-muted small mb-0">Error al cargar el historial.</p>';
    });
}

function verDetalleImport(importId) {
    var detalleRow = document.getElementById('detalle-row-' + importId);
    if (!detalleRow) return;

    if (!detalleRow.classList.contains('d-none')) {
        detalleRow.classList.add('d-none');
        return;
    }

    var cell = detalleRow.querySelector('td');
    cell.innerHTML = '<div class="p-3"><p class="text-muted small mb-0"><i class="bi bi-hourglass-split me-1"></i>Cargando detalle...</p></div>';
    detalleRow.classList.remove('d-none');

    fetch('{{ url("recepcion/clientes/import-detail") }}/' + importId, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var log = data.detalle_log || [];
        if (!log.length) {
            cell.innerHTML = '<div class="p-3"><p class="text-muted small mb-0">Sin detalle disponible.</p></div>';
            return;
        }

        var iconMap = {
            'creado': '<i class="bi bi-plus-circle-fill text-success me-1"></i>',
            'actualizado': '<i class="bi bi-pencil-fill text-primary me-1"></i>',
            'error': '<i class="bi bi-x-circle-fill text-danger me-1"></i>'
        };

        var html = '<div class="p-3"><div class="table-responsive" style="max-height:300px;overflow-y:auto">';
        html += '<table class="table table-sm table-bordered mb-0 small">';
        html += '<thead class="table-light"><tr><th width="60">Fila</th><th width="140">Cedula/Nombre</th><th width="120">Accion</th><th>Mensaje</th></tr></thead><tbody>';

        log.forEach(function(entry) {
            var rowClass = entry.accion === 'error' ? ' class="table-danger"' : '';
            var accionLabel = entry.accion === 'creado' ? 'Creado' : (entry.accion === 'actualizado' ? 'Actualizado' : 'Error');
            html += '<tr' + rowClass + '>';
            html += '<td class="text-center">' + entry.fila + '</td>';
            html += '<td><code>' + (entry.codigo || '-') + '</code></td>';
            html += '<td>' + (iconMap[entry.accion] || '') + accionLabel + '</td>';
            html += '<td>' + entry.mensaje + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div></div>';
        cell.innerHTML = html;
    })
    .catch(function() {
        cell.innerHTML = '<div class="p-3"><p class="text-danger small mb-0">Error al cargar el detalle.</p></div>';
    });
}

$(function() { cargarHistorialImport(); });
@endcan
</script>
@endpush
