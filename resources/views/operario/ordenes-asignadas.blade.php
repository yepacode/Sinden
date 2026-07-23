@extends('layouts.app')

@section('title', 'Mis Ordenes Asignadas')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Mis Ordenes Asignadas" description="Ordenes donde tienes piezas asignadas para trabajar">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="{{ route('operario.ordenes-asignadas.export-excel') }}">Excel</x-sinden.button>
            <a href="{{ route('operario.panel') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver al Panel
            </a>
        </x-slot>
    </x-sinden.page-header>

    {{-- DataTable --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold text-dark">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Mis Ordenes
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="ordenesAsignadasTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Orden #</th>
                            <th>Cliente</th>
                            <th>Fecha Creacion</th>
                            <th>Fecha Entrega</th>
                            <th>Hora Entrega</th>
                            <th>Mis Piezas</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    var table = $('#ordenesAsignadasTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("operario.ordenes-asignadas") }}',
        columns: [
            { data: 'numero_orden', name: 'numero_orden', className: 'fw-semibold' },
            { data: 'cliente_nombre', name: 'cliente_nombre' },
            { data: 'fecha_creacion_fmt', name: 'created_at', className: 'text-center' },
            { data: 'fecha_entrega_fmt', name: 'fecha_entrega', className: 'text-center' },
            { data: 'hora_entrega_fmt', name: 'hora_entrega', searchable: false, className: 'text-center' },
            { data: 'mis_piezas', name: 'mis_piezas', orderable: false, searchable: false, className: 'text-center' },
            { data: 'estado', name: 'estado_trabajo', orderable: true, searchable: false, className: 'text-center' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '100px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        // Arranca ordenada por urgencia: la entrega mas proxima primero (fecha y luego hora)
        order: [[3, 'asc'], [4, 'asc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal || 0;
            $('#totalRegistros').text(total + ' orden' + (total !== 1 ? 'es' : ''));
        }
    });
});
</script>
@endpush
