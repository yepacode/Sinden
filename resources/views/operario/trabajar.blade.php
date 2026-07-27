@extends('layouts.app')

@section('title', 'Trabajar Orden ' . $orden->numero_orden)

@push('styles')
<style>
    /* Entrega resaltada en la vista de trabajo del operario */
    .entrega-destacada {
        background: #FEF3C7;
        border: 1px solid #F59E0B;
        color: #92400E;
    }
    .entrega-destacada .entrega-hora {
        background: #F59E0B;
        color: #fff;
        padding: .1rem .5rem;
        border-radius: .35rem;
        margin-left: .25rem;
        white-space: nowrap;
    }
    [data-theme="dark"] .entrega-destacada,
    [data-bs-theme="dark"] .entrega-destacada {
        background: rgba(245, 158, 11, .18);
        border-color: #F59E0B;
        color: #FCD34D;
    }
    /* .btn-cerrar-bosquejo esta definido globalmente en sinden-components.css */
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <x-sinden.page-header :title="'Orden ' . $orden->numero_orden" :description="($orden->cliente->nombre ?? 'Sin cliente') . ($orden->fecha_entrega ? ' | Entrega: ' . $orden->fecha_entrega->format('d/m/Y') . ($orden->hora_entrega_fmt ? ' ' . $orden->hora_entrega_fmt : '') : '')">
        <x-slot name="actions">
            <a href="{{ route('operario.ordenes-asignadas') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver a Ordenes
            </a>
        </x-slot>
    </x-sinden.page-header>

    {{-- Lock Banner --}}
    @if($lockResult['success'] ?? false)
        <div class="lock-banner locked-self">
            <i class="bi bi-lock-fill"></i>
            <span>Estas trabajando en esta orden. Se liberara automaticamente al salir o por inactividad.</span>
        </div>
    @else
        <div class="lock-banner locked-other">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Esta orden esta bloqueada por <strong>{{ $lockResult['locked_by'] ?? 'otro usuario' }}</strong> ({{ $lockResult['locked_by_role'] ?? '' }}). No puedes editar en este momento.</span>
        </div>
    @endif

    {{-- Order Summary --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body px-4 py-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <div>
                    @php
                        $badgeConfig = [
                            'generada' => ['GENERADA', 'info'],
                            'en_ejecucion' => ['EN EJECUCION', 'warning'],
                            'ejecutada_parcialmente' => ['EJECUTADA PARC.', 'warning'],
                            'ejecutada' => ['EJECUTADA', 'success'],
                        ];
                        $bc = $badgeConfig[$orden->estado_trabajo] ?? ['', 'secondary'];
                    @endphp
                    <span class="badge bg-{{ $bc[1] }}">{{ $bc[0] }}</span>
                </div>
                {{-- Piezas asignadas: texto grande y visible para operarios (tablet / vista cansada) --}}
                <div class="piezas-asignadas-destacado d-flex align-items-center gap-2">
                    <i class="bi bi-puzzle-fill text-primary fs-4"></i>
                    <span>
                        <strong class="fs-2 text-primary">{{ $piezas->count() }}</strong>
                        <span class="fs-5 fw-semibold">pieza(s) asignada(s) a ti</span>
                        <span class="fs-6 text-muted">de {{ $orden->piezas->count() }} total(es)</span>
                    </span>
                </div>
                {{-- Entrega resaltada: fecha + hora bien visibles para el operario --}}
                <div class="entrega-destacada ms-auto d-flex align-items-center gap-2 px-3 py-2 rounded">
                    <i class="bi bi-alarm-fill fs-5"></i>
                    <div class="lh-sm">
                        <div class="small text-uppercase fw-semibold" style="letter-spacing:.03em; opacity:.85;">Entregar</div>
                        <div class="fw-bold">
                            {{ $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : 'Sin fecha' }}
                            @if($orden->hora_entrega_fmt)
                                <span class="entrega-hora">{{ $orden->hora_entrega_fmt }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                @if($orden->notas)
                    <div class="text-muted">
                        <i class="bi bi-sticky me-1"></i><em>{{ $orden->notas }}</em>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Documentos adjuntos (subidos por recepcion) --}}
    @if($orden->documentos->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 px-4 pt-3 pb-0">
            <h6 class="mb-0 fw-bold">
                <i class="bi bi-paperclip me-1"></i>Documentos adjuntos
                <span class="badge bg-secondary ms-1">{{ $orden->documentos->count() }}</span>
            </h6>
        </div>
        <div class="card-body px-4 py-3">
            <div class="row g-2">
                @foreach($orden->documentos as $doc)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('recepcion.ordenes.documentos.descargar', ['orden' => $orden->id, 'documento' => $doc->id]) }}"
                       class="d-flex align-items-center gap-2 p-2 border rounded text-decoration-none text-body doc-descargar"
                       title="Descargar {{ $doc->nombre_original }}">
                        <i class="bi {{ $doc->icono }} fs-4"></i>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate fw-semibold">{{ $doc->nombre_original }}</div>
                            <small class="text-muted">
                                {{ $doc->tamano_legible }}
                                @if($doc->subidoPorUsuario) &middot; {{ $doc->subidoPorUsuario->name }} @endif
                                @if($doc->created_at) &middot; {{ $doc->created_at->format('d/m/Y') }} @endif
                            </small>
                        </div>
                        <i class="bi bi-download text-primary"></i>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Piezas de trabajo --}}
    @foreach($piezas as $pieza)
    <div class="pieza-card pieza-trabajo mb-4" id="pieza-{{ $pieza->id }}" data-pieza-id="{{ $pieza->id }}" data-porcentaje-original="{{ (float) $pieza->porcentaje_avance }}">
        <div class="d-flex gap-3 align-items-start mb-3">
            {{-- Bosquejo thumbnail --}}
            @if($pieza->bosquejo && $pieza->bosquejo->ruta_miniatura)
                <div class="flex-shrink-0">
                    <img src="{{ asset($pieza->bosquejo->ruta_miniatura) }}"
                         class="rounded border cursor-pointer"
                         style="width:80px; height:80px; object-fit:cover;"
                         onclick="abrirLightbox('{{ asset($pieza->bosquejo->ruta_archivo) }}', '{{ e($pieza->bosquejo->nombre) }}')"
                         alt="{{ $pieza->bosquejo->nombre }}">
                    <small class="d-block text-center text-muted mt-1" style="font-size:0.7rem;">{{ Str::limit($pieza->bosquejo->nombre, 12) }}</small>
                </div>
            @endif

            {{-- Pieza info --}}
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="mb-0 fw-bold">{{ $pieza->nombre }} <small class="text-muted fw-normal">(x{{ $pieza->cantidad }})</small></h6>
                        @if($pieza->entregada)
                            <span class="badge bg-success ms-1">ENTREGADA</span>
                        @endif
                    </div>
                    <span class="fw-bold fs-5 pieza-porcentaje-display text-{{ (float)$pieza->porcentaje_avance >= 100 ? 'success' : ((float)$pieza->porcentaje_avance >= 50 ? 'warning' : ((float)$pieza->porcentaje_avance > 0 ? 'info' : 'muted')) }}">
                        {{ intval($pieza->porcentaje_avance) }}%
                    </span>
                </div>

                @if($pieza->especificacion)
                    <small class="text-secondary d-block">{{ $pieza->especificacion }}</small>
                @endif

                @if($pieza->notas)
                    <small class="text-muted d-block"><i class="bi bi-sticky me-1"></i><em>{{ $pieza->notas }}</em></small>
                @endif

                @if($pieza->material || $pieza->calibre)
                    <small class="text-muted d-block mt-1">
                        @if($pieza->material)<span class="badge bg-light text-dark border me-1">{{ $pieza->material }}</span>@endif
                        @if($pieza->calibre)<span class="badge bg-light text-dark border">{{ $pieza->calibre }}</span>@endif
                    </small>
                @endif
            </div>
        </div>

        {{-- Multi-operator progress bar --}}
        @php
            $historialCerrado = $pieza->historialAvances->whereNotNull('completado_en')->sortBy('created_at');
            $operarioColorMap = [];
            $colorIndex = 0;
        @endphp
        <div class="progress-multi mb-2" style="height:14px; border-radius:8px; overflow:hidden; display:flex; background:#e9ecef;">
            @foreach($historialCerrado as $h)
                @php
                    $opId = $h->operario_id;
                    if (!isset($operarioColorMap[$opId])) {
                        $operarioColorMap[$opId] = $coloresOperarios[$colorIndex % count($coloresOperarios)];
                        $colorIndex++;
                    }
                    $contrib = max(0, (float)$h->contribucion);
                @endphp
                @if($contrib > 0)
                    <div style="width:{{ $contrib }}%; background:{{ $operarioColorMap[$opId] }}; height:100%;"
                         title="{{ $h->operario->name ?? '?' }}: {{ $h->porcentaje_desde }}% → {{ $h->porcentaje_hasta }}% (+{{ $contrib }}%)"></div>
                @endif
            @endforeach
        </div>

        {{-- Legend --}}
        @if(count($operarioColorMap) > 0)
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach($operarioColorMap as $opId => $color)
                    @php $opName = $pieza->historialAvances->firstWhere('operario_id', $opId)?->operario?->name ?? '?'; @endphp
                    <small class="d-flex align-items-center gap-1">
                        <span style="width:10px; height:10px; border-radius:2px; background:{{ $color }}; display:inline-block;"></span>
                        {{ $opName }}
                    </small>
                @endforeach
            </div>
        @endif

        {{-- Toggles: Historial + Observaciones (misma fila) --}}
        <div class="mb-3 observaciones-wrapper" id="obsWrapper{{ $pieza->id }}">
            <div class="d-flex flex-wrap gap-3">
                @if($pieza->historialAvances->count() > 0)
                    <a class="text-primary text-decoration-none small" data-bs-toggle="collapse" href="#hist{{ $pieza->id }}">
                        <i class="bi bi-clock-history me-1"></i>Ver historial ({{ $pieza->historialAvances->count() }})
                    </a>
                @endif
                <a class="text-info text-decoration-none small obs-toggle {{ $pieza->observaciones->count() > 0 ? '' : 'd-none' }}"
                   data-bs-toggle="collapse" href="#obs{{ $pieza->id }}">
                    <i class="bi bi-chat-left-text me-1"></i>Ver observaciones (<span class="obs-count">{{ $pieza->observaciones->count() }}</span>)
                </a>
            </div>

            {{-- Historial colapsable --}}
            @if($pieza->historialAvances->count() > 0)
                <div class="collapse mt-2" id="hist{{ $pieza->id }}">
                    <div class="historial-timeline">
                        @foreach($pieza->historialAvances->sortByDesc('created_at') as $h)
                            <div class="timeline-item">
                                <strong>{{ $h->operario->name ?? 'Desconocido' }}</strong>
                                <small class="text-muted">({{ $h->created_at->format('d/m/Y H:i') }})</small>
                                <br>
                                <small>
                                    {{ $h->porcentaje_desde }}% &rarr; {{ $h->porcentaje_hasta }}%
                                    @if((float)$h->contribucion != 0)
                                        <span class="fw-semibold {{ (float)$h->contribucion > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ (float)$h->contribucion > 0 ? '+' : '' }}{{ $h->contribucion }}%
                                        </span>
                                    @endif
                                    @if(!$h->completado_en)
                                        <span class="badge bg-warning text-dark ms-1">En curso</span>
                                    @endif
                                </small>
                                @if($h->notas)
                                    <br><small class="text-muted"><i class="bi bi-chat-text me-1"></i>{{ $h->notas }}</small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Observaciones colapsable --}}
            <div class="collapse mt-2" id="obs{{ $pieza->id }}">
                <div class="historial-timeline obs-list" id="obsList{{ $pieza->id }}">
                    @foreach($pieza->observaciones->sortByDesc('created_at') as $obs)
                        <div class="timeline-item">
                            <strong>{{ $obs->usuario->name ?? 'Desconocido' }}</strong>
                            <small class="text-muted">({{ $obs->created_at->format('d/m/Y H:i') }})</small>
                            <br>
                            <small><i class="bi bi-chat-text me-1"></i>{{ $obs->observacion }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- === CONTROLES DE TRABAJO === --}}
        @if($lockResult['success'] ?? false)
        <div class="pieza-controles border-top pt-3">
            {{-- Porcentaje slider + input --}}
            <label class="form-label fw-semibold small mb-1">Actualizar Porcentaje:</label>
            <div class="d-flex align-items-center gap-3">
                <input type="range" class="form-range flex-grow-1 pieza-slider"
                       data-pieza-id="{{ $pieza->id }}"
                       min="0" max="100" step="5"
                       value="{{ intval($pieza->porcentaje_avance) }}">
                <div class="input-group" style="width: 130px; flex-shrink: 0;">
                    <input type="number" class="form-control text-center fw-bold pieza-porcentaje-input"
                           data-pieza-id="{{ $pieza->id }}"
                           min="0" max="100"
                           value="{{ intval($pieza->porcentaje_avance) }}">
                    <span class="input-group-text">%</span>
                </div>
            </div>

            {{-- Foto / Observacion --}}
            <div class="mt-3 d-flex flex-wrap gap-2">
                <label class="btn btn-sm btn-outline-secondary mb-0">
                    <i class="bi bi-camera me-1"></i>Adjuntar Foto
                    <input type="file" class="d-none foto-input" accept="image/*" capture="environment"
                           data-pieza-id="{{ $pieza->id }}">
                </label>
                <button type="button" class="btn btn-sm btn-outline-info btn-observacion"
                        data-pieza-id="{{ $pieza->id }}" data-pieza-nombre="{{ e($pieza->nombre) }}">
                    <i class="bi bi-chat-left-text me-1"></i>Ingresar Observación
                </button>
            </div>
            <div class="foto-preview mt-2 d-flex flex-wrap gap-2" id="fotoPreview{{ $pieza->id }}">
                @foreach($pieza->fotos as $foto)
                    <img src="{{ asset($foto->ruta_archivo) }}"
                         class="foto-thumb rounded border cursor-pointer"
                         style="width:60px;height:60px;object-fit:cover;"
                         onclick="abrirLightbox('{{ asset($foto->ruta_archivo) }}', 'Foto de avance')"
                         alt="Foto de avance">
                @endforeach
            </div>

            {{-- Acciones: Transferir / Dejar en cola --}}
            <div class="d-flex gap-2 mt-3 flex-wrap">
                <button class="btn btn-sm btn-outline-secondary btn-transferir"
                        data-pieza-id="{{ $pieza->id }}" data-pieza-nombre="{{ e($pieza->nombre) }}">
                    <i class="bi bi-arrow-left-right me-1"></i>Transferir a Operario
                </button>
                <button class="btn btn-sm btn-outline-secondary btn-dejar-cola"
                        data-pieza-id="{{ $pieza->id }}" data-pieza-nombre="{{ e($pieza->nombre) }}">
                    <i class="bi bi-box-seam me-1"></i>Terminé mi parte
                </button>
            </div>
        </div>
        @endif
    </div>
    @endforeach

    {{-- Boton principal ACTUALIZAR ORDEN --}}
    @if($lockResult['success'] ?? false)
    <div class="text-center mt-4 mb-5">
        @if($piezas->count() > 1)
        <div class="mb-3">
            <button type="button" class="btn btn-outline-warning" id="btnTransferirMasivo">
                <i class="bi bi-arrow-left-right me-1"></i>Transferir TODAS mis piezas a un operario
            </button>
            <div class="small text-muted mt-1">Envia de una vez tus {{ $piezas->count() }} piezas al mismo operario.</div>
        </div>
        @endif
        <button type="button" class="btn btn-lg btn-primary px-5" id="btnActualizarOrden">
            <i class="bi bi-check-lg me-2"></i>ACTUALIZAR ORDEN
        </button>
    </div>
    @endif
