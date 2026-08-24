@extends('layouts.app')

@section('title', 'Cliente: ' . $cliente->nombre)

@section('content')
<div class="container-fluid py-4">
    <x-sinden.page-header :title="$cliente->nombre" description="Detalle del cliente">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-arrow-left"
                href="{{ route('recepcion.clientes.index') }}">Volver</x-sinden.button>
            <x-sinden.button variant="primary" icon="bi bi-pencil"
                href="{{ route('recepcion.clientes.edit', $cliente) }}">Editar</x-sinden.button>
        </x-slot>
    </x-sinden.page-header>

    <div class="row mt-4">
        {{-- Informacion del Cliente --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Informacion del Cliente</h6>
                </div>
                <div class="card-body p-4">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 140px;">Nombre</td>
                                <td class="fw-semibold">{{ $cliente->nombre }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Cedula/NIT</td>
                                <td>{{ $cliente->cedula ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Correo</td>
                                <td>
                                    @if($cliente->correo)
                                        <a href="mailto:{{ $cliente->correo }}">{{ $cliente->correo }}</a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Celular Principal (WhatsApp)</td>
                                <td>{{ $cliente->celular_1 ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Celular Secundario</td>
                                <td>{{ $cliente->celular_2 ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Direccion</td>
                                <td>{{ $cliente->direccion ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Estado</td>
                                <td>
                                    @if($cliente->activo)
                                        <span class="status-badge success">ACTIVO</span>
                                    @else
                                        <span class="status-badge danger">INACTIVO</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Registrado</td>
                                <td>{{ $cliente->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @if($cliente->updated_at && $cliente->updated_at != $cliente->created_at)
                            <tr>
                                <td class="text-muted">Actualizado</td>
                                <td>{{ $cliente->updated_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Ordenes del Cliente --}}
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Ordenes del Cliente</h6>
                    @if($cliente->ordenes->count() > 0)
                        <span class="badge bg-primary rounded-pill">{{ $cliente->ordenes->count() }}</span>
                    @endif
                </div>
                <div class="card-body p-4">
                    @if($cliente->ordenes->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th># Orden</th>
                                        <th>Estado</th>
                                        <th class="text-end">Total</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($cliente->ordenes as $orden)
                                        @php
                                            $badgeMap = [
                                                'borrador' => ['secondary', 'BORRADOR'],
                                                'generada' => ['info', 'GENERADA'],
                                                'en_ejecucion' => ['warning', 'EN EJECUCION'],
                                                'ejecutada_parcialmente' => ['warning', 'EJEC. PARCIAL'],
                                                'ejecutada' => ['success', 'EJECUTADA'],
                                                'anulada' => ['danger', 'ANULADA'],
                                            ];
                                            $badge = $badgeMap[$orden->estado_trabajo] ?? ['secondary', strtoupper($orden->estado_trabajo)];
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('recepcion.ordenes.show', $orden) }}" class="fw-semibold text-decoration-none">
                                                    {{ $orden->numero_orden ?? 'Borrador' }}
                                                </a>
                                            </td>
                                            <td><span class="status-badge {{ $badge[0] }}">{{ $badge[1] }}</span></td>
                                            <td class="text-end">${{ number_format($orden->total, 0, '.', ',') }}</td>
                                            <td class="text-muted small">{{ $orden->created_at->format('d/m/Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2 mb-0">Este cliente no tiene ordenes registradas.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
