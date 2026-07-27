@extends('layouts.app')

@section('title', 'Entregar Piezas - ' . ($orden->numero_orden ?? 'Orden'))

{{-- .bosquejo-entrega-thumb esta definido globalmente en sinden-components.css --}}

@section('content')
<div class="container-fluid py-4" x-data="entregaFlujo()">
    {{-- Page Header --}}
    <x-sinden.page-header title="Entregar Piezas" :description="'Orden ' . ($orden->numero_orden ?? '#' . $orden->id) . ' - ' . ($orden->cliente->nombre ?? 'Sin cliente')">
        <x-slot name="actions">
            <x-sinden.button variant="outline" icon="bi bi-arrow-left"
                href="{{ route('recepcion.entregas-pendientes') }}">Volver</x-sinden.button>
        </x-slot>
    </x-sinden.page-header>

    {{-- Resumen de la Orden --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body px-4 py-3">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <small class="text-muted d-block">Orden</small>
                    <strong>{{ $orden->numero_orden ?? 'Borrador' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Cliente</small>
                    <strong>{{ $orden->cliente->nombre ?? '-' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Fecha Entrega</small>
                    <strong>{{ $orden->fecha_entrega ? $orden->fecha_entrega->format('d/m/Y') : '-' }}</strong>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">Piezas Pendientes / Total</small>
                    <strong>
                        <span class="text-warning">{{ $unidadesPendientes }}</span>
                        <span class="text-muted">/ {{ $totalUnidades }}</span>
                    </strong>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        {{-- Columna izquierda: Tabla de piezas --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <div>
                        <h6 class="mb-1 fw-semibold text-dark">
                            <i class="bi bi-check2-square me-2 text-primary"></i>Piezas Pendientes
                        </h6>
                        <span class="text-muted small">
                            <span x-text="selectedIds.length"></span> de <span x-text="piezas.length"></span> seleccionada(s)
                        </span>
                    </div>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" @click="toggleAll()" :checked="allSelected">
                                    </th>
                                    <th class="text-center" style="width: 70px;">Bosquejo</th>
                                    <th>Identificador</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Pendiente</th>
                                    <th class="text-center" style="width: 100px;">Entregar</th>
                                    <th>Material</th>
                                    <th>Calibre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="pieza in piezas" :key="pieza.id">
                                    <tr :class="selectedIds.includes(pieza.id) ? 'table-success' : ''">
                                        <td>
                                            <input type="checkbox" class="form-check-input" :value="pieza.id"
                                                :checked="selectedIds.includes(pieza.id)" @change="togglePieza(pieza.id)">
                                        </td>
                                        <td class="text-center" @click.stop>
                                            <template x-if="pieza.bosquejo_miniatura">
                                                <img :src="pieza.bosquejo_miniatura"
                                                    @click="verBosquejoPieza(pieza.bosquejo_imagen, pieza.nombre)"
                                                    class="bosquejo-entrega-thumb" alt="Bosquejo"
                                                    title="Click para ver el bosquejo">
                                            </template>
                                            <template x-if="!pieza.bosquejo_miniatura">
                                                <span class="text-muted small" title="Sin bosquejo"><i class="bi bi-image"></i></span>
                                            </template>
                                        </td>
                                        <td>
                                            <span class="fw-semibold" x-text="pieza.nombre"></span>
                                            <div class="small text-muted">
                                                <span x-text="pieza.cantidad_entregada"></span> / <span x-text="pieza.cantidad"></span> entregadas
                                            </div>
                                            <div class="progress mt-1" style="height: 4px; width: 100px;">
                                                <div class="progress-bar bg-info" :style="'width:' + (pieza.cantidad > 0 ? (pieza.cantidad_entregada / pieza.cantidad * 100) : 0) + '%'"></div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary" x-text="pieza.cantidad"></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark" x-text="pieza.cantidad_pendiente"></span>
                                        </td>
                                        <td class="text-center" @click.stop>
                                            <input type="number" class="form-control form-control-sm text-center"
                                                :min="1" :max="pieza.cantidad_pendiente"
                                                x-model.number="cantidades[pieza.id]"
                                                @focus="if(!selectedIds.includes(pieza.id)) togglePieza(pieza.id)"
                                                style="width: 70px; margin: 0 auto;">
                                        </td>
                                        <td x-text="pieza.material || '-'"></td>
                                        <td x-text="pieza.calibre || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Tabla de piezas ya entregadas (solo visual) --}}
            @if($piezasEntregadas->isNotEmpty())
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <div>
                        <h6 class="mb-1 fw-semibold text-dark">
                            <i class="bi bi-check-circle me-2 text-success"></i>Piezas Entregadas
                        </h6>
                        <span class="text-muted small">
                            Registro de piezas ya entregadas (informativo)
                        </span>
                    </div>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 70px;">Bosquejo</th>
                                    <th>Identificador</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Entregadas</th>
                                    <th class="text-center">Pendientes</th>
                                    <th>Material</th>
                                    <th>Calibre</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($piezasEntregadas as $pe)
                                    @php
                                        $pendientesPE = max(0, $pe->cantidad - $pe->cantidad_entregada);
                                        $completa = $pendientesPE === 0;
                                    @endphp
                                    <tr>
                                        <td class="text-center">
                                            @if($pe->bosquejo)
                                                <img src="{{ asset($pe->bosquejo->ruta_miniatura ?: $pe->bosquejo->ruta_archivo) }}"
                                                    onclick="verBosquejoPieza('{{ asset($pe->bosquejo->ruta_archivo) }}', '{{ addslashes($pe->nombre) }}')"
                                                    class="bosquejo-entrega-thumb" alt="Bosquejo" title="Click para ver el bosquejo">
                                            @else
                                                <span class="text-muted small" title="Sin bosquejo"><i class="bi bi-image"></i></span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-semibold">{{ $pe->nombre }}</span>
                                            <div class="small text-muted">
                                                {{ $pe->cantidad_entregada }} / {{ $pe->cantidad }} entregadas
                                            </div>
                                            <div class="progress mt-1" style="height: 4px; width: 100px;">
                                                <div class="progress-bar bg-success" style="width: {{ $pe->cantidad > 0 ? ($pe->cantidad_entregada / $pe->cantidad * 100) : 0 }}%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ $pe->cantidad }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success">{{ $pe->cantidad_entregada }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if($completa)
                                                <span class="badge bg-light text-muted">0</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ $pendientesPE }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $pe->material ?: '-' }}</td>
                                        <td>{{ $pe->calibre ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Columna derecha: Foto + Boton entregar --}}
        <div class="col-lg-4">
            {{-- Foto de entrega --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <h6 class="mb-0 fw-semibold text-dark">
                        <i class="bi bi-camera me-2 text-primary"></i>Fotos
                        <span class="text-muted fw-normal small ms-1">(Opcional)</span>
                        <span class="badge bg-secondary ms-1" x-show="fotosSubidas.length > 0" x-text="fotosSubidas.length" x-cloak></span>
                    </h6>
                </div>
                <div class="card-body px-4 pb-4 pt-3">
                    {{-- Grid de fotos tomadas --}}
                    <div class="d-flex flex-wrap gap-2 mb-3" x-show="fotosSubidas.length > 0" x-cloak>
                        <template x-for="foto in fotosSubidas" :key="foto.id">
                            <div class="position-relative">
                                <img :src="foto.url" class="rounded shadow-sm border"
                                     style="width: 80px; height: 80px; object-fit: cover;">
                                <button type="button"
                                        class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center"
                                        style="width: 22px; height: 22px; transform: translate(35%, -35%); border-radius: 50%;"
                                        @click="quitarFoto(foto.id)"
                                        title="Quitar foto">
                                    <i class="bi bi-x" style="font-size: 14px;"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- Boton tomar foto --}}
                    <div class="text-center" x-show="!uploading">
                        <button type="button" class="btn btn-outline-primary btn-sm" @click="abrirCamara()">
                            <i class="bi bi-camera me-2"></i>
                            <span x-show="fotosSubidas.length === 0">Tomar Foto</span>
                            <span x-show="fotosSubidas.length > 0" x-cloak>Tomar otra foto</span>
                        </button>
                    </div>

                    {{-- Subiendo foto --}}
                    <div class="text-center p-2" x-show="uploading" x-cloak>
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 mb-0 text-muted small">Subiendo...</p>
                    </div>
                </div>
            </div>

            {{-- Resumen de entrega --}}
            <div class="card border-0 shadow-sm mt-3" x-show="selectedIds.length > 0">
                <div class="card-body px-4 py-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-receipt me-2 text-primary"></i>Resumen</h6>
                    <template x-for="pieza in piezasSeleccionadas" :key="pieza.id">
                        <div class="d-flex justify-content-between small mb-1">
                            <span x-text="pieza.nombre"></span>
                            <span class="fw-semibold" x-text="cantidades[pieza.id] + ' ud.'"></span>
                        </div>
                    </template>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between small fw-bold">
                        <span>Total unidades</span>
                        <span x-text="totalUnidades"></span>
                    </div>
                </div>
            </div>

            {{-- Boton entregar --}}
            <div class="d-grid mt-3">
                <button class="btn btn-success btn-lg" :disabled="noneSelected || submitting" @click="confirmarEntrega()">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    <span x-show="!submitting">Entregar <span x-text="totalUnidades"></span> Unidad(es)</span>
                    <span x-show="submitting">Procesando...</span>
                </button>
            </div>

            {{-- Boton rapida: seleccionar todas --}}
            <div class="d-grid mt-2" x-show="piezas.length > 1 && !allSelected">
                <button class="btn btn-outline-primary btn-sm" @click="seleccionarTodas()">
                    <i class="bi bi-lightning me-1"></i>Seleccionar Todas
                </button>
            </div>
        </div>
    </div>
</div>
{{-- Modal Camara Entrega --}}
<div class="modal fade" id="modalCamaraEntrega" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-semibold"><i class="bi bi-camera me-2"></i>Tomar Foto de Entrega</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center p-3">
                <video id="camaraEntregaVideo" autoplay playsinline
                    class="img-fluid rounded shadow-sm" style="max-height: 350px; width: 100%;"></video>
                <canvas id="camaraEntregaCanvas" class="d-none"></canvas>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" onclick="camaraEntregaCapturar()">
                    <i class="bi bi-camera-fill me-1"></i>Capturar
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Visor de bosquejo a pantalla completa (con boton grande de cerrar) --}}
<div class="modal fade" id="lightboxBosquejoEntrega" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-dark">
            <div class="modal-header border-0">
                <h6 class="modal-title text-white" id="lightboxBosquejoTitulo"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body d-flex align-items-center justify-content-center p-2" style="position:relative;overflow:auto;">
                <img id="lightboxBosquejoImg" src="" class="img-fluid" style="max-height:calc(100vh - 90px);">
                {{-- Boton grande de cerrar al centro-derecha (facil de tocar en tablet) --}}
                <button type="button" class="btn-cerrar-bosquejo btn-cerrar-bosquejo--centro-derecha" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Abrir el bosquejo de la pieza en grande para consultarlo durante la entrega
function verBosquejoPieza(url, nombre) {
    if (!url) {
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Esta pieza no tiene bosquejo.', showConfirmButton: false, timer: 2500 });
        return;
    }
    document.getElementById('lightboxBosquejoImg').src = url;
    document.getElementById('lightboxBosquejoTitulo').textContent = nombre || 'Bosquejo';
    new bootstrap.Modal(document.getElementById('lightboxBosquejoEntrega')).show();
}

function entregaFlujo() {
    var piezasData = @json($piezasEntregables);
    var cantidadesInit = {};
    piezasData.forEach(function(p) {
        cantidadesInit[p.id] = p.cantidad_pendiente;
    });

    return {
        piezas: piezasData,
        selectedIds: [],
        cantidades: cantidadesInit,
        fotosSubidas: [],
        uploading: false,
        submitting: false,

        get allSelected() {
            return this.selectedIds.length === this.piezas.length && this.piezas.length > 0;
        },

        get noneSelected() {
            return this.selectedIds.length === 0;
        },

        get piezasSeleccionadas() {
            var self = this;
            return this.piezas.filter(function(p) { return self.selectedIds.includes(p.id); });
        },

        get totalUnidades() {
            var self = this;
            var total = 0;
            this.selectedIds.forEach(function(id) {
                total += (self.cantidades[id] || 0);
            });
            return total;
        },

        toggleAll() {
            if (this.allSelected) {
                this.selectedIds = [];
            } else {
                this.selectedIds = this.piezas.map(function(p) { return p.id; });
            }
        },

        seleccionarTodas() {
            this.selectedIds = this.piezas.map(function(p) { return p.id; });
        },

        togglePieza(id) {
            var idx = this.selectedIds.indexOf(id);
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            } else {
                this.selectedIds.push(id);
            }
        },

        abrirCamara() {
            var self = this;
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                Swal.fire('Error', 'Tu navegador no soporta acceso a la camara.', 'error');
                return;
            }

            var modal = document.getElementById('modalCamaraEntrega');
            if (!modal) return;
            var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then(function(mediaStream) {
                    window._entregaCamaraStream = mediaStream;
                    var video = document.getElementById('camaraEntregaVideo');
                    if (video) {
                        video.srcObject = mediaStream;
                    }
                })
                .catch(function(err) {
                    console.error('Error al acceder a la camara:', err);
                    bsModal.hide();
                    Swal.fire('Error', 'No se pudo acceder a la camara. Verifica los permisos.', 'error');
                });
        },

        cerrarCamara() {
            if (window._entregaCamaraStream) {
                window._entregaCamaraStream.getTracks().forEach(function(track) { track.stop(); });
                window._entregaCamaraStream = null;
            }
            var modal = document.getElementById('modalCamaraEntrega');
            if (modal) {
                var bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }
        },

        quitarFoto(fotoId) {
            var self = this;
            $.ajax({
                url: '{{ url("recepcion/entregas-pendientes/" . $orden->id . "/foto-entrega") }}/' + fotoId,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(data) {
                    if (data.success) {
                        self.fotosSubidas = self.fotosSubidas.filter(function(f) { return f.id !== fotoId; });
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : 'No se pudo eliminar la foto.';
                    Swal.fire('Error', msg, 'error');
                }
            });
        },

        subirBlob(blob) {
            this.uploading = true;
            var formData = new FormData();
            formData.append('foto', blob, 'foto_entrega.jpg');

            var self = this;
            $.ajax({
                url: '{{ route("recepcion.entregas.foto-entrega", $orden) }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(data) {
                    if (data.success) {
                        self.fotosSubidas.push(data.foto);
                    }
                    self.uploading = false;
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : 'No se pudo subir la foto.';
                    Swal.fire('Error', msg, 'error');
                    self.uploading = false;
                }
            });
        },

        confirmarEntrega() {
            if (this.noneSelected) return;
            var self = this;

            // Construir detalle de piezas
            var piezasPayload = [];
            var detalleHtml = '<ul class="text-start list-unstyled mb-0">';
            this.selectedIds.forEach(function(id) {
                var pieza = self.piezas.find(function(p) { return p.id === id; });
                var cant = self.cantidades[id] || 0;
                if (pieza && cant > 0) {
                    piezasPayload.push({ pieza_id: id, cantidad: cant });
                    detalleHtml += '<li><b>' + pieza.nombre + '</b>: ' + cant + ' de ' + pieza.cantidad + '</li>';
                }
            });
            detalleHtml += '</ul>';

            if (piezasPayload.length === 0) return;

            Swal.fire({
                title: 'Confirmar Entrega',
                html: detalleHtml,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#475569',
                confirmButtonText: 'Si, entregar',
                cancelButtonText: 'Cancelar'
            }).then(function(result) {
                if (result.isConfirmed) {
                    self.submitting = true;

                    var payload = { piezas: piezasPayload };
                    if (self.fotosSubidas.length > 0) {
                        payload.foto_ids = self.fotosSubidas.map(function(f) { return f.id; });
                    }

                    $.ajax({
                        url: '{{ route("recepcion.entregas.entregar", $orden) }}',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        data: JSON.stringify(payload),
                        success: function(data) {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Entrega Exitosa',
                                    text: data.message,
                                    confirmButtonColor: '#475569'
                                }).then(function() {
                                    window.location.href = '{{ route("recepcion.entregas-pendientes") }}';
                                });
                            }
                        },
                        error: function(xhr) {
                            var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error al procesar la entrega.';
                            Swal.fire('Error', msg, 'error');
                            self.submitting = false;
                        }
                    });
                }
            });
        }
    };
}

