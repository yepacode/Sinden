@extends('layouts.app')

@section('title', 'Bosquejos Matriz')

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header --}}
    <x-sinden.page-header title="Bosquejos Matriz" description="Biblioteca de plantillas de bosquejos organizadas por grupos">
        <x-slot name="actions">
            @can('gestionar_bosquejos_matriz')
            <x-sinden.button variant="outline-primary" icon="bi bi-image"
                onclick="abrirModalSubirBosquejoIndividual()">Subir Bosquejo</x-sinden.button>
            <x-sinden.button variant="primary" icon="bi bi-folder-plus"
                onclick="$('#modalNuevoGrupo').modal('show')">Nuevo Grupo</x-sinden.button>
            @endcan
        </x-slot>
    </x-sinden.page-header>

    {{-- Summary Cards --}}
    <div class="summary-cards">
        <x-sinden.stat-card icon="bi bi-folder" :value="$totalGrupos" title="Total Grupos" color="primary" />
        <x-sinden.stat-card icon="bi bi-image" :value="$totalBosquejos" title="Total Bosquejos" color="info" />
        <x-sinden.stat-card icon="bi bi-images" :value="$bosquejosSinGrupo" :title="\App\Models\ConfiguracionSistema::get('nombre_bosquejos_genericos', 'Genericos')" color="warning" />
    </div>

    {{-- Accordion de Grupos --}}
    @if($grupos->count() > 0)
    <div class="accordion mt-4" id="acordeonGrupos">
        @foreach($grupos as $grupo)
        <div class="accordion-item border-0 shadow-sm mb-3 rounded-3 overflow-hidden" id="grupo-card-{{ $grupo->id }}">
            <h2 class="accordion-header">
                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                    type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapse-{{ $grupo->id }}">
                    <i class="bi bi-folder2-open me-2 text-primary"></i>
                    <strong>{{ $grupo->nombre }}</strong>
                    <span class="badge bg-light text-muted border ms-2">
                        {{ $grupo->plantillas->count() }} bosquejo{{ $grupo->plantillas->count() !== 1 ? 's' : '' }}
                    </span>
                </button>
            </h2>
            <div id="collapse-{{ $grupo->id }}"
                class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                data-bs-parent="#acordeonGrupos">
                <div class="accordion-body">
                    {{-- Botones de accion del grupo --}}
                    @can('gestionar_bosquejos_matriz')
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary"
                            onclick="abrirModalSubirBosquejo({{ $grupo->id }}, '{{ addslashes($grupo->nombre) }}')">
                            <i class="bi bi-upload me-1"></i> Subir Bosquejo
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="abrirModalRenombrar('grupo', {{ $grupo->id }}, '{{ addslashes($grupo->nombre) }}')">
                            <i class="bi bi-pencil me-1"></i> Renombrar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                            onclick="confirmarEliminarGrupo({{ $grupo->id }}, '{{ addslashes($grupo->nombre) }}', {{ $grupo->plantillas->count() }})">
                            <i class="bi bi-trash me-1"></i> Eliminar Grupo
                        </button>
                    </div>
                    @endcan

                    {{-- Grid de tarjetas de bosquejos --}}
                    @if($grupo->plantillas->count() > 0)
                    <div class="row g-3" id="bosquejos-grid-{{ $grupo->id }}">
                        @foreach($grupo->plantillas as $plantilla)
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2" id="bosquejo-card-{{ $plantilla->id }}">
                            <div class="card bosquejo-card h-100 border-0 shadow-sm">
                                <div class="bosquejo-thumb-wrapper" onclick="verBosquejo('{{ asset($plantilla->ruta_archivo) }}', '{{ addslashes($plantilla->nombre) }}')">
                                    <img src="{{ asset($plantilla->ruta_miniatura ?? $plantilla->ruta_archivo) }}"
                                        class="card-img-top bosquejo-thumb"
                                        alt="{{ $plantilla->nombre }}"
                                        loading="lazy">
                                </div>
                                <div class="card-body p-2 text-center">
                                    <p class="card-text small mb-1 fw-semibold text-truncate" title="{{ $plantilla->nombre }}">
                                        {{ $plantilla->nombre }}
                                    </p>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('recepcion.bosquejos-matriz.bosquejos.descargar', $plantilla) }}"
                                            class="btn btn-outline-primary btn-sm" title="Descargar">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        @can('gestionar_bosquejos_matriz')
                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                            title="Renombrar"
                                            onclick="abrirModalRenombrar('bosquejo', {{ $plantilla->id }}, '{{ addslashes($plantilla->nombre) }}')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm"
                                            title="Eliminar"
                                            onclick="confirmarEliminarBosquejo({{ $plantilla->id }}, '{{ addslashes($plantilla->nombre) }}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-images" style="font-size: 2rem; opacity: 0.3;"></i>
                        <p class="mt-2 mb-0">Este grupo no tiene bosquejos todavia.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Seccion de Bosquejos sin grupo (nombre configurable, por defecto "Genericos") --}}
    @php $nombreGenericos = \App\Models\ConfiguracionSistema::get('nombre_bosquejos_genericos', 'Genericos'); @endphp
    @if($bosquejosSueltos->count() > 0)
    <div class="card border-0 shadow-sm mb-3 rounded-3 overflow-hidden mt-4" id="seccion-individuales">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div>
                <i class="bi bi-image me-2 text-warning"></i>
                <strong id="tituloGenericos">{{ $nombreGenericos }}</strong>
                @can('gestionar_bosquejos_matriz')
                <button type="button" class="btn btn-sm btn-link p-0 ms-1 align-baseline text-secondary"
                    title="Renombrar seccion" onclick="editarNombreGenericos()">
                    <i class="bi bi-pencil"></i>
                </button>
                @endcan
                <span class="badge bg-light text-muted border ms-2">{{ $bosquejosSueltos->count() }}</span>
            </div>
            @can('gestionar_bosquejos_matriz')
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalSubirBosquejoIndividual()">
                <i class="bi bi-upload me-1"></i> Subir Bosquejo
            </button>
            @endcan
        </div>
        <div class="card-body">
            <div class="row g-3" id="bosquejos-grid-individuales">
                @foreach($bosquejosSueltos as $plantilla)
                <div class="col-6 col-md-4 col-lg-3 col-xl-2" id="bosquejo-card-{{ $plantilla->id }}">
                    <div class="card bosquejo-card h-100 border-0 shadow-sm">
                        <div class="bosquejo-thumb-wrapper" onclick="verBosquejo('{{ asset($plantilla->ruta_archivo) }}', '{{ addslashes($plantilla->nombre) }}')">
                            <img src="{{ asset($plantilla->ruta_miniatura ?? $plantilla->ruta_archivo) }}"
                                class="card-img-top bosquejo-thumb"
                                alt="{{ $plantilla->nombre }}"
                                loading="lazy">
                        </div>
                        <div class="card-body p-2 text-center">
                            <p class="card-text small mb-1 fw-semibold text-truncate" title="{{ $plantilla->nombre }}">
                                {{ $plantilla->nombre }}
                            </p>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('recepcion.bosquejos-matriz.bosquejos.descargar', $plantilla) }}"
                                    class="btn btn-outline-primary btn-sm" title="Descargar">
                                    <i class="bi bi-download"></i>
                                </a>
                                @can('gestionar_bosquejos_matriz')
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                    title="Renombrar"
                                    onclick="abrirModalRenombrar('bosquejo', {{ $plantilla->id }}, '{{ addslashes($plantilla->nombre) }}')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                    title="Eliminar"
                                    onclick="confirmarEliminarBosquejo({{ $plantilla->id }}, '{{ addslashes($plantilla->nombre) }}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if($grupos->count() === 0 && $bosquejosSueltos->count() === 0)
    {{-- Estado vacio --}}
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-folder-plus text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
            <h5 class="mt-3 text-muted">No hay bosquejos</h5>
            <p class="text-muted mb-3">Crea un grupo o sube un bosquejo individual para comenzar.</p>
            @can('gestionar_bosquejos_matriz')
            <div class="d-flex gap-2 justify-content-center">
                <x-sinden.button variant="outline-primary" icon="bi bi-image"
                    onclick="abrirModalSubirBosquejoIndividual()">Subir Bosquejo</x-sinden.button>
                <x-sinden.button variant="primary" icon="bi bi-folder-plus"
                    onclick="$('#modalNuevoGrupo').modal('show')">Crear Grupo</x-sinden.button>
            </div>
            @endcan
        </div>
    </div>
    @endif
