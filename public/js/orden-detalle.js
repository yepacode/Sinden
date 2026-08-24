/**
 * SINDEN - Orden Detalle
 * JavaScript para la vista show de ordenes.
 */

// ==========================================
// Copiar Orden
// ==========================================
function copiarOrden(ordenId) {
    Swal.fire({
        title: 'Copiar Orden?',
        text: 'Se creara un nuevo borrador con los datos de esta orden.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4A7C59',
        confirmButtonText: 'Si, Copiar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Copiando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

        $.ajax({
            url: ROUTES_DETALLE.copiar,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Orden Copiada',
                        text: response.message || 'Se ha creado un nuevo borrador.',
                        confirmButtonColor: '#4A7C59'
                    }).then(function() {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    });
                }
            },
            error: function(xhr) {
                var msg = 'Error al copiar la orden.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
}

// ==========================================
// Anular Orden
// ==========================================
function abrirModalAnular() {
    $('#motivoAnulacion').val('');
    $('#modalAnularOrden').modal('show');
}

function confirmarAnulacion() {
    var motivo = $('#motivoAnulacion').val().trim();
    if (!motivo) {
        Swal.fire('Error', 'Debe ingresar un motivo para anular la orden.', 'error');
        return;
    }

    $('#btnConfirmarAnular').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Anulando...');

    $.ajax({
        url: ROUTES_DETALLE.anular,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        contentType: 'application/json',
        data: JSON.stringify({ motivo: motivo }),
        success: function(response) {
            if (response.success) {
                $('#modalAnularOrden').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Orden Anulada',
                    text: response.message || 'La orden ha sido anulada.',
                    confirmButtonColor: '#4A7C59'
                }).then(function() {
                    window.location.reload();
                });
            }
        },
        error: function(xhr) {
            var msg = 'Error al anular la orden.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        },
        complete: function() {
            $('#btnConfirmarAnular').prop('disabled', false).html('<i class="bi bi-x-circle me-1"></i> Confirmar Anulacion');
        }
    });
}

