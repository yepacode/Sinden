@extends('layouts.app')

@section('title', 'Ordenes con Saldo Pendiente')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Ordenes con Saldo Pendiente" description="Ordenes que tienen saldo por cobrar">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="#" onclick="exportarOrdenesPendientesExcel(); return false;">Excel</x-sinden.button>
        </x-slot>
    </x-sinden.page-header>

    {{-- Stat Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-receipt-cutoff" :value="$totalOrdenes" title="Ordenes con Saldo" color="primary" />
        <x-sinden.stat-card icon="bi bi-currency-dollar" :value="'$' . number_format($totalPendiente, 0, '.', ',')" title="Cartera Total" color="danger" />
        <x-sinden.stat-card icon="bi bi-hourglass-split" :value="$abonosSinAprobar" title="Abonos sin Aprobar" color="warning" />
        <x-sinden.stat-card icon="bi bi-cash-stack" :value="'$' . number_format($recaudadoHoy, 0, '.', ',')" title="Recaudado Hoy" color="success" />
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body px-4 py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">Numero de Orden</label>
                    <input type="text" class="form-control" id="filtroNumeroOrden" placeholder="Ej: #0001" style="min-height:44px">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small text-muted mb-1">Cliente</label>
                    <input type="text" class="form-control" id="filtroCliente" placeholder="Nombre del cliente" style="min-height:44px">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Desde</label>
                    <input type="date" class="form-control" id="filtroFechaDesde" style="min-height:44px">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Hasta</label>
                    <input type="date" class="form-control" id="filtroFechaHasta" style="min-height:44px">
                </div>
                <div class="col-md-2 col-12">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary flex-grow-1" id="btnFiltrar" style="min-height:44px">
                            <i class="bi bi-search me-1"></i>Filtrar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnLimpiar" style="min-height:44px" title="Borrar filtros">
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
                    <i class="bi bi-list-ul me-2 text-primary"></i>Ordenes con Saldo
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="ordenesPendientesTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Cliente</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-center">% Pagado</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Pend.</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Agregar Pago --}}
<div class="modal fade" id="modalAgregarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2 text-primary"></i>Registrar Pago
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="infoPagoOrdenContainer" class="mb-3"></div>

                <input type="hidden" id="pagoOrdenId">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Monto *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="pagoMonto" min="1" step="100" placeholder="0" style="min-height:48px; font-size:1.1rem" required>
                    </div>
                    <small class="text-muted">Maximo permitido: <span id="pagoMontoMax">$0</span></small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Metodo de Pago *</label>
                    <select class="form-select" id="pagoMetodo" style="min-height:48px" required>
                        @foreach(($tiposPago ?? collect()) as $tp)
                            <option value="{{ $tp->codigo }}">{{ $tp->codigo }} - {{ $tp->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Referencia <span class="text-muted fw-normal">(opcional)</span></label>
                    <input type="text" class="form-control" id="pagoReferencia" placeholder="Nro. referencia" style="min-height:44px">
                </div>

                <div class="alert alert-info small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Los pagos registrados por Contabilidad se aprueban automaticamente.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="min-height:44px">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnRegistrarPago" style="min-height:44px">
                    <i class="bi bi-check-lg me-1"></i>Registrar Pago
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/contabilidad.js') }}"></script>
<script>
    $(function() {
        initOrdenesPendientesTable({
            ajaxUrl: '{{ route("contabilidad.ordenes-pendientes") }}',
            pagoStoreUrl: '{{ url("contabilidad/ordenes") }}',
            csrfToken: '{{ csrf_token() }}'
        });
    });

    function exportarOrdenesPendientesExcel() {
        var params = {
            numero_orden: $('#filtroNumeroOrden').val(),
            cliente: $('#filtroCliente').val(),
            fecha_desde: $('#filtroFechaDesde').val(),
            fecha_hasta: $('#filtroFechaHasta').val()
        };
        var qs = $.param(Object.keys(params).reduce(function(acc, k) {
            if (params[k]) acc[k] = params[k];
            return acc;
        }, {}));
        window.location.href = '{{ route("contabilidad.ordenes-pendientes.export-excel") }}' + (qs ? ('?' + qs) : '');
    }
</script>
@endpush