</div>

{{-- ===== MODALES ===== --}}

@can('gestionar_bosquejos_matriz')
{{-- Modal: Nuevo Grupo --}}
<x-sinden.modal id="modalNuevoGrupo" title="Nuevo Grupo de Bosquejos">
    <form id="formNuevoGrupo" onsubmit="event.preventDefault(); guardarGrupo();">
        <div class="mb-3">
            <label for="grupo_nombre" class="form-label">
                <i class="bi bi-folder me-1"></i> Nombre del Grupo <span class="text-danger">*</span>
            </label>
            <input type="text" id="grupo_nombre" name="grupo_nombre"
                class="form-control" placeholder="Ej: Puertas Industriales" required maxlength="255">
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <x-sinden.button variant="primary" icon="bi bi-check-lg" onclick="guardarGrupo()">Crear Grupo</x-sinden.button>
    </x-slot>
</x-sinden.modal>

{{-- Modal: Subir Bosquejo --}}
<x-sinden.modal id="modalSubirBosquejo" title="Subir Bosquejo">
    <form id="formSubirBosquejo" enctype="multipart/form-data" onsubmit="event.preventDefault(); subirBosquejo();">
        <input type="hidden" id="subir_grupo_id" name="grupo_bosquejo_id">
        <p class="text-muted mb-3">Grupo: <strong id="subir_grupo_nombre"></strong></p>

        <div class="mb-3" id="bosquejoNombreWrapper">
            <label for="bosquejo_nombre" class="form-label">
                <i class="bi bi-tag me-1"></i> Nombre del Bosquejo
            </label>
            <input type="text" id="bosquejo_nombre" name="bosquejo_nombre"
                class="form-control" placeholder="Opcional - por defecto se usa el nombre del archivo" maxlength="255">
            <small class="text-muted">Solo aplica si se sube una sola imagen.</small>
        </div>

        <div class="mb-3">
            <label for="archivo" class="form-label">
                <i class="bi bi-image me-1"></i> Imagenes <span class="text-danger">*</span>
            </label>
            <input type="file" id="archivo" name="archivo[]"
                class="form-control" required multiple accept="image/jpeg,image/png,image/webp">
            <small class="text-muted">Puede seleccionar varias. Formatos: JPG, PNG, WebP. Maximo 10MB cada una.</small>
        </div>

        <div id="multiInfo" class="alert alert-info py-2 px-3 mb-2" style="display:none;">
            <i class="bi bi-info-circle me-1"></i>
            <span id="multiInfoTexto"></span>
        </div>

        <div id="previewImagen" class="text-center mt-2" style="display: none;">
            <img id="previewImg" src="" alt="Preview" style="max-height: 200px; max-width: 100%; border-radius: 8px;">
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <x-sinden.button variant="primary" icon="bi bi-upload" id="btnSubirBosquejo" onclick="subirBosquejo()">Subir</x-sinden.button>
    </x-slot>
