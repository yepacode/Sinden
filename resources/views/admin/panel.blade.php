@extends('layouts.app')

@section('title', 'Panel de Administracion')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Panel de Administracion" :description="'Bienvenido, ' . auth()->user()->name . ' | ' . now()->translatedFormat('l d \\d\\e F Y')">
    </x-sinden.page-header>

    {{-- Stat Cards clickeables --}}
    <div class="summary-cards">
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'ordenes_activas'))
        <a href="{{ route('recepcion.ordenes.index') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-file-earmark-text" :value="$stats['ordenes_activas']" title="Ordenes Activas" color="primary" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'entregas_vencidas'))
        <a href="{{ route('recepcion.entregas-pendientes') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-exclamation-triangle" :value="$stats['entregas_vencidas']" title="Entregas Vencidas" color="danger" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'saldo_pendiente_total'))
        <a href="{{ route('contabilidad.ordenes-pendientes') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-currency-dollar" :value="'$' . number_format($stats['saldo_pendiente_total'], 0, '.', ',')" title="Saldo Pendiente" color="danger" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'recaudado_hoy'))
        <x-sinden.stat-card icon="bi bi-cash-stack" :value="'$' . number_format($stats['recaudado_hoy'], 0, '.', ',')" title="Recaudado Hoy" color="success" />
        @endif
    </div>

    {{-- Segunda fila de stats --}}
    <div class="summary-cards mt-3">
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'garantias_activas'))
        <a href="{{ route('recepcion.garantias.index') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-shield-check" :value="$stats['garantias_activas']" title="Garantias Activas" color="warning" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'pagos_por_aprobar'))
        <a href="{{ route('contabilidad.pagos-pendientes') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-hourglass-split" :value="$stats['pagos_por_aprobar']" title="Pagos por Aprobar" color="warning" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('admin', 'ordenes_hoy'))
        <x-sinden.stat-card icon="bi bi-plus-circle" :value="$stats['ordenes_hoy']" title="Ordenes Nuevas Hoy" color="info" />
        @endif
    </div>

    {{-- Acciones rapidas --}}
    <div class="row mt-4">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <h6 class="mb-0 fw-semibold text-dark">
                        <i class="bi bi-lightning me-2 text-primary"></i>Acciones Rapidas
                    </h6>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="quick-actions">
                        <a href="{{ route('recepcion.ordenes.crear') }}" class="quick-action-btn">
                            <i class="bi bi-plus-circle"></i>
                            <span>Crear Orden</span>
                        </a>
                        <a href="{{ route('recepcion.ordenes.index') }}" class="quick-action-btn">
                            <i class="bi bi-search"></i>
                            <span>Buscar Ordenes</span>
                            @if($stats['ordenes_activas'] > 0)
                                <span class="badge bg-primary">{{ $stats['ordenes_activas'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('recepcion.entregas-pendientes') }}" class="quick-action-btn">
                            <i class="bi bi-box-seam"></i>
                            <span>Entregas Pendientes</span>
                            @if($stats['entregas_vencidas'] > 0)
                                <span class="badge bg-danger">{{ $stats['entregas_vencidas'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('contabilidad.pagos-pendientes') }}" class="quick-action-btn">
                            <i class="bi bi-hourglass-split"></i>
                            <span>Pagos por Aprobar</span>
                            @if($stats['pagos_por_aprobar'] > 0)
                                <span class="badge bg-warning text-dark">{{ $stats['pagos_por_aprobar'] }}</span>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Panel de administracion --}}
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <h6 class="mb-0 fw-semibold text-dark">
                        <i class="bi bi-gear me-2 text-primary"></i>Administracion
                    </h6>
                </div>
                <div class="card-body px-4 pb-4 pt-3 d-grid gap-2">
                    <a href="{{ route('admin.configuracion') }}" class="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center gap-2" style="min-height:48px">
                        <i class="bi bi-sliders"></i> Configuracion del Sistema
                    </a>
                    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center gap-2" style="min-height:48px">
                        <i class="bi bi-people-fill"></i> Gestion de Usuarios
                    </a>
                    <a href="{{ route('admin.tabla-precios.index') }}" class="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center gap-2" style="min-height:48px">
                        <i class="bi bi-table"></i> Tabla de Precios
                    </a>
                    <a href="{{ route('recepcion.garantias.index') }}" class="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center gap-2" style="min-height:48px">
                        <i class="bi bi-shield-check"></i> Garantias
                        @if($stats['garantias_activas'] > 0)
                            <span class="badge bg-danger">{{ $stats['garantias_activas'] }}</span>
                        @endif
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .summary-cards a:hover .summary-card {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .summary-cards a .summary-card {
        cursor: pointer;
        transition: all 0.2s ease;
    }
</style>
@endsection
