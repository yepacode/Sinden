@extends('layouts.app')

@section('title', 'Pagos Pendientes de Aprobacion')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Pagos Pendientes de Aprobacion" description="Pagos registrados por Recepcion (ventas) que requieren aprobacion">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-file-earmark-excel"
                href="{{ route('contabilidad.pagos-pendientes.export-excel') }}">Excel</x-sinden.button>
            @if($porAprobar > 0)
            <button type="button" class="btn btn-success btn-lg" id="btnAprobarTodos" style="min-height:48px">
                <i class="bi bi-check-all me-1"></i>Aprobar Todos ({{ $porAprobar }})
            </button>
            @endif
        </x-slot>
    </x-sinden.page-header>

    {{-- Stat Cards --}}
    <div class="summary-cards">
        <div id="statPorAprobar">
            <x-sinden.stat-card icon="bi bi-hourglass-split" :value="$porAprobar" title="Por Aprobar" color="warning" />
        </div>
        <div id="statMontoPendiente">
            <x-sinden.stat-card icon="bi bi-currency-dollar" :value="'$' . number_format($montoPendiente, 0, '.', ',')" title="Monto Total Pendiente" color="info" />
        </div>
        <div id="statAprobadosHoy">
            <x-sinden.stat-card icon="bi bi-check2-all" :value="$aprobadosHoy" title="Aprobados Hoy" color="success" />
        </div>
    </div>

    {{-- DataTable --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold text-dark">
                    <i class="bi bi-list-ul me-2 text-primary"></i>Pagos Pendientes
                </h6>
                <span class="badge bg-light text-muted border" id="totalRegistros"></span>
            </div>
        </div>
        <div class="card-body px-4 pb-4 pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 sinden-datatable" id="pagosPendientesTable" style="width:100%">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:40px">
                                <input type="checkbox" class="form-check-input" id="selectAllPagos" style="width:20px;height:20px;cursor:pointer" title="Seleccionar todos">
                            </th>
                            <th>Fecha</th>
                            <th>Orden</th>
                            <th>Cliente</th>
                            <th class="text-end">Monto</th>
                            <th class="text-center">Metodo</th>
                            <th>Referencia</th>
                            <th>Registrado Por</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Barra sticky de seleccion masiva --}}
<div class="contabilidad-bulk-bar" id="bulkBar" style="display:none">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="fw-semibold" id="bulkCount">0</span> pago(s) seleccionado(s)
                <span class="ms-2 text-muted">|</span>
                <span class="ms-2 fw-bold" id="bulkMonto">$0</span>
            </div>
            <button type="button" class="btn btn-success btn-lg" id="btnAprobarSeleccionados" style="min-height:48px">
                <i class="bi bi-check-all me-1"></i>Aprobar Seleccionados
            </button>
        </div>
    </div>
</div>

<style>
    .contabilidad-bulk-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #fff;
        border-top: 2px solid var(--sinden-primary, #475569);
        padding: 12px 0;
        box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
        z-index: 1050;
        animation: slideUp 0.3s ease;
    }
    @keyframes slideUp {
        from { transform: translateY(100%); }
        to { transform: translateY(0); }
    }
    .bg-purple {
        background-color: rgba(128, 0, 255, 0.15) !important;
    }
</style>

@endsection

@push('scripts')
<script src="{{ asset('js/contabilidad.js') }}"></script>
<script>
    $(function() {
        initPagosPendientesTable({
            ajaxUrl: '{{ route("contabilidad.pagos-pendientes") }}',
            aprobarUrl: '{{ url("contabilidad/pagos") }}',
            aprobarMasivoUrl: '{{ route("contabilidad.pagos.aprobar-masivo") }}',
            rechazarUrl: '{{ url("contabilidad/pagos") }}',
            csrfToken: '{{ csrf_token() }}'
        });
    });
</script>
@endpush