// ==========================================
// Eliminar Borrador
// ==========================================
function eliminarBorrador() {
    Swal.fire({
        title: 'Borrar borrador?',
        html: 'Esta accion eliminara permanentemente este borrador y todos sus items, bosquejos, piezas y archivos adjuntos.<br><br><span class="text-danger fw-semibold">No se puede deshacer.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Si, borrar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Borrando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

        $.ajax({
            url: ROUTES_DETALLE.destroy,
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Borrador eliminado',
                        text: response.message || 'El borrador ha sido eliminado.',
                        confirmButtonColor: '#4A7C59'
                    }).then(function() {
                        window.location.href = ROUTES_DETALLE.index;
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message || 'No se pudo eliminar.' });
                }
            },
            error: function(xhr) {
                var msg = 'Error al eliminar el borrador.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
}

// ==========================================
// Registrar Pago
// ==========================================
function registrarPago() {
    var monto = parseFloat($('#pagoMonto').val()) || 0;
    var metodo = $('#pagoMetodo').val();
    var referencia = $('#pagoReferencia').val().trim();

    if (monto <= 0) {
        Swal.fire('Error', 'El monto debe ser mayor a 0.', 'error');
        return;
    }

    var saldoMax = (typeof ORDEN_SALDO_DISPONIBLE !== 'undefined') ? parseFloat(ORDEN_SALDO_DISPONIBLE) : 0;
    if (monto > saldoMax + 0.005) {
        Swal.fire('Monto excede el saldo', 'El maximo permitido es $' + saldoMax.toLocaleString('en-US') + '.', 'warning');
        return;
    }

    $('#btnRegistrarPago').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Registrando...');

    $.ajax({
        url: ROUTES_DETALLE.pagos,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        contentType: 'application/json',
        data: JSON.stringify({
            monto: monto,
            metodo_pago: metodo,
            referencia_pago: referencia || null
        }),
        success: function(response) {
            if (response.success) {
                $('#modalAgregarPago').modal('hide');
                $('#pagoMonto').val('');
                $('#pagoReferencia').val('');
                $('#pagoMetodo').prop('selectedIndex', 0);

                // Append pago to list
                var pago = response.pago;
                var badgeAprobado = pago.aprobado
                    ? '<span class="badge bg-success ms-1 small">Aprobado</span>'
                    : '<span class="badge bg-warning text-dark ms-1 small">Pendiente</span>';

                var etiquetaMetodo = pago.metodo_pago;
                if (window.TIPOS_PAGO_MAPA && window.TIPOS_PAGO_MAPA[pago.metodo_pago]) {
                    var _tp = window.TIPOS_PAGO_MAPA[pago.metodo_pago];
                    etiquetaMetodo = (_tp.etiqueta) ? _tp.etiqueta : (_tp.codigo + ' - ' + _tp.nombre);
                } else {
                    etiquetaMetodo = ucfirst(pago.metodo_pago);
                }

                var pagoHtml = '<div class="d-flex justify-content-between align-items-start py-2 border-bottom">'
                    + '<div>'
                    + '  <span class="fw-semibold">' + pago.monto + '</span>'
                    + '  <span class="badge bg-light text-dark border ms-1 small">' + etiquetaMetodo + '</span>'
                    + '  ' + badgeAprobado
                    + '  <div class="text-muted small">' + (pago.registrado_por || '-') + ' - Ahora</div>'
                    + (pago.referencia_pago ? '  <div class="text-muted small">Ref: ' + pago.referencia_pago + '</div>' : '')
                    + '</div>'
                    + '</div>';

                $('#sinPagos').hide();
                $('#listaPagos').prepend(pagoHtml);

                // Update totals (seccion pagos + header)
                if (response.nuevo_total_pagado !== undefined) {
                    $('#totalPagadoDisplay').text(response.nuevo_total_pagado);
                    $('#saldoDisplay').text(response.nuevo_saldo || '$0');
                    var saldoClass = (response.estado_pago === 'saldo_pendiente') ? 'text-danger' : 'text-success';
                    $('#saldoDisplay').removeClass('text-danger text-success').addClass(saldoClass);

                    // Actualizar header superior
                    $('#headerPagado').text(response.nuevo_total_pagado);
                    $('#headerSaldo').text(response.nuevo_saldo || '$0');
                    $('#headerSaldo').removeClass('text-danger text-success').addClass(saldoClass);

                    // Actualizar badge de estado pago
                    if (response.estado_pago === 'pagado') {
                        $('#headerBadgePago').attr('class', 'status-badge success').text('PAGADO');
                    } else if (response.estado_pago === 'saldo_pendiente') {
                        $('#headerBadgePago').attr('class', 'status-badge danger').text('SALDO PEND.');
                    }
                }

                // Actualizar saldo disponible para validar el siguiente pago en esta sesion
                if (typeof ORDEN_SALDO_DISPONIBLE !== 'undefined') {
                    ORDEN_SALDO_DISPONIBLE = Math.max(0, parseFloat(ORDEN_SALDO_DISPONIBLE) - monto);
                }

                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: 'Pago registrado', showConfirmButton: false, timer: 2000 });
            }
        },
        error: function(xhr) {
            var msg = 'Error al registrar el pago.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        },
        complete: function() {
            $('#btnRegistrarPago').prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i> Registrar');
        }
    });
}

// ==========================================
// Agregar Comentario
// ==========================================
$(function() {
    $('#btnAgregarComentario').on('click', function() {
        agregarComentario();
    });

    $('#nuevoComentario').on('keypress', function(e) {
        if (e.which === 13) agregarComentario();
    });
});

