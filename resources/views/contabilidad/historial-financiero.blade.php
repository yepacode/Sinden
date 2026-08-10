@extends('layouts.app')

@section('title', 'Historial Financiero')

@push('styles')
<style>
    [data-theme="dark"] #historialFinancieroTable tfoot tr.table-light,
    [data-theme="dark"] #historialFinancieroTable tfoot tr.table-light > td {
        background-color: #2a2a2a !important;
        color: #e5e7eb !important;
        border-color: #3a3a3a !important;
        --bs-table-bg: #2a2a2a;
        --bs-table-color: #e5e7eb;
    }
    [data-theme="dark"] #historialFinancieroTable tfoot tr.table-light .text-dark {
        color: #f3f4f6 !important;
    }
    [data-theme="dark"] #historialFinancieroTable tfoot tr.table-light .text-muted {
        color: #9ca3af !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Historial Financiero" description="Resumen financiero de todas las ordenes">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="{{ route('contabilidad.historial-financiero.export') }}" id="btnExportar">Excel</x-sinden.button>
        </x-slot>
    </x-sinden.page-header>

    {{-- Stat Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-journal-text" :value="$totalOrdenes" title="Total Ordenes" color="primary" />
        <x-sinden.stat-card icon="bi bi-check-circle" :value="$ordenesPagadas" title="Ordenes Pagadas" color="success" />
        <x-sinden.stat-card icon="bi bi-cash-stack" :value="'$' . number_format($totalRecaudado, 0, '.', ',')" title="Total Recaudado" color="info" />
        <x-sinden.stat-card icon="bi bi-exclamation-triangle" :value="'$' . number_format($totalPorCobrar, 0, '.', ',')" title="Total por Cobrar" color="danger" />
    </div>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body px-4 py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Numero de Orden</label>
                    <input type="text" class="form-control" id="filtroNumeroOrden" placeholder="Ej: #0001" style="min-height:44px">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Cliente</label>
                    <input type="text" class="form-control" id="filtroCliente" placeholder="Nombre del cliente" style="min-height:44px">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small text-muted mb-1">Estado de Pago</label>
                    <select class="form-select" id="filtroEstadoPago" style="min-height:44px">
                        <option value="todos">Todos</option>
                        <option value="pagada">Pagada</option>
                        <option value="saldo_pendiente">Saldo Pendiente</option>
                        <option value="sin_pagos">Sin Pagos</option>
                    </select>
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
                        <button type="button" class="btn btn-primary flex-grow-1" id="btnFiltrarHistorial" style="min-height:44px">
                            <i class="bi bi-search me-1"></i>Filtrar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnLimpiarHistorial" style="min-height:44px" title="Borrar filtros">
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
                    <i class="bi bi-list-ul me-2 text-primary"></i>Todas las Ordenes
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="historialFinancieroTable" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:40px">
                                <input type="checkbox" class="form-check-input" id="checkAll" checked>
                            </th>
                            <th>Orden</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-center">%</th>
                            <th class="text-center">Estado Pago</th>
                            <th class="text-center">Pagos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr class="table-light fw-bold">
                            <td colspan="4" class="text-end">Totales seleccionados:</td>
                            <td class="text-end" id="sumaTotal">$0</td>
                            <td class="text-end" id="sumaPagado">$0</td>
                            <td class="text-end" id="sumaSaldo">$0</td>
                            <td colspan="4"></td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="4" class="text-end small text-muted">
                                Subtotal (Sin IVA): <span id="sumaSubtotal" class="fw-bold text-dark">$0</span>
                            </td>
                            <td colspan="3" class="text-end small text-muted">
                                IVA: <span id="sumaIva" class="fw-bold text-dark">$0</span>
                            </td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal Historial de Pagos --}}
<div class="modal fade" id="modalHistorialPagos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-receipt me-2 text-primary"></i>Pagos de Orden <span id="modalOrdenNumero" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Resumen de la orden --}}
                <div id="modalResumenContainer" class="mb-3"></div>

                {{-- Tabla de pagos --}}
                <div id="modalPagosContainer">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2 text-muted">Cargando pagos...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="min-height:44px">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/contabilidad.js') }}"></script>
<script>
    $(function() {
        initHistorialFinancieroTable({
            ajaxUrl: '{{ route("contabilidad.historial-financiero") }}',
            exportUrl: '{{ route("contabilidad.historial-financiero.export") }}',
            pagosUrl: '{{ url("contabilidad/ordenes") }}',
            csrfToken: '{{ csrf_token() }}'
        });
    });
</script>
@endpush
