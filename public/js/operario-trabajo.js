/**
 * SINDEN - Operario: Vista de Trabajo (operario-trabajo.js)
 * Maneja: sliders, fotos, transferencias, actualizar orden, heartbeat, inactividad, forzar cierre.
 */

(function() {
    'use strict';

    var piezasCambios = {};
    var heartbeatInterval = null;
    var inactivityTimer = null;
    var forceCloseInterval = null;
    var huboAccionPieza = false; // true si se hizo transferencia o dejar-en-cola durante la sesion

    // ==========================================
    // INIT
    // ==========================================
    $(function() {
        if (!LOCK_ACQUIRED) return;

        initSliders();
        initFotoUploads();
        initObservaciones();
        initTransferButtons();
        initDejarColaButtons();
        initActualizarOrden();
        startHeartbeat();
        startInactivityTimer();
        startForceCloseCheck();

        // Recuperar datos no guardados (corte de luz, cierre inesperado)
        if (window.SindenConexion) {
            var recovered = SindenConexion.loadModuleData('operario', ORDEN_ID);
            if (recovered && recovered.cambios && Object.keys(recovered.cambios).length > 0) {
                Swal.fire({
                    title: 'Datos recuperados',
                    html: 'Se encontraron <b>' + Object.keys(recovered.cambios).length + '</b> avance(s) no guardados de una sesion anterior.',
                    icon: 'info',
                    confirmButtonText: 'Aplicar',
                    showCancelButton: true,
                    cancelButtonText: 'Descartar',
                    confirmButtonColor: '#4A7C59'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        for (var pid in recovered.cambios) {
                            var val = recovered.cambios[pid];
                            piezasCambios[pid] = val;
                            $('input.pieza-slider[data-pieza-id="' + pid + '"]').val(val);
                            $('input.pieza-porcentaje-input[data-pieza-id="' + pid + '"]').val(val);
                            updatePorcentajeDisplay(pid, val);
                        }
                    }
                    SindenConexion.clearModuleData('operario', ORDEN_ID);
                });
            }
        }

        // Release lock on page unload
        $(window).on('beforeunload', function() {
            if (navigator.sendBeacon) {
                var data = new URLSearchParams({ _token: CSRF_TOKEN });
                navigator.sendBeacon(OPERARIO_ROUTES.desbloquear, data);
            }
        });
    });

    // ==========================================
    // SLIDER <-> INPUT SYNC
    // ==========================================
    function initSliders() {
        // Set initial color on all sliders
        $('.pieza-slider').each(function() {
            updateSliderColor($(this), parseInt($(this).val()));
        });

        // Slider change -> update input
        $(document).on('input', '.pieza-slider', function() {
            var piezaId = $(this).data('pieza-id');
            var val = parseInt($(this).val());
            $('input.pieza-porcentaje-input[data-pieza-id="' + piezaId + '"]').val(val);
            updatePorcentajeDisplay(piezaId, val);
            trackCambio(piezaId, val);
        });

        // Input change -> update slider
        $(document).on('input', '.pieza-porcentaje-input', function() {
            var piezaId = $(this).data('pieza-id');
            var val = Math.max(0, Math.min(100, parseInt($(this).val()) || 0));
            $('input.pieza-slider[data-pieza-id="' + piezaId + '"]').val(val);
            updatePorcentajeDisplay(piezaId, val);
            trackCambio(piezaId, val);
        });
    }

    function getSliderColor(val) {
        if (val >= 100) return '#198754'; // green
        if (val >= 75) return '#20c997';  // teal
        if (val >= 50) return '#ffc107';  // yellow
        if (val >= 25) return '#fd7e14';  // orange
        if (val > 0) return '#0dcaf0';    // cyan
        return '#adb5bd';                 // gray
    }

    function updateSliderColor(slider, val) {
        slider.css('--slider-color', getSliderColor(val));
        slider.css('--slider-pct', val + '%');
    }

    function updatePorcentajeDisplay(piezaId, val) {
        var card = $('#pieza-' + piezaId);
        var display = card.find('.pieza-porcentaje-display');
        display.text(val + '%');

        // Update text color
        display.removeClass('text-success text-warning text-info text-muted');
        if (val >= 100) display.addClass('text-success');
        else if (val >= 50) display.addClass('text-warning');
        else if (val > 0) display.addClass('text-info');
        else display.addClass('text-muted');

        // Update slider color
        var slider = card.find('.pieza-slider');
        updateSliderColor(slider, val);
    }

    function trackCambio(piezaId, porcentaje) {
        var original = parseFloat($('#pieza-' + piezaId).data('porcentaje-original'));
        if (Math.abs(porcentaje - original) < 0.5) {
            delete piezasCambios[piezaId];
        } else {
            piezasCambios[piezaId] = porcentaje;
        }

        // Backup en localStorage para recuperacion ante corte de luz
        if (window.SindenConexion) {
            SindenConexion.saveModuleData('operario', ORDEN_ID, {
                cambios: piezasCambios,
                timestamp: Date.now()
            });
        }
    }

    // ==========================================
    // FOTO UPLOAD
    // ==========================================
    function initFotoUploads() {
        $(document).on('change', '.foto-input', function() {
            var input = this;
            var piezaId = $(this).data('pieza-id');
            var file = input.files[0];

            if (!file) return;

            var reader = new FileReader();
            reader.onload = function(e) {
                Swal.fire({
                    title: 'Esta bien la foto?',
                    imageUrl: e.target.result,
                    imageWidth: 300,
                    imageAlt: 'Preview',
                    showCancelButton: true,
                    confirmButtonText: 'Aceptar',
                    cancelButtonText: 'Repetir',
                    confirmButtonColor: '#4A7C59'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        subirFotoAjax(piezaId, file);
                    }
                    // Clear input para permitir seleccionar la misma foto
                    $(input).val('');
                });
            };
            reader.readAsDataURL(file);
        });
    }

    function subirFotoAjax(piezaId, file) {
        var formData = new FormData();
        formData.append('_token', CSRF_TOKEN);
        formData.append('foto', file);

        $.ajax({
            url: '/operario/piezas/' + piezaId + '/foto',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (data.success) {
                    showToast('success', 'Foto subida', 'La foto se adjunto correctamente.');
                    // Add clickable thumbnail to preview area
                    var url = data.foto.url;
                    var previewHtml = '<img src="' + url + '" class="foto-thumb rounded border cursor-pointer" ' +
                        'style="width:60px;height:60px;object-fit:cover;" ' +
                        'onclick="abrirLightbox(\'' + url + '\', \'Foto de avance\')" alt="Foto de avance">';
                    $('#fotoPreview' + piezaId).append(previewHtml);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo subir la foto.' });
                }
            },
            error: function(xhr) {
                var msg = 'No se pudo subir la foto.';
                if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.foto) {
                    msg = xhr.responseJSON.errors.foto[0];
                }
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    }

    // ==========================================
    // OBSERVACIONES
    // ==========================================
    function initObservaciones() {
        $(document).on('click', '.btn-observacion', function() {
            var piezaId = $(this).data('pieza-id');
            var piezaNombre = $(this).data('pieza-nombre');

            Swal.fire({
                title: 'Observacion',
                html: '<p class="text-muted small mb-2">' + piezaNombre + '</p>',
                input: 'textarea',
                inputPlaceholder: 'Escribe la observacion de esta pieza...',
                inputAttributes: { 'maxlength': 2000, 'rows': 4 },
                showCancelButton: true,
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4A7C59',
                inputValidator: function(value) {
                    if (!value || !value.trim()) {
                        return 'Debes escribir una observacion.';
                    }
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    guardarObservacionAjax(piezaId, result.value.trim());
                }
            });
        });
    }

    function guardarObservacionAjax(piezaId, texto) {
        $.ajax({
            url: '/operario/piezas/' + piezaId + '/observacion',
            method: 'POST',
            data: { _token: CSRF_TOKEN, observacion: texto },
            success: function(data) {
                if (data.success) {
                    showToast('success', 'Observacion guardada', 'La observacion se registro correctamente.');

                    var obs = data.observacion;
                    var itemHtml = '<div class="timeline-item">' +
                        '<strong>' + escapeHtml(obs.usuario) + '</strong> ' +
                        '<small class="text-muted">(' + obs.fecha + ')</small><br>' +
                        '<small><i class="bi bi-chat-text me-1"></i>' + escapeHtml(obs.texto) + '</small>' +
                        '</div>';
                    // Prepend (mas reciente primero)
                    $('#obsList' + piezaId).prepend(itemHtml);

                    // Mostrar toggle y actualizar contador
                    var wrapper = $('#obsWrapper' + piezaId);
                    var toggle = wrapper.find('.obs-toggle');
                    toggle.removeClass('d-none');
                    var countEl = toggle.find('.obs-count');
                    countEl.text((parseInt(countEl.text(), 10) || 0) + 1);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo guardar la observacion.' });
                }
            },
            error: function(xhr) {
                var msg = 'No se pudo guardar la observacion.';
                if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.observacion) {
                    msg = xhr.responseJSON.errors.observacion[0];
                }
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    }

    function escapeHtml(str) {
        return $('<div>').text(str == null ? '' : str).html();
    }

    // ==========================================
    // TRANSFERIR PIEZA
    // ==========================================
    function initTransferButtons() {
        $(document).on('click', '.btn-transferir', function() {
            var piezaId = $(this).data('pieza-id');
            var piezaNombre = $(this).data('pieza-nombre');

            $('#transferirPiezaId').val(piezaId);
            $('#transferirPiezaNombre').text(piezaNombre);
            $('#transferirOperarioSelect').val('');
            $('#transferirNotas').val('');

            new bootstrap.Modal(document.getElementById('modalTransferir')).show();
        });

        $('#btnConfirmarTransferencia').on('click', function() {
            var piezaId = $('#transferirPiezaId').val();
            var operarioId = $('#transferirOperarioSelect').val();
            var notas = $('#transferirNotas').val();

            if (!operarioId) {
                Swal.fire({ icon: 'warning', title: 'Selecciona un operario', text: 'Debes elegir a quien transferir la pieza.' });
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true);

            function ejecutarTransferencia() {
                $.ajax({
                    url: '/operario/piezas/' + piezaId + '/transferir',
                    method: 'POST',
                    data: {
                        _token: CSRF_TOKEN,
                        nuevo_operario_id: operarioId,
                        notas: notas
                    },
                success: function(data) {
                    bootstrap.Modal.getInstance(document.getElementById('modalTransferir')).hide();
                    btn.prop('disabled', false);

                    if (data.success) {
                        huboAccionPieza = true;
                        showToast('success', 'Pieza transferida', 'La pieza fue transferida a ' + data.nuevo_operario);
                        $('#pieza-' + piezaId).fadeOut(400, function() { $(this).remove(); });
                        delete piezasCambios[piezaId];
                        checkPiezasRestantes();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo transferir.' });
                    }
                },
                error: function() {
                    btn.prop('disabled', false);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo completar la transferencia.' });
                }
                });
            }

            // Si hay cambios pendientes en esta pieza, guardar primero
            if (piezasCambios[piezaId] !== undefined) {
                var cambios = [{ pieza_id: parseInt(piezaId), porcentaje: piezasCambios[piezaId] }];
                enviarActualizacionSilenciosa(cambios, function() {
                    $('#pieza-' + piezaId).data('porcentaje-original', piezasCambios[piezaId]);
                    delete piezasCambios[piezaId];
                    ejecutarTransferencia();
                });
            } else {
                ejecutarTransferencia();
            }
        });

        // ---- Transferencia MASIVA (todas las piezas del operario a un mismo operario) ----
        $(document).on('click', '#btnTransferirMasivo', function() {
            $('#transferirMasivoOperarioSelect').val('');
            $('#transferirMasivoNotas').val('');
            new bootstrap.Modal(document.getElementById('modalTransferirMasivo')).show();
        });

        $('#btnConfirmarTransferenciaMasiva').on('click', function() {
            var operarioId = $('#transferirMasivoOperarioSelect').val();
            var notas = $('#transferirMasivoNotas').val();

            if (!operarioId) {
                Swal.fire({ icon: 'warning', title: 'Selecciona un operario', text: 'Debes elegir a quien transferir todas las piezas.' });
                return;
            }

            var operarioNombre = $('#transferirMasivoOperarioSelect option:selected').text();
            var btn = $(this);

            Swal.fire({
                title: 'Transferir TODAS las piezas?',
                html: 'Se transferiran <b>todas tus piezas</b> de esta orden a <b>' + operarioNombre + '</b>.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, transferir todas',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#f0ad4e'
            }).then(function(result) {
                if (!result.isConfirmed) return;
                btn.prop('disabled', true);

                function ejecutarMasivo() {
                    $.ajax({
                        url: '/operario/ordenes/' + ORDEN_ID + '/transferir-masivo',
                        method: 'POST',
                        data: { _token: CSRF_TOKEN, nuevo_operario_id: operarioId, notas: notas },
                        success: function(data) {
                            btn.prop('disabled', false);
                            if (data.success) {
                                huboAccionPieza = true;
                                var inst = bootstrap.Modal.getInstance(document.getElementById('modalTransferirMasivo'));
                                if (inst) inst.hide();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Piezas transferidas',
                                    text: data.cantidad + ' pieza(s) transferida(s) a ' + data.nuevo_operario + '.',
                                    timer: 2500,
                                    showConfirmButton: false
                                }).then(function() {
                                    window.location.href = '/operario/ordenes-asignadas';
                                });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo transferir.' });
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false);
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo completar la transferencia masiva.' });
                        }
                    });
                }

                // Guardar primero cualquier avance pendiente (de cualquier pieza) antes de transferir
                var cambios = [];
                for (var pid in piezasCambios) {
                    if (piezasCambios.hasOwnProperty(pid) && piezasCambios[pid] !== undefined) {
                        cambios.push({ pieza_id: parseInt(pid), porcentaje: piezasCambios[pid] });
                    }
                }
                if (cambios.length > 0) {
                    enviarActualizacionSilenciosa(cambios, function() {
                        cambios.forEach(function(c) { delete piezasCambios[c.pieza_id]; });
                        ejecutarMasivo();
                    });
                } else {
                    ejecutarMasivo();
                }
            });
        });
    }

    // ==========================================
    // DEJAR EN COLA
    // ==========================================
    function initDejarColaButtons() {
        $(document).on('click', '.btn-dejar-cola', function() {
            var piezaId = $(this).data('pieza-id');
            var piezaNombre = $(this).data('pieza-nombre');

            Swal.fire({
                title: 'Dejar en pendiente por terminar?',
                html: 'La pieza <b>' + piezaNombre + '</b> quedara disponible para que otro operario la tome.<br>'
                    + '<span class="text-muted small">Indica que falta por hacer para guiar al proximo operario.</span>',
                icon: 'question',
                input: 'textarea',
                inputLabel: 'Que falta por hacer? (obligatorio)',
                inputPlaceholder: 'Ej: falta rolar, falta cortar, falta soldar...',
                inputAttributes: { maxlength: 500 },
                showCancelButton: true,
                confirmButtonText: 'Si, dejar en pendiente',
                cancelButtonText: 'Cancelar',
                inputValidator: function(value) {
                    if (!value || !value.trim()) {
                        return 'Debe indicar que falta por hacer en la pieza.';
                    }
                }
            }).then(function(result) {
                if (result.isConfirmed) {
                    var notasFalta = (result.value || '').trim();
                    function ejecutarDejarCola() {
                        $.ajax({
                            url: '/operario/piezas/' + piezaId + '/dejar-cola',
                            method: 'POST',
                            data: { _token: CSRF_TOKEN, notas: notasFalta },
                            success: function(data) {
                                if (data.success) {
                                    huboAccionPieza = true;
                                    showToast('success', 'Pieza liberada', 'La pieza fue dejada en pendiente por terminar.');
                                    $('#pieza-' + piezaId).fadeOut(400, function() { $(this).remove(); });
                                    delete piezasCambios[piezaId];
                                    checkPiezasRestantes();
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudo liberar la pieza.' });
                                }
                            },
                            error: function() {
                                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo completar la operacion.' });
                            }
                        });
                    }

                    // Si hay cambios pendientes en esta pieza, guardar primero
                    if (piezasCambios[piezaId] !== undefined) {
                        var cambios = [{ pieza_id: parseInt(piezaId), porcentaje: piezasCambios[piezaId] }];
                        enviarActualizacionSilenciosa(cambios, function() {
                            delete piezasCambios[piezaId];
                            ejecutarDejarCola();
                        });
                    } else {
                        ejecutarDejarCola();
                    }
                }
            });
        });
    }

    // ==========================================
    // ACTUALIZAR ORDEN (MAIN SAVE)
    // ==========================================
    function initActualizarOrden() {
        $('#btnActualizarOrden').on('click', function() {
            var cambios = recopilarCambios();

            if (cambios.length === 0) {
                // Si ya se hizo alguna accion sobre piezas (transferencia o dejar en cola),
                // no mostrar el dialogo "Sin cambios": el trabajo ya fue registrado.
                if (huboAccionPieza) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Trabajo registrado',
                        text: 'Las acciones sobre las piezas ya fueron guardadas.',
                        confirmButtonText: 'Continuar',
                        confirmButtonColor: '#4A7C59'
                    }).then(function() {
                        window.location.href = OPERARIO_ROUTES.ordenesAsignadas;
                    });
                    return;
                }

                Swal.fire({
                    icon: 'info',
                    title: 'Sin cambios',
                    text: 'No se modifico porcentaje de alguna pieza, esta seguro que no hizo algun avance?',
                    showCancelButton: true,
                    confirmButtonText: 'Si, no hice cambios',
                    cancelButtonText: 'Volver a revisar'
                });
                return;
            }

            // Check for pieces at 100% or decreased
            var piezasTerminadas = [];
            var piezasDisminuidas = [];

            cambios.forEach(function(c) {
                var original = parseFloat($('#pieza-' + c.pieza_id).data('porcentaje-original'));
                if (c.porcentaje >= 100) {
                    var nombre = $('#pieza-' + c.pieza_id).find('.fw-bold').first().text().trim();
                    piezasTerminadas.push(nombre);
                }
                if (c.porcentaje < original) {
                    var nombre = $('#pieza-' + c.pieza_id).find('.fw-bold').first().text().trim();
                    piezasDisminuidas.push(nombre + ' (' + Math.round(original) + '% → ' + c.porcentaje + '%)');
                }
            });

            // Verificar si TODAS las piezas de la orden quedarian al 100%
            var misPiezasAl100 = 0;
            $('.pieza-trabajo').each(function() {
                var pId = $(this).data('pieza-id');
                // Usar el valor del cambio si existe, sino el del slider actual
                var val = piezasCambios[pId] !== undefined ? piezasCambios[pId] : parseInt($(this).find('.pieza-slider').val());
                if (val >= 100) misPiezasAl100++;
            });
            var totalAl100 = PIEZAS_OTROS_100 + misPiezasAl100;
            var ordenCompleta = totalAl100 >= TOTAL_PIEZAS_ORDEN;

            if (piezasDisminuidas.length > 0) {
                Swal.fire({
                    title: 'Disminuir porcentaje?',
                    html: 'Esta seguro de bajar el avance de:<br><b>' + piezasDisminuidas.join('<br>') + '</b>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Si, disminuir',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                }).then(function(r) {
                    if (!r.isConfirmed) return;

                    if (ordenCompleta && piezasTerminadas.length > 0) {
                        Swal.fire({
                            title: 'Orden Completada?',
                            html: 'Esta seguro de colocar la Orden <b>' + NUMERO_ORDEN + '</b> como EJECUTADA?<br><br>Todas las piezas quedaran al 100%.',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Si, Ejecutar Orden',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#28a745'
                        }).then(function(r2) { if (r2.isConfirmed) enviarActualizacion(cambios); });
                    } else if (piezasTerminadas.length > 0) {
                        Swal.fire({
                            title: 'Piezas Terminadas',
                            html: 'Esta seguro de colocar terminada(s) esta(s) pieza(s)?<br><b>' + piezasTerminadas.join(', ') + '</b>',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Si, Actualizar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#4A7C59'
                        }).then(function(r2) { if (r2.isConfirmed) enviarActualizacion(cambios); });
                    } else {
                        enviarActualizacion(cambios);
                    }
                });
            } else if (ordenCompleta && piezasTerminadas.length > 0) {
                Swal.fire({
                    title: 'Orden Completada?',
                    html: 'Esta seguro de colocar la Orden <b>' + NUMERO_ORDEN + '</b> como EJECUTADA?<br><br>Todas las piezas quedaran al 100%.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Si, Ejecutar Orden',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#28a745'
                }).then(function(r) { if (r.isConfirmed) enviarActualizacion(cambios); });
            } else if (piezasTerminadas.length > 0) {
                Swal.fire({
                    title: 'Piezas Terminadas',
                    html: 'Esta seguro de colocar terminada(s) esta(s) pieza(s)?<br><b>' + piezasTerminadas.join(', ') + '</b>',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Si, Actualizar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#4A7C59'
                }).then(function(r) { if (r.isConfirmed) enviarActualizacion(cambios); });
            } else {
                // Normal update
                enviarActualizacion(cambios);
            }
        });
    }

    function recopilarCambios() {
        var cambios = [];
        for (var piezaId in piezasCambios) {
            cambios.push({
                pieza_id: parseInt(piezaId),
                porcentaje: piezasCambios[piezaId]
            });
        }
        return cambios;
    }

    function enviarActualizacion(cambios) {
        var btn = $('#btnActualizarOrden');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Actualizando...');

        $.ajax({
            url: OPERARIO_ROUTES.actualizarAvances,
            method: 'POST',
            data: JSON.stringify({ _token: CSRF_TOKEN, cambios: cambios }),
            contentType: 'application/json',
            success: function(data) {
                btn.prop('disabled', false).html('<i class="bi bi-check-lg me-2"></i>ACTUALIZAR ORDEN');

                if (data.success) {
                    // Update originals
                    cambios.forEach(function(c) {
                        $('#pieza-' + c.pieza_id).data('porcentaje-original', c.porcentaje);
                    });
                    piezasCambios = {};

                    // Limpiar backup de localStorage (datos guardados exitosamente)
                    if (window.SindenConexion) {
                        SindenConexion.clearModuleData('operario', ORDEN_ID);
                    }

                    var msg = data.piezas_actualizadas + ' pieza(s) actualizada(s).';
                    if (data.piezas_terminadas && data.piezas_terminadas.length > 0) {
                        msg += ' Terminadas: ' + data.piezas_terminadas.join(', ') + '.';
                    }

                    showToast('success', 'Avances guardados', msg);

                    if (data.orden_ejecutada) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Orden Ejecutada!',
                            text: 'La orden ' + NUMERO_ORDEN + ' ha sido marcada como EJECUTADA.',
                            confirmButtonColor: '#28a745'
                        }).then(function() {
                            window.location.href = OPERARIO_ROUTES.ordenesAsignadas;
                        });
                    } else {
                        // Reload to show updated historial
                        setTimeout(function() { location.reload(); }, 1500);
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'No se pudieron guardar los avances.' });
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="bi bi-check-lg me-2"></i>ACTUALIZAR ORDEN');
                var msg = 'No se pudieron guardar los avances.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    }

    // ==========================================
    // HEARTBEAT
    // ==========================================
    function startHeartbeat() {
        heartbeatInterval = setInterval(function() {
            $.post(OPERARIO_ROUTES.heartbeat, { _token: CSRF_TOKEN });
        }, 30000); // Every 30 seconds
    }

    // ==========================================
    // INACTIVITY TIMER
    // ==========================================
    function startInactivityTimer() {
        // Desactivado - sesion infinita
    }

    // ==========================================
    // FORCE CLOSE CHECK
    // ==========================================
    function startForceCloseCheck() {
        forceCloseInterval = setInterval(function() {
            $.get(OPERARIO_ROUTES.estadoBloqueo).done(function(data) {
                if (data.force_close) {
                    clearInterval(forceCloseInterval);
                    clearInterval(heartbeatInterval);

                    var seconds = data.force_close_seconds_remaining || 60;

                    Swal.fire({
                        title: 'Cierre Requerido',
                        html: 'Un usuario de mayor jerarquia necesita editar esta orden.<br>Se cerrara en <b id="forceCountdown">' + seconds + '</b> segundos.<br><br>Su progreso se guardara automaticamente.',
                        icon: 'warning',
                        timer: seconds * 1000,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        showConfirmButton: true,
                        confirmButtonText: 'Cerrar ahora'
                    }).then(function() {
                        // Save progress before leaving
                        var cambios = recopilarCambios();
                        if (cambios.length > 0) {
                            enviarActualizacionSilenciosa(cambios, function() {
                                cerrarSesionTrabajo('La orden fue cerrada por el sistema. Su progreso se guardo automaticamente.');
                            });
                        } else {
                            cerrarSesionTrabajo('La orden fue cerrada por el sistema.');
                        }
                    });

                    // Countdown animation
                    var countdownInterval = setInterval(function() {
                        seconds--;
                        var el = document.getElementById('forceCountdown');
                        if (el) el.textContent = Math.max(0, seconds);
                        if (seconds <= 0) clearInterval(countdownInterval);
                    }, 1000);

                } else if (data.force_closed) {
                    // Cierre forzado por superior con timeout vencido
                    clearInterval(forceCloseInterval);
                    cerrarSesionTrabajo('La sesion de trabajo fue cerrada.');
                } else if (!data.locked) {
                    // Lock liberado externamente (otro operario salio, superior lo libero, etc.)
                    // Reintentar adquirirlo silenciosamente para no interrumpir el trabajo.
                    $.post(OPERARIO_ROUTES.bloquear, { _token: CSRF_TOKEN });
                }
            });
        }, 10000); // Check every 10 seconds
    }

    // ==========================================
    // HELPERS
    // ==========================================
    function enviarActualizacionSilenciosa(cambios, callback) {
        $.ajax({
            url: OPERARIO_ROUTES.actualizarAvances,
            method: 'POST',
            data: JSON.stringify({ _token: CSRF_TOKEN, cambios: cambios }),
            contentType: 'application/json',
            complete: function() {
                if (callback) callback();
            }
        });
    }

    function cerrarSesionTrabajo(mensaje) {
        $.post(OPERARIO_ROUTES.desbloquear, { _token: CSRF_TOKEN }, function() {
            Swal.fire({
                icon: 'info',
                title: 'Sesion cerrada',
                text: mensaje,
                confirmButtonColor: '#4A7C59'
            }).then(function() {
                window.location.href = OPERARIO_ROUTES.ordenesAsignadas;
            });
        });
    }

    function checkPiezasRestantes() {
        if ($('.pieza-trabajo:visible').length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Sin piezas',
                text: 'Ya no tienes piezas asignadas en esta orden.',
                confirmButtonColor: '#4A7C59'
            }).then(function() {
                window.location.href = OPERARIO_ROUTES.ordenesAsignadas;
            });
        }
    }

})();