function agregarComentario() {
    var contenido = $('#nuevoComentario').val().trim();
    if (!contenido) return;

    $('#btnAgregarComentario').prop('disabled', true);

    $.ajax({
        url: ROUTES_DETALLE.comentarios,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        contentType: 'application/json',
        data: JSON.stringify({ contenido: contenido }),
        success: function(response) {
            if (response.success) {
                var c = response.comentario;
                var html = '<div class="comment-item">'
                    + '<div class="d-flex justify-content-between">'
                    + '  <span class="comment-author">' + (c.usuario || '-') + '</span>'
                    + '  <span class="comment-date">Ahora</span>'
                    + '</div>'
                    + '<div class="comment-content">' + escapeHtmlDetalle(c.contenido) + '</div>'
                    + '</div>';

                $('#sinComentarios').hide();
                $('#listaComentarios').prepend(html);
                $('#nuevoComentario').val('');
            }
        },
        error: function(xhr) {
            var msg = 'Error al agregar comentario.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        },
        complete: function() {
            $('#btnAgregarComentario').prop('disabled', false);
        }
    });
}

// ==========================================
// Lightbox
// ==========================================
function abrirLightbox(rutaArchivo, titulo) {
    var src = rutaArchivo;
    if (src && !src.startsWith('http') && !src.startsWith('/')) {
        src = '/' + src;
    }
    $('#lightboxImage').attr('src', src);
    $('#lightboxTitle').text(titulo || 'Imagen');
    $('#lightboxModal').modal('show');
}

// ==========================================
// Garantias
// ==========================================
function abrirModalGarantia() {
    var $select = $('#garantiaPiezaId');
    var $operarioSelect = $('#garantiaOperarioId');
    $select.html('<option value="">Cargando piezas...</option>');
    $('#garantiaCantidad').val(1).attr('max', 1);
    $('#garantiaMotivo').val('');
    $('#garantiaCobrable').prop('checked', false);
    $('#garantiaMontoCobro').val('');
    $('#garantiaPiezaInfo').text('');
    $('#garantiaLoading').removeClass('d-none');
    $('#garantiaForm').addClass('d-none');
    $('#modalRegistrarGarantia').modal('show');

    // Cargar piezas y operarios en paralelo
    var piezasReq = $.ajax({
        url: ROUTES_DETALLE.garantiasPiezas,
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    });

    var operariosReq = $.ajax({
        url: ROUTES_DETALLE.operarios,
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    });

    $.when(piezasReq, operariosReq).done(function(piezasRes, operariosRes) {
        var piezas = piezasRes[0];
        var operariosData = operariosRes[0];
        var operarios = operariosData.operarios || operariosData;

        $select.html('<option value="">Seleccione una pieza...</option>');
        if (Array.isArray(piezas)) {
            piezas.forEach(function(p) {
                $select.append('<option value="' + p.id + '" data-disponible="' + p.disponible_garantia + '">'
                    + p.nombre + ' (Entregadas: ' + p.cantidad_entregada + ', Disponible: ' + p.disponible_garantia + ')'
                    + '</option>');
            });
        }

        $operarioSelect.html('<option value="">Sin asignar por ahora</option>');
        if (Array.isArray(operarios)) {
            operarios.forEach(function(op) {
                $operarioSelect.append('<option value="' + op.id + '">' + op.name + '</option>');
            });
        }

        $('#garantiaLoading').addClass('d-none');
        $('#garantiaForm').removeClass('d-none');
    }).fail(function() {
        $('#garantiaLoading').addClass('d-none');
        $('#garantiaForm').removeClass('d-none');
        $select.html('<option value="">Error al cargar piezas</option>');
    });

    // Actualizar max al cambiar pieza
    $select.off('change.garantia').on('change.garantia', function() {
        var $opt = $(this).find(':selected');
        var disponible = parseInt($opt.data('disponible')) || 0;
        $('#garantiaCantidad').attr('max', disponible).val(Math.min(1, disponible));
        if (disponible > 0) {
            $('#garantiaPiezaInfo').text('Maximo: ' + disponible + ' unidad(es)');
        } else {
            $('#garantiaPiezaInfo').text('');
        }
    });
}

function registrarGarantia() {
    var piezaId = $('#garantiaPiezaId').val();
    var cantidad = parseInt($('#garantiaCantidad').val()) || 0;
    var motivo = $('#garantiaMotivo').val().trim();
    var cobrable = $('#garantiaCobrable').is(':checked');
    var montoCobro = parseFloat($('#garantiaMontoCobro').val()) || 0;
    var operarioId = $('#garantiaOperarioId').val();

    if (!piezaId) {
        Swal.fire('Error', 'Seleccione una pieza.', 'error');
        return;
    }
    if (cantidad <= 0) {
        Swal.fire('Error', 'La cantidad debe ser mayor a 0.', 'error');
        return;
    }
    if (!motivo) {
        Swal.fire('Error', 'Debe ingresar el motivo de la devolucion.', 'error');
        return;
    }

    var data = {
        orden_pieza_id: piezaId,
        cantidad_devuelta: cantidad,
        motivo: motivo,
        cobrable: cobrable ? 1 : 0,
        monto_cobro: cobrable ? montoCobro : null,
        operario_asignado_id: operarioId || null
    };

    Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: function() { Swal.showLoading(); } });

    $.ajax({
        url: ROUTES_DETALLE.garantiasStore,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                $('#modalRegistrarGarantia').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Garantia Registrada',
                    text: response.message,
                    confirmButtonColor: '#4A7C59'
                }).then(function() {
                    window.location.reload();
                });
            }
        },
        error: function(xhr) {
            var msg = 'Error al registrar la garantia.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            Swal.fire({ icon: 'error', title: 'Error', text: msg });
        }
    });
}