</x-sinden.modal>

{{-- Modal: Renombrar (compartido grupo/bosquejo) --}}
<x-sinden.modal id="modalRenombrar" title="Renombrar">
    <form id="formRenombrar" onsubmit="event.preventDefault(); guardarRenombrar();">
        <input type="hidden" id="renombrar_tipo">
        <input type="hidden" id="renombrar_id">
        <div class="mb-3">
            <label for="renombrar_nombre" class="form-label">
                <i class="bi bi-pencil me-1"></i> Nuevo Nombre <span class="text-danger">*</span>
            </label>
            <input type="text" id="renombrar_nombre" name="renombrar_nombre"
                class="form-control" required maxlength="255">
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <x-sinden.button variant="primary" icon="bi bi-check-lg" onclick="guardarRenombrar()">Guardar</x-sinden.button>
    </x-slot>
</x-sinden.modal>
@endcan

{{-- Visor de bosquejo en pantalla completa --}}
<div id="visorBosquejo" class="visor-bosquejo" role="dialog" aria-hidden="true">
    <div class="visor-bosquejo__header">
        <span id="visorBosquejoTitulo" class="visor-bosquejo__titulo"></span>
        <button type="button" class="visor-bosquejo__cerrar" aria-label="Cerrar"
                onclick="cerrarVisorBosquejo()">
            <i class="bi bi-x-lg"></i> Cerrar
        </button>
    </div>
    <div class="visor-bosquejo__canvas" id="visorBosquejoCanvas">
        <img id="visorBosquejoImg" src="" alt="" draggable="false">
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function() {
    // ===== Reabrir acordeon del grupo donde se subio el ultimo bosquejo =====
    try {
        var openGrupoId = sessionStorage.getItem('bosquejosMatrizOpenGrupo');
        if (openGrupoId) {
            sessionStorage.removeItem('bosquejosMatrizOpenGrupo');

            if (openGrupoId === 'individuales') {
                // Cerrar el primer acordeon que abre por defecto y hacer scroll a individuales
                var $primero = $('#acordeonGrupos .accordion-collapse.show').first();
                if ($primero.length && typeof bootstrap !== 'undefined') {
                    bootstrap.Collapse.getOrCreateInstance($primero[0], { toggle: false }).hide();
                    $primero.prev('.accordion-header')
                        .find('.accordion-button')
                        .addClass('collapsed')
                        .attr('aria-expanded', 'false');
                }
                var $ind = $('#seccion-individuales');
                if ($ind.length) {
                    setTimeout(function() {
                        $ind[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 200);
                }
            } else {
                var $target = $('#collapse-' + openGrupoId);
                if ($target.length && typeof bootstrap !== 'undefined') {
                    var yaAbierto = $target.hasClass('show');
                    // Cerrar el que esta abierto por defecto (si no es el target)
                    $('#acordeonGrupos .accordion-collapse.show').not($target).each(function() {
                        bootstrap.Collapse.getOrCreateInstance(this, { toggle: false }).hide();
                        $(this).prev('.accordion-header')
                            .find('.accordion-button')
                            .addClass('collapsed')
                            .attr('aria-expanded', 'false');
                    });
                    // Abrir el target solo si no esta ya abierto (evita que Bootstrap lo toggle al cerrado)
                    if (!yaAbierto) {
                        bootstrap.Collapse.getOrCreateInstance($target[0], { toggle: false }).show();
                    }
                    $target.prev('.accordion-header')
                        .find('.accordion-button')
                        .removeClass('collapsed')
                        .attr('aria-expanded', 'true');
                    // Scroll al grupo
                    setTimeout(function() {
                        var card = document.getElementById('grupo-card-' + openGrupoId);
                        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 300);
                }
            }
        }
    } catch (e) {}

    // Preview de imagen(es) al seleccionar archivo(s)
    $('#archivo').on('change', function(e) {
        var files = e.target.files;
        if (!files || files.length === 0) {
            $('#previewImagen').hide();
            $('#multiInfo').hide();
            $('#bosquejoNombreWrapper').show();
            return;
        }

        if (files.length === 1) {
            // Una sola imagen: preview + auto-llenar nombre si esta vacio
            $('#bosquejoNombreWrapper').show();
            $('#multiInfo').hide();
            var file = files[0];
            var reader = new FileReader();
            reader.onload = function(ev) {
                $('#previewImg').attr('src', ev.target.result);
                $('#previewImagen').show();
            };
            reader.readAsDataURL(file);
            if (!$('#bosquejo_nombre').val().trim()) {
                $('#bosquejo_nombre').val(file.name.replace(/\.[^/.]+$/, ''));
            }
        } else {
            // Varias imagenes: ocultar campo nombre y mostrar info
            $('#previewImagen').hide();
            $('#bosquejoNombreWrapper').hide();
            $('#multiInfoTexto').text('Se subiran ' + files.length + ' imagenes y se usara el nombre de cada archivo como nombre del bosquejo.');
            $('#multiInfo').show();
        }
    });
});

@can('gestionar_bosquejos_matriz')
// ===== CREAR GRUPO =====
function guardarGrupo() {
    var nombre = $('#grupo_nombre').val().trim();
    if (!nombre) {
        Swal.fire('Error', 'El nombre del grupo es obligatorio.', 'error');
        return;
    }

    $.ajax({
        url: '{{ route("recepcion.bosquejos-matriz.grupos.store") }}',
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ nombre: nombre }),
        success: function(data) {
            if (data.success) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                $('#modalNuevoGrupo').modal('hide');
                $('#grupo_nombre').val('');
                location.reload();
            }
        },
        error: function(xhr) {
            var msg = 'No se pudo crear el grupo.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
            }
            Swal.fire('Error', msg, 'error');
        }
    });
}

