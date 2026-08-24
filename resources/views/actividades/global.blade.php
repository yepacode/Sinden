@extends('layouts.app')

@section('title', 'Actividades Globales')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Actividades Globales" description="Registro de todas las acciones realizadas en el sistema">
        @if(Route::has($routePrefix . '.actividades-globales.export-excel'))
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="#" onclick="exportarActividadesGlobalesExcel(); return false;">Excel</x-sinden.button>
        </x-slot>
        @endif
    </x-sinden.page-header>

    {{-- Stat Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-calendar-check" :value="$stats['total_hoy']" title="Hoy" color="success" />
        <x-sinden.stat-card icon="bi bi-calendar-week" :value="$stats['total_semana']" title="Esta Semana" color="primary" />
        <x-sinden.stat-card icon="bi bi-people" :value="$stats['usuarios_activos_hoy']" title="Usuarios Activos Hoy" color="info" />
        <x-sinden.stat-card icon="bi bi-database" :value="number_format($stats['total_registros'], 0, '.', ',')" title="Total Registros" color="secondary" />
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-3">
            <h6 class="mb-3 fw-semibold text-dark">
                <i class="bi bi-funnel me-2 text-primary"></i>Filtros
            </h6>
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="filtroFechaDesde" class="form-label small text-muted mb-1">Fecha Desde</label>
                    <input type="date" class="form-control" id="filtroFechaDesde">
                </div>
                <div class="col-md-2">
                    <label for="filtroFechaHasta" class="form-label small text-muted mb-1">Fecha Hasta</label>
                    <input type="date" class="form-control" id="filtroFechaHasta">
                </div>
                <div class="col-md-3">
                    <label for="filtroAccion" class="form-label small text-muted mb-1">Tipo de Accion</label>
                    <select class="form-select" id="filtroAccion">
                        <option value="">Todas las acciones</option>
                        @foreach($tiposAccion as $clave => $etiqueta)
                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filtroUsuario" class="form-label small text-muted mb-1">Usuario</label>
                    <select class="form-select" id="filtroUsuario">
                        <option value="">Todos los usuarios</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-fill" id="btnFiltrar">
                            <i class="bi bi-search me-1"></i>Filtrar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnLimpiar" title="Borrar filtros">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- DataTable --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold text-dark">
                    <i class="bi bi-activity me-2 text-primary"></i>Todas las Actividades
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="actividadesTable" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width:150px">Fecha/Hora</th>
                            <th style="width:140px">Usuario</th>
                            <th style="width:110px" class="text-center">Rol</th>
                            <th style="width:200px">Accion</th>
                            <th style="width:120px">Orden</th>
                            <th>Descripcion</th>
                            <th style="width:80px" class="text-center">Detalle</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@include('actividades.partials._modal-detalle')
@endsection

@push('scripts')
<script src="{{ asset('js/actividades.js') }}"></script>
<script>
    $(function() {
        initActividadesTable({
            ajaxUrl: window.location.href,
            personal: false
        });
    });

    function exportarActividadesGlobalesExcel() {
        var params = {
            fecha_desde: $('#filtroFechaDesde').val(),
            fecha_hasta: $('#filtroFechaHasta').val(),
            accion: $('#filtroAccion').val(),
            usuario_id: $('#filtroUsuario').val()
        };
        var qs = $.param(Object.keys(params).reduce(function(acc, k) {
            if (params[k]) acc[k] = params[k];
            return acc;
        }, {}));
        var base = '{{ route($routePrefix . ".actividades-globales.export-excel") }}';
        window.location.href = base + (qs ? ('?' + qs) : '');
    }
</script>
@endpush
