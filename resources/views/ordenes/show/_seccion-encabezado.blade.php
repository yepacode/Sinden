{{-- Seccion 1: Encabezado con estados y resumen financiero --}}
<div class="card border-0 shadow-sm">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div>
                        <span class="order-status-label">TRABAJO</span><br>
                        @php
                            $mapTrabajo = [
                                'borrador' => 'secondary', 'generada' => 'info', 'en_ejecucion' => 'warning',
                                'ejecutada_parcialmente' => 'warning', 'ejecutada' => 'success', 'anulada' => 'danger',
                            ];
                            $labelTrabajo = [
                                'borrador' => 'BORRADOR', 'generada' => 'GENERADA', 'en_ejecucion' => 'EN EJECUCION',
                                'ejecutada_parcialmente' => 'EJEC. PARCIAL', 'ejecutada' => 'EJECUTADA', 'anulada' => 'ANULADA',
                            ];
                        @endphp
                        <span class="status-badge {{ $mapTrabajo[$orden->estado_trabajo] ?? 'secondary' }}">
                            {{ $labelTrabajo[$orden->estado_trabajo] ?? strtoupper($orden->estado_trabajo) }}
                        </span>
                    </div>
                    <div>
                        <span class="order-status-label">ENTREGA</span><br>
                        @if($orden->estado_entrega)
                            @php
                                $mapEntrega = ['entregada_parcialmente' => 'info', 'entregada' => 'success'];
                                $labelEntrega = ['entregada_parcialmente' => 'ENTREGA PARCIAL', 'entregada' => 'ENTREGADA'];
                            @endphp
                            <span class="status-badge {{ $mapEntrega[$orden->estado_entrega] ?? 'secondary' }}">
                                {{ $labelEntrega[$orden->estado_entrega] ?? strtoupper($orden->estado_entrega) }}
                            </span>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </div>
                    <div>
                        <span class="order-status-label">PAGO</span><br>
                        @if($orden->estado_pago)
                            @php
                                $mapPago = ['saldo_pendiente' => 'danger', 'pagado' => 'success'];
                                $labelPago = ['saldo_pendiente' => 'SALDO PEND.', 'pagado' => 'PAGADO'];
                            @endphp
                            <span class="status-badge {{ $mapPago[$orden->estado_pago] ?? 'secondary' }}" id="headerBadgePago">
                                {{ $labelPago[$orden->estado_pago] ?? strtoupper($orden->estado_pago) }}
                            </span>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </div>
                </div>
                @if($orden->clonada_de_id && $orden->ordenOriginal)
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-copy me-1"></i>Copiada de
                            <a href="{{ route('recepcion.ordenes.show', $orden->ordenOriginal) }}">
                                {{ $orden->ordenOriginal->numero_orden ?? 'Borrador #' . $orden->ordenOriginal->id }}
                            </a>
                        </small>
                    </div>
                @endif
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="d-inline-flex gap-4">
                    <div>
                        <span class="order-status-label">TOTAL</span><br>
                        <span class="fs-5 fw-bold text-dark">${{ number_format($orden->total, 0, '.', ',') }}</span>
                    </div>
                    <div>
                        <span class="order-status-label">PAGADO</span><br>
                        <span class="fs-5 fw-semibold text-success" id="headerPagado">${{ number_format($orden->total_pagado, 0, '.', ',') }}</span>
                    </div>
                    <div>
                        <span class="order-status-label">SALDO</span><br>
                        <span class="fs-5 fw-bold {{ $orden->saldo > 0 ? 'text-danger' : 'text-success' }}" id="headerSaldo">
                            ${{ number_format($orden->saldo, 0, '.', ',') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