// ===== RENOMBRAR (grupo o bosquejo) =====
// ===== Renombrar la seccion de bosquejos sin grupo =====
function editarNombreGenericos() {
    var actual = ($('#tituloGenericos').text() || '').trim();
    Swal.fire({
        title: 'Nombre de la seccion',
        input: 'text',
        inputValue: actual,
        inputAttributes: { maxlength: 50 },
        inputPlaceholder: 'Ej: Genericos',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4A7C59',
        inputValidator: function(value) {
            if (!value || !value.trim()) {
                return 'El nombre es obligatorio.';
            }
        }
    }).then(function(result) {
        if (!result.isConfirmed) return;
        var nombre = result.value.trim();
        $.ajax({
            url: '{{ route("recepcion.bosquejos-matriz.nombre-genericos") }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            contentType: 'application/json',
            data: JSON.stringify({ nombre: nombre }),
            success: function(data) {
                if (data.success) {
                    $('#tituloGenericos').text(data.nombre);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'No se pudo actualizar el nombre.';
                Swal.fire('Error', msg, 'error');
            }
        });
    });
}

function abrirModalRenombrar(tipo, id, nombreActual) {
    $('#renombrar_tipo').val(tipo);
    $('#renombrar_id').val(id);
    $('#renombrar_nombre').val(nombreActual);
    $('#modalRenombrar').modal('show');
    setTimeout(function() { $('#renombrar_nombre').focus().select(); }, 500);
}

function guardarRenombrar() {
    var tipo = $('#renombrar_tipo').val();
    var id = $('#renombrar_id').val();
    var nombre = $('#renombrar_nombre').val().trim();

    if (!nombre) {
        Swal.fire('Error', 'El nombre es obligatorio.', 'error');
        return;
    }

    var url = tipo === 'grupo'
        ? '{{ url("recepcion/bosquejos-matriz/grupos") }}/' + id
        : '{{ url("recepcion/bosquejos-matriz/bosquejos") }}/' + id;

    $.ajax({
        url: url,
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        contentType: 'application/json',
        data: JSON.stringify({ nombre: nombre }),
        success: function(data) {
            if (data.success) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                $('#modalRenombrar').modal('hide');
                location.reload();
            }
        },
        error: function() {
            Swal.fire('Error', 'No se pudo renombrar.', 'error');
        }
    });
}