function cambiarEstadoGarantia(garantiaId, nuevoEstado) {
    var etiquetas = {
        'en_proceso': 'En Proceso',
        'completada': 'Completada',
        'reentregada': 'Reentregada'
    };

    Swal.fire({
        title: 'Cambiar Estado?',
        text: 'La garantia pasara a "' + (etiquetas[nuevoEstado] || nuevoEstado) + '".',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4A7C59',
        confirmButtonText: 'Si, Cambiar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: ROUTES_DETALLE.garantiasCambiarEstado + '/' + garantiaId + '/estado',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
            contentType: 'application/json',
            data: JSON.stringify({ estado: nuevoEstado }),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: response.message, showConfirmButton: false, timer: 2000
                    });
                    setTimeout(function() { window.location.reload(); }, 1500);
                }
            },
            error: function(xhr) {
                var msg = 'Error al cambiar el estado.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            }
        });
    });
}

function asignarOperarioGarantia(garantiaId) {
    // Cargar operarios y mostrar select
    $.ajax({
        url: ROUTES_DETALLE.operarios,
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        success: function(response) {
            var operarios = response.operarios || response;
            var options = {};
            if (Array.isArray(operarios)) {
                operarios.forEach(function(op) {
                    options[op.id] = op.name;
                });
            }

            Swal.fire({
                title: 'Asignar Operario',
                text: 'Seleccione el operario para esta garantia:',
                input: 'select',
                inputOptions: options,
                inputPlaceholder: 'Seleccione...',
                showCancelButton: true,
                confirmButtonColor: '#4A7C59',
                confirmButtonText: 'Asignar',
                cancelButtonText: 'Cancelar',
                inputValidator: function(value) {
                    if (!value) return 'Debe seleccionar un operario.';
                }
            }).then(function(result) {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: ROUTES_DETALLE.garantiasAsignarOperario + '/' + garantiaId + '/asignar-operario',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN },
                    contentType: 'application/json',
                    data: JSON.stringify({ operario_asignado_id: result.value }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                toast: true, position: 'top-end', icon: 'success',
                                title: response.message, showConfirmButton: false, timer: 2000
                            });
                            setTimeout(function() { window.location.reload(); }, 1500);
                        }
                    },
                    error: function(xhr) {
                        var msg = 'Error al asignar operario.';
                        if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        Swal.fire({ icon: 'error', title: 'Error', text: msg });
                    }
                });
            });
        },
        error: function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los operarios.' });
        }
    });
}

// ==========================================
// Helpers
// ==========================================
function formatCOPDetalle(valor) {
    if (isNaN(valor) || valor === null) valor = 0;
    return Math.round(valor).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtmlDetalle(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