</div>

{{-- Modal Transferir --}}
<div class="modal fade" id="modalTransferir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transferir Pieza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Transferir <strong id="transferirPiezaNombre"></strong> a otro operario:</p>
                <input type="hidden" id="transferirPiezaId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Operario destino</label>
                    <select class="form-select" id="transferirOperarioSelect">
                        <option value="">Selecciona un operario...</option>
                        @foreach($operarios as $op)
                            <option value="{{ $op->id }}">{{ $op->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notas (opcional)</label>
                    <textarea class="form-control" id="transferirNotas" rows="2" placeholder="Instrucciones o notas para el otro operario..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarTransferencia">
                    <i class="bi bi-arrow-left-right me-1"></i>Confirmar Transferencia
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Transferir Masivo (todas las piezas del operario a un mismo operario) --}}
<div class="modal fade" id="modalTransferirMasivo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Transferir todas las piezas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Se transferiran <strong>tus {{ $piezas->count() }} pieza(s)</strong> de esta orden al operario que elijas.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Operario destino</label>
                    <select class="form-select" id="transferirMasivoOperarioSelect">
                        <option value="">Selecciona un operario...</option>
                        @foreach($operarios as $op)
                            <option value="{{ $op->id }}">{{ $op->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notas (opcional)</label>
                    <textarea class="form-control" id="transferirMasivoNotas" rows="2" placeholder="Instrucciones o notas para el otro operario (se aplican a todas las piezas)..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarTransferenciaMasiva">
                    <i class="bi bi-arrow-left-right me-1"></i>Confirmar Transferencia
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Lightbox Fullscreen --}}
<div class="modal fade" id="modalLightbox" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0">
                <h6 class="modal-title text-white" id="lightboxTitulo"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body d-flex align-items-center justify-content-center p-0" style="overflow:hidden;position:relative;">
                <div id="lightboxZoomContainer" style="overflow:auto;width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:1rem;">
                    <img id="lightboxImagen" src="" style="max-height:calc(100vh - 120px);max-width:92vw;object-fit:contain;transition:transform 0.2s;cursor:zoom-in;" onclick="toggleZoomLightbox()">
                </div>
                {{-- Boton grande de cerrar al centro-derecha: facil de tocar en tablet con manos ocupadas --}}
                <button type="button" class="btn btn-danger btn-cerrar-bosquejo position-absolute top-50 end-0 translate-middle-y me-3" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cerrar
                </button>
                {{-- Contador visual --}}
                <div class="position-absolute bottom-0 end-0 mb-3 me-3 d-flex align-items-center gap-3 bg-dark bg-opacity-75 rounded-pill px-3 py-2">
                    <button type="button" class="btn btn-outline-light rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:1.2rem;" onclick="cambiarContador(-1)">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <span id="contadorValor" class="text-white fw-bold" style="font-size:1.68rem;min-width:38px;text-align:center;">0</span>
                    <button type="button" class="btn btn-outline-light rounded-circle d-flex align-items-center justify-content-center" style="width:38px;height:38px;font-size:1.2rem;" onclick="cambiarContador(1)">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                {{-- Controles zoom (abajo-izquierda, separados del boton Cerrar) --}}
                <div class="position-absolute bottom-0 start-0 mb-3 ms-3 d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center text-white" style="width:32px;height:32px;background:rgba(0,0,0,0.75);border:1px solid #000;" onclick="zoomLightbox(-1)" title="Alejar">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <div class="d-flex align-items-center bg-dark bg-opacity-75 rounded-pill px-2 py-1" style="border:1px solid #000;">
                        <input type="number" id="zoomPercentInput" min="20" max="500" step="10" value="100" onchange="setZoomPercent(this.value)" style="width:58px;border:none;background:transparent;color:#fff;text-align:center;font-size:0.9rem;outline:none;">
                        <span class="text-white" style="font-size:0.9rem;">%</span>
                    </div>
                    <button type="button" class="btn btn-sm rounded-circle d-flex align-items-center justify-content-center text-white" style="width:32px;height:32px;background:rgba(0,0,0,0.75);border:1px solid #000;" onclick="zoomLightbox(1)" title="Acercar">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var ORDEN_ID = {{ $orden->id }};