// ===== ELIMINAR GRUPO =====
function confirmarEliminarGrupo(grupoId, nombre, cantidadBosquejos) {
    var texto = cantidadBosquejos > 0
        ? 'Se eliminara el grupo "' + nombre + '" y sus ' + cantidadBosquejos + ' bosquejo(s). Esta accion no se puede deshacer.'
        : 'Se eliminara el grupo "' + nombre + '". Esta accion no se puede deshacer.';

    Swal.fire({
        title: 'Eliminar grupo?',
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ url("recepcion/bosquejos-matriz/grupos") }}/' + grupoId,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    if (data.success) {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                        $('#grupo-card-' + grupoId).fadeOut(300, function() { $(this).remove(); });
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo eliminar el grupo.', 'error');
                }
            });
        }
    });
}

// ===== SUBIR BOSQUEJO =====
function abrirModalSubirBosquejo(grupoId, grupoNombre) {
    $('#subir_grupo_id').val(grupoId);
    $('#subir_grupo_nombre').text(grupoNombre).show();
    $('#bosquejo_nombre').val('');
    $('#archivo').val('');
    $('#previewImagen').hide();
    $('#multiInfo').hide();
    $('#bosquejoNombreWrapper').show();
    $('#btnSubirBosquejo').prop('disabled', false).html('<i class="bi bi-upload me-1"></i>Subir');
    $('#modalSubirBosquejo').modal('show');
    setTimeout(function() { $('#archivo').focus(); }, 500);
}

