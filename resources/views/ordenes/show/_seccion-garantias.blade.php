{{-- Seccion: Garantias --}}
<div class="card border-0 shadow-sm mt-3" id="garantias">
    <div class="card-header bg-white border-0 px-4 pt-3 pb-0">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 fw-semibold">
                <i class="bi bi-shield-check me-2 text-primary"></i>Garantias ({{ $orden->garantias->count() }})
            </h6>
            @hasanyrole('Administrador|Recepcion')
                @if($orden->piezas->where('cantidad_entregada', '>', 0)->count() > 0)
                    <button type="button" class="btn btn-warning btn-sm" onclick="abrirModalGarantia()">
                        <i class="bi bi-shield-plus me-1"></i>Registrar Garantia
                    </button>
                @endif
            @endhasanyrole
        </div>
    </div>
    <div class="card-body px-4 pb-3 pt-2">
        @if($orden->garantias->count() > 0)
            @foreach($orden->garantias->sortByDesc('created_at') as $garantia)
                <div class="py-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="fw-semibold">{{ $garantia->pieza->nombre ?? '-' }}</span>
                            <span class="badge bg-secondary">x{{ $garantia->cantidad_devuelta }}</span>
                            @php
                                $estadoMap = [
                                    'abierta' => 'warning',
                                    'en_proceso' => 'info',
                                    'completada' => 'success',
                                    'reentregada' => 'primary',
                                ];
                            @endphp
                            <span class="status-badge {{ $estadoMap[$garantia->estado] ?? 'secondary' }}">
                                {{ strtoupper(str_replace('_', ' ', $garantia->estado)) }}
                            </span>
                            @if($garantia->cobrable)
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" style="white-space: nowrap;">
                                    <i class="bi bi-cash me-1"></i>Cobrable: ${{ number_format($garantia->monto_cobro, 0, '.', ',') }}
                                </span>
                            @endif
                        </div>

                        <p class="text-muted small mb-1">{{ $garantia->motivo }}</p>

                        <div class="d-flex flex-wrap gap-3 small text-muted">
                            @if($garantia->operarioAsignado)
                                <span><i class="bi bi-person-gear me-1"></i>{{ $garantia->operarioAsignado->name }}</span>
                            @else
                                <span class="text-warning"><i class="bi bi-person-exclamation me-1"></i>Sin operario</span>
                            @endif
                            <span><i class="bi bi-person me-1"></i>{{ $garantia->registradoPorUsuario->name ?? '-' }}</span>
                            <span><i class="bi bi-calendar me-1"></i>{{ $garantia->created_at->format('d/m/Y H:i') }}</span>
                            @if($garantia->completada_en)
                                <span><i class="bi bi-check-circle me-1 text-success"></i>{{ $garantia->completada_en->format('d/m/Y H:i') }}</span>
                            @endif
                            @if($garantia->reentregada_en)
                                <span><i class="bi bi-box-arrow-right me-1 text-primary"></i>{{ $garantia->reentregada_en->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>

                        {{-- Botones de accion segun estado --}}
                        @hasanyrole('Administrador|Recepcion')
                        @if(!in_array($garantia->estado, ['reentregada']))
                        <div class="d-flex gap-1 mt-2">
                            @if($garantia->estado === 'abierta')
                                <button type="button" class="btn btn-outline-info btn-sm"
                                        onclick="asignarOperarioGarantia({{ $garantia->id }})"
                                        title="Asignar Operario">
                                    <i class="bi bi-person-plus me-1"></i>Asignar
                                </button>
                            @endif

                            @if($garantia->estado === 'abierta' && $garantia->operario_asignado_id)
                                <button type="button" class="btn btn-outline-info btn-sm"
                                        onclick="cambiarEstadoGarantia({{ $garantia->id }}, 'en_proceso')"
                                        title="Pasar a En Proceso">
                                    <i class="bi bi-play-fill me-1"></i>Iniciar
                                </button>
                            @endif

                            @if($garantia->estado === 'en_proceso')
                                <button type="button" class="btn btn-outline-success btn-sm"
                                        onclick="cambiarEstadoGarantia({{ $garantia->id }}, 'completada')"
                                        title="Marcar Completada">
                                    <i class="bi bi-check-lg me-1"></i>Completar
                                </button>
                            @endif

                            @if($garantia->estado === 'completada')
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="cambiarEstadoGarantia({{ $garantia->id }}, 'reentregada')"
                                        title="Marcar Reentregada">
                                    <i class="bi bi-box-arrow-right me-1"></i>Reentregada
                                </button>
                            @endif
                        </div>
                        @endif
                        @endhasanyrole
                    </div>
                </div>
            @endforeach
        @else
            <p class="text-muted mb-0 small">No hay devoluciones por garantia.</p>
        @endif
    </div>
</div>
