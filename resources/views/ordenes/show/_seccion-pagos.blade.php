{{-- Seccion 7: Pagos --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 px-4 pt-3 pb-0">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-cash-stack me-2 text-primary"></i>Pagos</h6>
            @hasanyrole('Administrador|Recepcion')
            @if(!in_array($orden->estado_trabajo, ['anulada', 'borrador']))
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="$('#modalAgregarPago').modal('show')">
                    <i class="bi bi-plus-lg me-1"></i>Agregar
                </button>
            @endif
            @endhasanyrole
        </div>
    </div>
    <div class="card-body px-4 pb-3 pt-2">
        <div id="listaPagos">
            @if($orden->pagos->count() > 0)
                @foreach($orden->pagos->sortByDesc('created_at') as $pago)
                    <div class="d-flex justify-content-between align-items-start py-2 {{ !$loop->last ? 'border-bottom' : '' }}" @if($pago->trashed()) style="opacity: 0.55;" @endif>
                        <div>
                            <span class="fw-semibold" @if($pago->trashed()) style="text-decoration: line-through;" @endif>${{ number_format($pago->monto, 0, '.', ',') }}</span>
                            @php($_tp = ($tiposPagoMapa ?? [])[$pago->metodo_pago] ?? null)
                            <span class="badge border ms-1 small @if($_tp)badge-metodo @endif" @if($_tp) style="--m-fg-dark: {{ $_tp['hex_dark'] ?? $_tp['hex'] }}; background-color: {{ $_tp['bg'] }}; color: {{ $_tp['hex'] }}; border-color: {{ $_tp['hex'] }}33 !important;" @endif>
                                @if($_tp)<i class="bi {{ $_tp['icono'] }} me-1"></i>@endif{{ $_tp['etiqueta'] ?? ucfirst($pago->metodo_pago) }}
                            </span>
                            @if($pago->trashed())
                                <span class="badge bg-danger ms-1 small">Rechazado</span>
                            @elseif(!$pago->aprobado)
                                <span class="badge bg-warning text-dark ms-1 small">Pendiente</span>
                            @else
                                <span class="badge bg-success ms-1 small">Aprobado</span>
                            @endif
                            <div class="text-muted small">
                                {{ $pago->registradoPorUsuario->name ?? '-' }} - {{ $pago->created_at->format('d/m/Y H:i') }}
                            </div>
                            @if($pago->referencia_pago)
                                <div class="text-muted small">Ref: {{ $pago->referencia_pago }}</div>
                            @endif
                            @if($pago->trashed())
                                <div class="text-danger small">
                                    <i class="bi bi-x-circle me-1"></i>Rechazado por {{ $pago->rechazadoPorUsuario->name ?? '-' }} el {{ $pago->deleted_at->format('d/m/Y H:i') }}
                                    @if($pago->motivo_rechazo)
                                        - {{ $pago->motivo_rechazo }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <p class="text-muted mb-0 small" id="sinPagos">No hay pagos registrados.</p>
            @endif
        </div>

        {{-- Resumen --}}
        <div class="border-top mt-2 pt-2" id="resumenPagos">
            <div class="d-flex justify-content-between small">
                <span class="text-muted">Total</span>
                <span class="fw-semibold">${{ number_format($orden->total, 0, '.', ',') }}</span>
            </div>
            <div class="d-flex justify-content-between small">
                <span class="text-muted">Pagado</span>
                <span class="fw-semibold text-success" id="totalPagadoDisplay">${{ number_format($orden->total_pagado, 0, '.', ',') }}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="fw-bold">Saldo</span>
                <span class="fw-bold {{ $orden->saldo > 0 ? 'text-danger' : 'text-success' }}" id="saldoDisplay">
                    ${{ number_format($orden->saldo, 0, '.', ',') }}
                </span>
            </div>
        </div>
    </div>
</div>
