@extends('layouts.app')

@section('title', 'Panel del Operario')

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header title="Panel del Operario" :description="'Bienvenido, ' . auth()->user()->name . ' | ' . now()->translatedFormat('l d \\d\\e F Y')">
    </x-sinden.page-header>

    {{-- Summary Cards --}}
    <div class="summary-cards">
        @if(\App\Models\ConfiguracionSistema::metricaVisible('operario', 'ordenes_asignadas'))
        <a href="{{ route('operario.ordenes-asignadas') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-list-check" :value="$stats['ordenes_asignadas']" title="Ordenes Asignadas" color="primary" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('operario', 'piezas_en_proceso'))
        <a href="{{ route('operario.ordenes-asignadas') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-gear-wide-connected" :value="$stats['piezas_en_proceso']" title="Piezas en Proceso" color="warning" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('operario', 'para_complementar'))
        <a href="{{ route('operario.complementar') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-plus-circle" :value="$stats['para_complementar']" title="Para Complementar" color="info" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('operario', 'completadas_hoy'))
        <a href="{{ route('operario.ordenes-asignadas') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-check-circle" :value="$stats['completadas_hoy']" title="Completadas Hoy" color="success" />
        </a>
        @endif
        @if(\App\Models\ConfiguracionSistema::metricaVisible('operario', 'garantias_pendientes'))
        <a href="{{ route('operario.garantias') }}" class="text-decoration-none">
            <x-sinden.stat-card icon="bi bi-shield-check" :value="$stats['garantias_pendientes']" title="Garantias Pendientes" color="danger" />
        </a>
        @endif
    </div>

    {{-- Quick Actions --}}
    <div class="quick-actions mt-4">
        <a href="{{ route('operario.ordenes-asignadas') }}" class="quick-action-btn">
            <i class="bi bi-list-check"></i>
            <span>Ver Ordenes Asignadas</span>
            @if($stats['ordenes_asignadas'] > 0)
                <span class="badge bg-primary">{{ $stats['ordenes_asignadas'] }}</span>
            @endif
        </a>
        <a href="{{ route('operario.buscar') }}" class="quick-action-btn">
            <i class="bi bi-search"></i>
            <span>Buscar Orden</span>
        </a>
        <a href="{{ route('operario.complementar') }}" class="quick-action-btn">
            <i class="bi bi-plus-circle"></i>
            <span>Ordenes Pendientes por Terminar</span>
            @if($stats['para_complementar'] > 0)
                <span class="badge bg-info">{{ $stats['para_complementar'] }}</span>
            @endif
        </a>
        <a href="{{ route('operario.garantias') }}" class="quick-action-btn">
            <i class="bi bi-shield-check"></i>
            <span>Mis Garantias</span>
            @if($stats['garantias_pendientes'] > 0)
                <span class="badge bg-danger">{{ $stats['garantias_pendientes'] }}</span>
            @endif
        </a>
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