function abrirModalSubirBosquejoIndividual() {
    $('#subir_grupo_id').val('');
    $('#subir_grupo_nombre').text('Sin grupo (individual)').show();
    $('#bosquejo_nombre').val('');
    $('#archivo').val('');
    $('#previewImagen').hide();
    $('#multiInfo').hide();
    $('#bosquejoNombreWrapper').show();
    $('#btnSubirBosquejo').prop('disabled', false).html('<i class="bi bi-upload me-1"></i>Subir');
    $('#modalSubirBosquejo').modal('show');
    setTimeout(function() { $('#archivo').focus(); }, 500);
}

function subirBosquejo() {
    var nombreManual = $('#bosquejo_nombre').val().trim();
    var grupoId = $('#subir_grupo_id').val();
    var archivoInput = document.getElementById('archivo');
    var files = archivoInput.files;

    if (!files || files.length === 0) {
        Swal.fire('Error', 'Debe seleccionar al menos una imagen.', 'error');
        return;
    }

    var $btn = $('#btnSubirBosquejo');
    $btn.prop('disabled', true);

    var total = files.length;
    var exitos = 0;
    var errores = [];
    var url = '{{ route("recepcion.bosquejos-matriz.bosquejos.store") }}';
    var csrf = $('meta[name="csrf-token"]').attr('content');

    function actualizarBoton(i) {
        $btn.html('<span class="spinner-border spinner-border-sm me-1"></span>Subiendo ' + i + ' de ' + total + '...');
    }

    function subirUno(index) {
        if (index >= total) {
            // Terminado
            // Recordar a que grupo se subio para reabrir su acordeon tras el reload
            if (exitos > 0) {
                try {
                    sessionStorage.setItem('bosquejosMatrizOpenGrupo', grupoId ? String(grupoId) : 'individuales');
                } catch (e) {}
            }
            if (errores.length === 0) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: exitos + ' bosquejo(s) subido(s)', showConfirmButton: false, timer: 2500 });
                $('#modalSubirBosquejo').modal('hide');
                setTimeout(function() { location.reload(); }, 600);
            } else {
                $btn.prop('disabled', false).html('<i class="bi bi-upload me-1"></i>Subir');
                Swal.fire({
                    icon: exitos > 0 ? 'warning' : 'error',
                    title: 'Subida con errores',
                    html: 'Exitosos: ' + exitos + '<br>Errores: ' + errores.length + '<br><br><small>' + errores.join('<br>') + '</small>'
                }).then(function() {
                    if (exitos > 0) location.reload();
                });
            }
            return;
        }

        actualizarBoton(index + 1);
        var file = files[index];
        var nombre;
        if (total === 1) {
            nombre = nombreManual || file.name.replace(/\.[^/.]+$/, '');
        } else {
            nombre = file.name.replace(/\.[^/.]+$/, '');
        }

        var formData = new FormData();
        formData.append('nombre', nombre);
        formData.append('archivo', file);
        if (grupoId) formData.append('grupo_bosquejo_id', grupoId);

        $.ajax({
            url: url,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (data && data.success) {
                    exitos++;
                } else {
                    errores.push(file.name + ': ' + ((data && data.message) || 'error desconocido'));
                }
                subirUno(index + 1);
            },
            error: function(xhr) {
                var msg = 'error';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                errores.push(file.name + ': ' + msg);
                subirUno(index + 1);
            }
        });
    }

    subirUno(0);
}

