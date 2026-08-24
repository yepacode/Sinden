@extends('layouts.app')

@section('title', 'Panel de Recepcion (ventas)')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Panel de Recepcion (ventas)" :description="'Bienvenido, ' . auth()->user()->name . ' | ' . now()->translatedFormat('l d \\d\\e F Y')">
    </x-sinden.page-header>

    {{-- 6 Stat Cards clickeables --}}
    <div class="summary-cards">
        @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'entregas_hoy'))
        <a href="{{ route('recepcion.entregas-pendientes') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-clock-history" :value="$stats['entregas_hoy']" title="Entregas Pendientes Hoy" color="warning" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'entregas_hoy_manana'))
        <a href="{{ route('recepcion.entregas-pendientes') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-calendar-event" :value="$stats['entregas_hoy_manana']" title="Entregas Hoy/Manana" color="warning" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'entregas_vencidas'))
        <a href="{{ route('recepcion.entregas-pendientes') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-exclamation-triangle" :value="$stats['entregas_vencidas']" title="Entregas Vencidas" color="danger" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'ordenes_abiertas'))
        <a href="{{ route('recepcion.ordenes.index') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-file-earmark-plus" :value="$stats['ordenes_abiertas']" title="Ordenes Abiertas" color="primary" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'saldo_pendiente'))
        <a href="{{ route('recepcion.ordenes.index') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-currency-dollar" :value="'$' . number_format($stats['saldo_pendiente'], 0, '.', ',')" title="Saldo Pendiente" color="danger" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'para_complementar'))
        <a href="{{ route('recepcion.ordenes.index') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-people-fill" :value="$stats['para_complementar']" title="Para Complementar" color="info" />
        </a>
        @endif
    </div>

    {{-- Contenido principal --}}
    <div class="row mt-4">
        {{-- Acciones rapidas --}}
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
                            @if($stats['ordenes_abiertas'] > 0)
                                <span class="badge bg-primary">{{ $stats['ordenes_abiertas'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('recepcion.entregas-pendientes') }}" class="quick-action-btn">
                            <i class="bi bi-box-seam"></i>
                            <span>Entregas Pendientes</span>
                            @if($stats['entregas_hoy'] > 0)
                                <span class="badge bg-warning text-dark">{{ $stats['entregas_hoy'] }}</span>
                            @endif
                        </a>
                        <a href="{{ route('recepcion.garantias.index') }}" class="quick-action-btn">
                            <i class="bi bi-shield-check"></i>
                            <span>Garantias</span>
                            @if($stats['garantias_activas'] > 0)
                                <span class="badge bg-danger">{{ $stats['garantias_activas'] }}</span>
                            @endif
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Garantias activas + enlaces --}}
        <div class="col-lg-4 mb-4">
            @if(\App\Models\ConfiguracionSistema::metricaVisible('recepcion', 'garantias_activas'))
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <h6 class="mb-0 fw-semibold text-dark">
                        <i class="bi bi-shield-check me-2 text-primary"></i>Garantias Activas
                    </h6>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    @if($stats['garantias_activas'] > 0)
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:56px;height:56px;background:rgba(220,53,69,0.1)">
                                <span class="fw-bold text-danger" style="font-size:1.5rem">{{ $stats['garantias_activas'] }}</span>
                            </div>
                            <div>
                                <div class="fw-semibold">Garantias abiertas o en proceso</div>
                                <small class="text-muted">Requieren atencion</small>
                            </div>
                        </div>
                        <a href="{{ route('recepcion.garantias.index') }}" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-arrow-right me-1"></i>Ver Garantias
                        </a>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-shield-check text-success" style="font-size:2rem"></i>
                            <p class="text-muted mt-2 mb-0">Sin garantias pendientes</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Enlaces rapidos --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <h6 class="mb-0 fw-semibold text-dark">
                        <i class="bi bi-bookmark me-2 text-primary"></i>Catalogos
                    </h6>
                </div>
                <div class="card-body px-4 pb-4 pt-3 d-grid gap-2">
                    <a href="{{ route('recepcion.clientes.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                        <i class="bi bi-people"></i> Clientes
                    </a>
                    <a href="{{ route('recepcion.items.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                        <i class="bi bi-tags"></i> Catalogo de Items
                    </a>
                    <a href="{{ route('recepcion.consulta-precios.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-2">
                        <i class="bi bi-calculator"></i> Consulta de Precios
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