// Captura foto desde el modal de camara
function camaraEntregaCapturar() {
    var video = document.getElementById('camaraEntregaVideo');
    var canvas = document.getElementById('camaraEntregaCanvas');
    if (!video || !canvas) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    // Cerrar camara y modal
    if (window._entregaCamaraStream) {
        window._entregaCamaraStream.getTracks().forEach(function(track) { track.stop(); });
        window._entregaCamaraStream = null;
    }
    var modal = document.getElementById('modalCamaraEntrega');
    if (modal) {
        var bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    }

    // Obtener el componente Alpine y subir la foto
    var alpineEl = document.querySelector('[x-data]');
    var alpineData = Alpine.$data(alpineEl);

    canvas.toBlob(function(blob) {
        if (blob) {
            alpineData.subirBlob(blob);
        }
    }, 'image/jpeg', 0.85);
}

// Limpiar stream cuando se cierra el modal por cualquier medio
$(function() {
    var modalCamara = document.getElementById('modalCamaraEntrega');
    if (modalCamara) {
        modalCamara.addEventListener('hidden.bs.modal', function() {
            if (window._entregaCamaraStream) {
                window._entregaCamaraStream.getTracks().forEach(function(track) { track.stop(); });
                window._entregaCamaraStream = null;
            }
        });
    }
});
</script>
@endpush