// ===== ELIMINAR BOSQUEJO =====
function confirmarEliminarBosquejo(bosquejoId, nombre) {
    Swal.fire({
        title: 'Eliminar bosquejo?',
        text: 'Se eliminara "' + nombre + '" y sus archivos. Esta accion no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ url("recepcion/bosquejos-matriz/bosquejos") }}/' + bosquejoId,
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    if (data.success) {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
                        $('#bosquejo-card-' + bosquejoId).fadeOut(300, function() { $(this).remove(); });
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo eliminar el bosquejo.', 'error');
                }
            });
        }
    });
}
@endcan

// ===== VISOR DE BOSQUEJO EN PANTALLA COMPLETA =====
const visorBosquejo = {
    el: null, canvas: null, img: null, titulo: null,
    scale: 1, tx: 0, ty: 0, minScale: 1, maxScale: 8,
    pointers: new Map(),
    pinchStartDist: 0, pinchStartScale: 1, pinchCenter: { x: 0, y: 0 },
    isPanning: false, panStartX: 0, panStartY: 0, panStartTx: 0, panStartTy: 0,
    lastTap: 0,

    init() {
        this.el = document.getElementById('visorBosquejo');
        if (!this.el) return;
        this.canvas = document.getElementById('visorBosquejoCanvas');
        this.img = document.getElementById('visorBosquejoImg');
        this.titulo = document.getElementById('visorBosquejoTitulo');

        // Wheel: zoom solo con Ctrl, sin Ctrl no hace nada (passive:false para preventDefault)
        this.canvas.addEventListener('wheel', (e) => this.onWheel(e), { passive: false });

        // Pointer events para pinch + pan (touch-action:none en CSS bloquea gestos nativos)
        this.canvas.addEventListener('pointerdown', (e) => this.onPointerDown(e));
        this.canvas.addEventListener('pointermove', (e) => this.onPointerMove(e));
        const up = (e) => this.onPointerUp(e);
        this.canvas.addEventListener('pointerup', up);
        this.canvas.addEventListener('pointercancel', up);
        this.canvas.addEventListener('pointerleave', up);

        // Doble clic / doble tap para resetear
        this.canvas.addEventListener('dblclick', (e) => { e.preventDefault(); this.reset(); });

        // Esc para cerrar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.el.classList.contains('is-open')) {
                this.cerrar();
            }
        });

        // Clic en el fondo (fuera de la imagen) cierra
        this.canvas.addEventListener('click', (e) => {
            if (e.target === this.canvas && this.scale === 1) this.cerrar();
        });
    },

    abrir(url, nombre) {
        if (!this.el) this.init();
        this.titulo.textContent = nombre || '';
        this.img.src = url;
        this.reset();
        this.el.classList.add('is-open');
        this.el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('visor-bosquejo-open');
    },

    cerrar() {
        if (!this.el) return;
        this.el.classList.remove('is-open');
        this.el.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('visor-bosquejo-open');
        this.reset();
        this.img.src = '';
        this.pointers.clear();
        this.isPanning = false;
    },

    aplicar() {
        this.img.style.transform = `translate(${this.tx}px, ${this.ty}px) scale(${this.scale})`;
    },

    reset() {
        this.scale = 1; this.tx = 0; this.ty = 0;
        this.aplicar();
    },

    // Zoom centrado en un punto del viewport (cx, cy)
    zoomEnPunto(cx, cy, factor) {
        const next = Math.min(this.maxScale, Math.max(this.minScale, this.scale * factor));
        if (next === this.scale) return;
        const rect = this.canvas.getBoundingClientRect();
        // Coordenadas del punto relativas al centro del canvas (donde está anclado el transform-origin)
        const px = cx - rect.left - rect.width / 2;
        const py = cy - rect.top - rect.height / 2;
        // Mantiene el punto bajo el cursor estable
        const ratio = next / this.scale;
        this.tx = px - (px - this.tx) * ratio;
        this.ty = py - (py - this.ty) * ratio;
        this.scale = next;
        if (this.scale === 1) { this.tx = 0; this.ty = 0; }
        this.aplicar();
    },

    onWheel(e) {
        if (!e.ctrlKey) return;          // solo Ctrl + rueda
        e.preventDefault();              // bloquea zoom del navegador
        const factor = e.deltaY < 0 ? 1.15 : 1 / 1.15;
        this.zoomEnPunto(e.clientX, e.clientY, factor);
    },

    onPointerDown(e) {
        if (e.target.tagName === 'BUTTON') return;
        this.canvas.setPointerCapture(e.pointerId);
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.pointers.size === 2) {
            // Inicio de pinch
            const pts = Array.from(this.pointers.values());
            this.pinchStartDist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            this.pinchStartScale = this.scale;
            this.pinchCenter = {
                x: (pts[0].x + pts[1].x) / 2,
                y: (pts[0].y + pts[1].y) / 2,
            };
            this.isPanning = false;
        } else if (this.pointers.size === 1 && this.scale > 1) {
            // Inicio de pan
            this.isPanning = true;
            this.panStartX = e.clientX;
            this.panStartY = e.clientY;
            this.panStartTx = this.tx;
            this.panStartTy = this.ty;
            this.img.classList.add('is-panning');
        }
    },

    onPointerMove(e) {
        if (!this.pointers.has(e.pointerId)) return;
        this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (this.pointers.size === 2) {
            const pts = Array.from(this.pointers.values());
            const dist = Math.hypot(pts[0].x - pts[1].x, pts[0].y - pts[1].y);
            if (this.pinchStartDist > 0) {
                const targetScale = Math.min(this.maxScale,
                    Math.max(this.minScale, this.pinchStartScale * (dist / this.pinchStartDist)));
                const factor = targetScale / this.scale;
                if (factor !== 1) {
                    this.zoomEnPunto(this.pinchCenter.x, this.pinchCenter.y, factor);
                }
            }
        } else if (this.isPanning) {
            this.tx = this.panStartTx + (e.clientX - this.panStartX);
            this.ty = this.panStartTy + (e.clientY - this.panStartY);
            this.aplicar();
        }
    },

    onPointerUp(e) {
        if (this.pointers.has(e.pointerId)) this.pointers.delete(e.pointerId);
        if (this.pointers.size < 2) this.pinchStartDist = 0;
        if (this.pointers.size === 0) {
            this.isPanning = false;
            this.img.classList.remove('is-panning');
        }
    },
};

function verBosquejo(url, nombre) { visorBosquejo.abrir(url, nombre); }
function cerrarVisorBosquejo() { visorBosquejo.cerrar(); }

$(function() { visorBosquejo.init(); });
</script>
@endpush