var NUMERO_ORDEN = '{{ $orden->numero_orden }}';
var CSRF_TOKEN = '{{ csrf_token() }}';
var LOCK_ACQUIRED = {{ ($lockResult['success'] ?? false) ? 'true' : 'false' }};
var TIMEOUT_INACTIVIDAD = {{ $timeoutInactividad * 60 * 1000 }};
var TOTAL_PIEZAS_ORDEN = {{ $totalPiezasOrden }};
var PIEZAS_OTROS_100 = {{ $piezasOtros100 }};
var OPERARIO_ROUTES = {
    actualizarAvances: '{{ route("operario.ordenes.actualizar-avances", $orden) }}',
    heartbeat: '{{ route("operario.ordenes.heartbeat", $orden) }}',
    desbloquear: '{{ route("operario.ordenes.desbloquear", $orden) }}',
    bloquear: '{{ route("operario.ordenes.bloquear", $orden) }}',
    estadoBloqueo: '{{ route("operario.ordenes.estado-bloqueo", $orden) }}',
    ordenesAsignadas: '{{ route("operario.ordenes-asignadas") }}'
};

let contadorLightbox = 0;
let zoomLevel = 1;

function aplicarZoom() {
    const img = document.getElementById('lightboxImagen');
    img.style.transform = 'scale(' + zoomLevel + ')';
    img.style.cursor = zoomLevel > 1 ? 'zoom-out' : 'zoom-in';
    document.getElementById('zoomPercentInput').value = Math.round(zoomLevel * 100);
}

function abrirLightbox(ruta, titulo) {
    contadorLightbox = 0;
    zoomLevel = 1;
    document.getElementById('contadorValor').textContent = '0';
    document.getElementById('lightboxImagen').src = ruta;
    aplicarZoom();
    $('#lightboxTitulo').text(titulo || 'Bosquejo');
    new bootstrap.Modal(document.getElementById('modalLightbox')).show();
}

function cambiarContador(delta) {
    contadorLightbox += delta;
    document.getElementById('contadorValor').textContent = contadorLightbox;
}

function zoomLightbox(direction) {
    zoomLevel = Math.max(0.2, Math.min(5, zoomLevel + direction * 0.1));
    aplicarZoom();
}

function setZoomPercent(val) {
    let pct = parseInt(val, 10);
    if (isNaN(pct)) { aplicarZoom(); return; }
    pct = Math.max(20, Math.min(500, pct));
    zoomLevel = pct / 100;
    aplicarZoom();
}

function toggleZoomLightbox() {
    zoomLevel = zoomLevel > 1 ? 1 : 1.5;
    aplicarZoom();
}
</script>
<script src="{{ asset('js/operario-trabajo.js') }}"></script>
@endpush
