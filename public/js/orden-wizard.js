/**
 * SINDEN - Orden Wizard
 * Orquestador principal del wizard de creacion de ordenes.
 */

// ==========================================
// Estado Global
// ==========================================
var wizardState = {
    ordenId: null,
    clienteId: null,
    bosquejos: [],
    firmaData: null,
    lastSavedHash: null,
    autoSaveTimer: null,
    itemCounter: 0,
    piezaCounter: 0,
    pagoCounter: 0,
    isSaving: false,
    pendingSave: false,
    autoSaveDebounceTimer: null,
    avisoSinClienteMostrado: false
};

// ==========================================
// Inicializacion
// ==========================================
$(function() {
    initClienteAutocomplete();
    initFirmaCanvas();
    initDibujoCanvas();
    initAutoSave();
    initAutoSaveOnEdit();
    initStepWatchers();

    // Limpiar bandera de destino al cerrar modal de matriz sin seleccionar
    $(document).on('hidden.bs.modal', '#modalBosquejoMatriz', function() {
        window._piezaDestinoMatriz = undefined;
    });

    // Cerrar dropdown de autocomplete al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#clienteSearch, #clienteResults').length) {
            $('#clienteResults').hide();
        }
        // Cerrar dropdowns de items
        if (!$(e.target).closest('.item-codigo, .item-autocomplete-results').length) {
            $('.item-autocomplete-results').hide();
        }
        // Cerrar dropdowns de material
        if (!$(e.target).closest('.pieza-material, .material-autocomplete-results').length) {
            $('.material-autocomplete-results').hide();
        }
    });

    // Cerrar dropdowns de material al hacer scroll en table-responsive
    $(document).on('scroll', '.table-responsive', function() {
        $('.material-autocomplete-results').hide();
    });

    // Focus en campo codigo de items -> mostrar todas las opciones
    $(document).on('focus click', '.item-codigo', function() {
        buscarItemCatalogo(this);
    });

    // Focus/click en campo material de piezas -> mostrar todas las opciones
    $(document).on('focus click', '.pieza-material', function() {
        buscarMaterialPieza(this);
    });

    // Forzar enteros en cantidad de piezas (no decimales)
    $(document).on('input', '.pieza-cantidad', function() {
        var v = String(this.value || '').replace(/[.,].*$/, '').replace(/[^0-9]/g, '');
        if (v !== this.value) this.value = v;
    });
    $(document).on('keydown', '.pieza-cantidad', function(e) {
        // Bloquear tecla de punto, coma y "e"
        if (e.key === '.' || e.key === ',' || e.key === 'e' || e.key === 'E') {
            e.preventDefault();
        }
    });
    $(document).on('blur', '.pieza-cantidad', function() {
        var n = parseInt(this.value, 10);
        if (isNaN(n) || n < 1) n = 1;
        this.value = n;
        autoExpandCantidad(this);
    });

    // Descuento de items: PERMITIR decimales (punto o coma) en el porcentaje, rango
    // 0-100, maximo 2 decimales. Se normaliza a punto (igual que el display y la
    // cantidad). El cliente pidio poder poner decimales (ej. 2,5%).
    $(document).on('input', '.item-descuento', function() {
        var v = String(this.value || '').replace(/,/g, '.').replace(/[^0-9.]/g, '');
        var i = v.indexOf('.');
        if (i !== -1) {
            // Un solo separador decimal y maximo 2 decimales
            var ent = v.slice(0, i).replace(/\./g, '');
            var dec = v.slice(i + 1).replace(/\./g, '').slice(0, 2);
            v = ent + '.' + dec;
        }
        if (v !== this.value) this.value = v;
    });
    $(document).on('keydown', '.item-descuento', function(e) {
        // El punto/coma SI se permiten (decimal); solo bloquear notacion cientifica y signo
        if (e.key === 'e' || e.key === 'E' || e.key === '+' || e.key === '-') {
            e.preventDefault();
        }
    });
    $(document).on('blur', '.item-descuento', function() {
        this.value = parseDescuento(this.value);
        autoExpandCantidad(this);
    });

    // Auto-expandir inputs de cantidad segun contenido
    $(document).on('input change', '.cantidad-auto-expand', function() {
        autoExpandCantidad(this);
    });

    // Auto-expandir selects segun el texto de la opcion seleccionada
    $(document).on('change', '.select-auto-expand', function() {
        autoExpandSelect(this);
    });
});

// Ajusta dinamicamente el ancho del input segun el largo del valor.
// Usa canvas para medir el ancho real del texto y suma padding + spinner.
var _autoExpandCanvas = null;
function autoExpandCantidad(input) {
    if (!input) return;
    var val = String(input.value || input.placeholder || '0');
    if (val.length === 0) val = '0';

    if (!_autoExpandCanvas) {
        _autoExpandCanvas = document.createElement('canvas');
    }
    var ctx = _autoExpandCanvas.getContext('2d');
    var cs = window.getComputedStyle(input);
    ctx.font = cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
    var textW = ctx.measureText(val).width;

    // padding horizontal del input + ancho de los spinners (~22px) + holgura
    var padL = parseFloat(cs.paddingLeft) || 0;
    var padR = parseFloat(cs.paddingRight) || 0;
    var spinner = 22;
    var safety = 6;
    var w = Math.ceil(textW + padL + padR + spinner + safety);
    if (w < 70) w = 70;
    input.style.width = w + 'px';
}

// Aplica auto-expand a todos los inputs de cantidad existentes
function aplicarAutoExpandCantidades() {
    $('.cantidad-auto-expand').each(function() {
        autoExpandCantidad(this);
    });
}

// Ajusta el ancho del select segun el texto de la opcion seleccionada.
function autoExpandSelect(select) {
    if (!select) return;
    var opt = select.options[select.selectedIndex];
    var val = opt ? opt.text : '';
    if (!val) val = '--';

    if (!_autoExpandCanvas) {
        _autoExpandCanvas = document.createElement('canvas');
    }
    var ctx = _autoExpandCanvas.getContext('2d');
    var cs = window.getComputedStyle(select);
    ctx.font = cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
    var textW = ctx.measureText(val).width;

    var padL = parseFloat(cs.paddingLeft) || 0;
    var padR = parseFloat(cs.paddingRight) || 0;
    var chevron = 16;
    var safety = 8;
    var w = Math.ceil(textW + padL + padR + chevron + safety);
    if (w < 70) w = 70;
    select.style.width = w + 'px';
}

// ==========================================
// Step Watchers (completado de secciones)
// ==========================================
function initStepWatchers() {
    // Step 6: Fechas - se completa al poner fecha y hora de entrega
    $('#fecha_entrega, #hora_entrega').on('change', function() {
        if ($('#fecha_entrega').val() && $('#hora_entrega').val()) {
            marcarStepCompletado(6);
        } else {
            desmarcarStep(6);
        }
    });
}

// ==========================================
// Utilidades
// ==========================================
function formatCOP(valor) {
    if (isNaN(valor) || valor === null) valor = 0;
    return '$' + Math.round(valor).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function parseCOP(str) {
    if (!str) return 0;
    // Formato US: la coma es separador de miles, el punto es decimal.
    return parseFloat(String(str).replace(/[$,]/g, '')) || 0;
}

// Convierte el texto del descuento (acepta punto o coma) a numero 0-100 con maximo
// 2 decimales. Centraliza el clamp para calculo, guardado y precarga en edicion.
function parseDescuento(str) {
    var n = parseFloat(String(str == null ? '' : str).replace(',', '.'));
    if (isNaN(n) || n < 0) n = 0;
    if (n > 100) n = 100;
    return Math.round(n * 100) / 100;
}

/**
 * Formatea en vivo un input de moneda mientras el usuario escribe.
 * Miles separados por coma; decimales opcionales (max 2) tras un punto.
 * Los decimales NO aparecen por defecto: solo si el usuario los escribe.
 */
function formatearMoneda(input) {
    var posIni = input.selectionStart;
    var largoIni = input.value.length;

    // Dejar solo digitos y puntos (el punto es el separador decimal)
    var val = input.value.replace(/[^0-9.]/g, '');
    var primerPunto = val.indexOf('.');
    var entero, decimal = null;
    if (primerPunto !== -1) {
        entero = val.slice(0, primerPunto).replace(/\./g, '');
        decimal = val.slice(primerPunto + 1).replace(/\./g, '').slice(0, 2);
    } else {
        entero = val;
    }
    // Quitar ceros a la izquierda dejando al menos un digito
    entero = entero.replace(/^0+(?=\d)/, '');
    var enteroFmt = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    var resultado = enteroFmt;
    if (decimal !== null) {
        resultado += '.' + decimal;
    }
    input.value = resultado;

    // Reposicionar el cursor compensando el cambio de longitud
    var nuevaPos = posIni + (input.value.length - largoIni);
    if (nuevaPos < 0) nuevaPos = 0;
    if (nuevaPos > input.value.length) nuevaPos = input.value.length;
    try { input.setSelectionRange(nuevaPos, nuevaPos); } catch (e) { /* noop */ }
}

/**
 * Asigna un valor numerico a un input de moneda con formato de pesos.
 * Sin decimales si el valor es entero; los muestra (max 2) si existen.
 */
function setValorMoneda($el, num) {
    num = parseFloat(num);
    if (isNaN(num)) num = 0;
    var str = num.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    var partes = str.split('.');
    var enteroFmt = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    $el.val(enteroFmt + (partes[1] ? '.' + partes[1] : ''));
}

function handleAjaxError(xhr, contexto) {
    // El 419 (token vencido) lo maneja conexion-handler: refresca el token y reintenta
    // la operacion de forma transparente. No mostrar aqui un error que ademas parpadearia
    // justo antes de que el reintento tenga exito.
    if (xhr && xhr.status === 419) return;
    var msg = 'Error al ' + contexto + '.';
    if (xhr.responseJSON) {
        if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
        if (xhr.responseJSON.errores) {
            msg += '<br><ul>' + xhr.responseJSON.errores.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>';
        }
    }
    Swal.fire({ icon: 'error', title: 'Error', html: msg });
}

// ==========================================
// SECCION 1: Cliente
// ==========================================
function initClienteAutocomplete() {
    var debounceTimer = null;
    var $input = $('#clienteSearch');
    var $results = $('#clienteResults');

    $input.on('keyup', function() {
        clearTimeout(debounceTimer);
        var q = $(this).val().trim();
        if (q.length < 2) { $results.hide(); return; }

        debounceTimer = setTimeout(function() {
            $.get(ROUTES.clienteAutocomplete, { q: q }, function(data) {
                $results.empty();
                if (data.length === 0) {
                    $results.append('<div class="list-group-item text-muted small">No se encontraron clientes</div>');
                } else {
                    data.forEach(function(c) {
                        $results.append(
                            '<a href="#" class="list-group-item list-group-item-action py-2" onclick="seleccionarCliente(' + c.id + ', \'' + escapeHtml(c.nombre) + '\', \'' + escapeHtml(c.celular_1 || '') + '\', \'' + escapeHtml(c.correo || '') + '\'); return false;">'
                            + '<strong>' + escapeHtml(c.nombre) + '</strong>'
                            + '<small class="text-muted d-block">' + (c.celular_1 || '-') + ' | ' + (c.correo || '-') + '</small>'
                            + '</a>'
                        );
                    });
                }
                $results.show();
            });
        }, 300);
    });

    $input.on('focus', function() {
        if ($results.children().length > 0 && $(this).val().length >= 2) {
            $results.show();
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/**
 * Toast de confirmacion al agregar una fila (pieza, item o pago).
 * Aparece arriba a la derecha para que el usuario sepa que se agrego,
 * aunque la lista crezca hacia abajo y la nueva fila quede fuera de vista.
 */
function mostrarToastAgregado(mensaje) {
    if (!window.Swal) return;
    Swal.fire({
        toast: true, position: 'top-end', icon: 'success',
        title: mensaje,
        showConfirmButton: false, timer: 2000
    });
}

var _piezaMsgTimer = null;
function mostrarMensajePieza(mensaje) {
    var $box = $('#piezaMsgInline');
    if (!$box.length) { mostrarToastAgregado(mensaje); return; }

    $('#piezaMsgInlineText').text(mensaje);

    $box.removeClass('d-none');
    // Reiniciar la animacion de aparicion aunque ya estuviera visible
    $box.removeClass('pieza-msg-flash');
    void $box[0].offsetWidth; // forzar reflow para reiniciar el @keyframes
    $box.addClass('pieza-msg-flash');

    if (_piezaMsgTimer) clearTimeout(_piezaMsgTimer);
    _piezaMsgTimer = setTimeout(function () {
        $box.addClass('d-none').removeClass('pieza-msg-flash');
    }, 2500);
}

function seleccionarCliente(id, nombre, celular, correo) {
    wizardState.clienteId = id;
    $('#cliente_id').val(id);
    $('#clienteSearch').val(nombre).prop('disabled', true);
    $('#clienteResults').hide();
    $('#clienteStatus').text('Seleccionado').removeClass('bg-light text-muted').addClass('bg-success text-white');

    // Construir tarjeta de info del cliente via JS
    var infoHtml = '<div class="alert alert-light border d-flex align-items-center mb-0">'
        + '<i class="bi bi-person-check-fill text-success me-3 fs-4"></i>'
        + '<div class="flex-grow-1">'
        + '  <strong class="d-block">' + escapeHtml(nombre) + '</strong>'
        + '  <small class="text-muted">'
        + '    <i class="bi bi-phone me-1"></i>' + escapeHtml(celular || '-')
        + '    <span class="mx-2">|</span>'
        + '    <i class="bi bi-envelope me-1"></i>' + escapeHtml(correo || '-')
        + '  </small>'
        + '</div>'
        + '<button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="limpiarCliente()">'
        + '  <i class="bi bi-x-lg"></i>'
        + '</button>'
        + '</div>';

    $('#clienteSeleccionado').html(infoHtml).slideDown(200);
    marcarStepCompletado(1);
    triggerAutoSave('cliente');
}

function limpiarCliente() {
    wizardState.clienteId = null;
    $('#cliente_id').val('');
    $('#clienteSeleccionado').slideUp(200, function() {
        $(this).empty();
        $('#clienteSearch').val('').prop('disabled', false).focus();
    });
    $('#clienteStatus').text('Sin seleccionar').removeClass('bg-success text-white').addClass('bg-light text-muted');
    desmarcarStep(1);
}

function crearClienteInline() {
    var nombre = $('#nuevoClienteNombre').val().trim();
    if (!nombre) {
        Swal.fire('Error', 'El nombre del cliente es obligatorio.', 'error');
        return;
    }

    $.ajax({
        url: ROUTES.crearCliente,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
        contentType: 'application/json',
        data: JSON.stringify({
            nombre: nombre,
            celular_1: $('#nuevoClienteCelular1').val().trim(),
            celular_2: $('#nuevoClienteCelular2').val().trim(),
            correo: $('#nuevoClienteCorreo').val().trim(),
            direccion: $('#nuevoClienteDireccion').val().trim()
        }),
        success: function(response) {
            if (response.success) {
                var c = response.cliente;
                seleccionarCliente(c.id, c.nombre, c.celular_1, c.correo);
                $('#modalNuevoCliente').modal('hide');
                // Limpiar formulario del modal
                $('#nuevoClienteNombre, #nuevoClienteCelular1, #nuevoClienteCelular2, #nuevoClienteCorreo, #nuevoClienteDireccion').val('');
                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: 'Cliente creado y seleccionado', showConfirmButton: false, timer: 2000 });
            }
        },
        error: function(xhr) { handleAjaxError(xhr, 'crear el cliente'); }
    });
}

// ==========================================
// SECCION 2: Items
// ==========================================
function agregarFilaItem(opts) {
    opts = opts || {};
    wizardState.itemCounter++;
    var idx = wizardState.itemCounter;

    var html = '<tr id="itemRow_' + idx + '" data-idx="' + idx + '">'
        + '<td class="text-center text-muted"><span class="item-num">' + contarFilasItems() + '</span></td>'
        + '<td class="position-relative">'
        + '  <div class="d-flex gap-1 align-items-center">'
        + '    <input type="text" class="form-control form-control-sm item-codigo" data-idx="' + idx + '" placeholder="Buscar..." autocomplete="off" onkeyup="buscarItemCatalogo(this)">'
        + '    <button type="button" class="btn btn-sm btn-outline-secondary border-0 btn-desvincular-item p-0" onclick="desvincularItemCatalogo(' + idx + ')" style="display:none;min-width:24px" title="Desvincular del catalogo"><i class="bi bi-x-lg"></i></button>'
        + '  </div>'
        + '  <div class="item-autocomplete-results list-group shadow-sm" id="itemResults_' + idx + '" style="display:none; position:absolute; z-index:1050; width:100%; max-height:240px; overflow-y:auto;"></div>'
        + '  <input type="hidden" class="item-catalogo-id" value="">'
        + '  <input type="hidden" class="item-categoria" value="servicio">'
        + '</td>'
        + '<td><input type="text" class="form-control form-control-sm item-descripcion" placeholder="Descripcion del item"></td>'
        + '<td><input type="number" class="form-control form-control-sm text-center item-cantidad cantidad-auto-expand" value="1" min="0.01" step="0.01" style="width:75px" onchange="calcularTotalFila(' + idx + ')" onkeyup="calcularTotalFila(' + idx + ')"></td>'
        + '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-end item-precio money-input" value="0" oninput="formatearMoneda(this);calcularTotalFila(' + idx + ')"></td>'
        + '<td class="text-center"><input type="checkbox" class="form-check-input item-iva-check" checked onchange="calcularTotalFila(' + idx + ')"></td>'
        + '<td><input type="text" inputmode="decimal" class="form-control form-control-sm text-center item-descuento cantidad-auto-expand" value="0" style="width:75px" onchange="calcularTotalFila(' + idx + ')" onkeyup="calcularTotalFila(' + idx + ')"></td>'
        + '<td class="text-end fw-semibold item-subtotal-display">$0</td>'
        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarFilaItem(' + idx + ')"><i class="bi bi-trash"></i></button></td>'
        + '</tr>';

    $('#tbodyItems').append(html);
    $('#itemsVacio').hide();
    $('#panelTotales').show();
    renumerarFilasItems();
    // Focus en el campo codigo de la nueva fila (omitir en precarga modo edicion)
    if (!opts.skipFocus) {
        $('#itemRow_' + idx + ' .item-codigo').focus();
    }
    if (!opts.skipAutoSave) {
        triggerAutoSave('item-add');
        mostrarToastAgregado('Item agregado');
    }
}

// Confirmacion generica antes de eliminar filas del wizard
function confirmarEliminacionFila(titulo, html) {
    if (!window.Swal) {
        // Fallback de emergencia si el CDN de SweetAlert2 no cargo
        var textoPlano = html.replace(/<[^>]*>/g, '');
        return Promise.resolve(window.confirm(titulo + '\n' + textoPlano));
    }
    return Swal.fire({
        title: titulo,
        html: html,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        return result.isConfirmed;
    });
}

function eliminarFilaItem(idx) {
    var desc = ($('#itemRow_' + idx + ' .item-descripcion').val() || '').trim();
    var detalle = desc
        ? 'Se eliminara el item <b>' + escapeHtml(desc) + '</b>.'
        : 'Se eliminara este item.';
    confirmarEliminacionFila('¿Eliminar item?', detalle).then(function(confirmado) {
        if (!confirmado) return;
        $('#itemRow_' + idx).remove();
        renumerarFilasItems();
        recalcularTotales();
        if ($('#tbodyItems tr').length === 0) {
            $('#itemsVacio').show();
            $('#panelTotales').hide();
        }
        triggerAutoSave('item-del');
    });
}

function contarFilasItems() {
    return $('#tbodyItems tr').length + 1;
}

function renumerarFilasItems() {
    $('#tbodyItems tr').each(function(i) {
        $(this).find('.item-num').text(i + 1);
    });
}

var itemSearchTimers = {};
function buscarItemCatalogo(input) {
    var idx = $(input).data('idx');
    var $results = $('#itemResults_' + idx);
    // Solo un dropdown de items abierto a la vez: cerrar los de las demas filas
    // (evita que los menus se encimen y no se escondan al pasar de una fila a otra).
    $('.item-autocomplete-results').not($results).hide();
    if ($(input).prop('readonly')) { $results.hide(); return; }
    var q = $(input).val().trim();

    clearTimeout(itemSearchTimers[idx]);

    var delay = q.length === 0 ? 0 : 300;

    itemSearchTimers[idx] = setTimeout(function() {
        $.get(ROUTES.itemAutocomplete, { q: q }, function(data) {
            $results.empty();
            if (data.length === 0) {
                $results.append('<div class="list-group-item text-muted small py-1">Sin resultados</div>');
            } else {
                data.forEach(function(item) {
                    $results.append(
                        '<a href="#" class="list-group-item list-group-item-action py-1 small" '
                        + 'style="white-space:nowrap;" '
                        + 'onclick="seleccionarItemCatalogo(' + idx + ', ' + JSON.stringify(item).replace(/"/g, '&quot;') + '); return false;">'
                        + '<strong>' + escapeHtml(item.codigo) + '</strong> - ' + escapeHtml(item.descripcion)
                        + '<br><small class="text-muted">' + formatCOP(item.precio_unitario) + ' | IVA: ' + item.porcentaje_iva + '%</small>'
                        + '</a>'
                    );
                });
            }
            // Solo mostrar si el input sigue enfocado. Si el usuario ya paso a otra
            // fila mientras este AJAX viajaba, un show tardio reabriria este dropdown
            // y quedarian dos encimados (el .hide() sincrono ya corrio en la otra fila).
            if (document.activeElement !== input) return;
            var inputRect = input.getBoundingClientRect();
            $results.css({
                width: 'auto',
                'min-width': Math.max(inputRect.width, 320) + 'px',
                'max-width': '480px'
            });
            $results.show();
        });
    }, delay);
}

function seleccionarItemCatalogo(idx, item) {
    var $row = $('#itemRow_' + idx);
    $row.find('.item-codigo').val(item.codigo).prop('readonly', true).addClass('item-readonly');
    $row.find('.item-catalogo-id').val(item.id);
    $row.find('.item-descripcion').val(item.descripcion).prop('readonly', true).addClass('item-readonly');
    setValorMoneda($row.find('.item-precio'), item.precio_unitario);
    $row.find('.item-iva-check').prop('checked', parseFloat(item.porcentaje_iva) > 0);
    $row.find('.item-categoria').val(item.categoria);
    $row.find('.btn-desvincular-item').show();
    $('#itemResults_' + idx).hide();
    calcularTotalFila(idx);
    triggerAutoSave('item-catalogo');
}

function desvincularItemCatalogo(idx) {
    var $row = $('#itemRow_' + idx);
    $row.find('.item-catalogo-id').val('');
    $row.find('.item-codigo').prop('readonly', false).removeClass('item-readonly').val('').focus();
    $row.find('.item-descripcion').prop('readonly', false).removeClass('item-readonly').val('');
    $row.find('.item-categoria').val('servicio');
    $row.find('.btn-desvincular-item').hide();
}

function calcularTotalFila(idx) {
    var $row = $('#itemRow_' + idx);
    var cantidad = parseFloat($row.find('.item-cantidad').val()) || 0;
    var precio = parseCOP($row.find('.item-precio').val());
    var base = cantidad * precio;
    $row.find('.item-subtotal-display').text(formatCOP(base));
    recalcularTotales();
}

function recalcularTotales() {
    var totalSubtotalBruto = 0;
    var totalDescuento = 0;
    var totalIva = 0;

    $('#tbodyItems tr').each(function() {
        var cantidad = parseFloat($(this).find('.item-cantidad').val()) || 0;
        var precio = parseCOP($(this).find('.item-precio').val());
        var iva = $(this).find('.item-iva-check').is(':checked') ? WIZARD_CONFIG.ivaDefecto : 0;
        var descPct = parseDescuento($(this).find('.item-descuento').val());
        // Peso colombiano sin centavos: redondear cada monto a pesos enteros
        var base = Math.round(cantidad * precio);
        var descMonto = Math.round(base * descPct / 100);
        var ivaVal = Math.round(base * (iva / 100));
        totalSubtotalBruto += base;
        totalDescuento += descMonto;
        totalIva += ivaVal;
    });

    var totalGeneral = totalSubtotalBruto + totalIva;
    var totalConRetenciones = totalGeneral - totalDescuento;

    $('#totalSubtotalBruto').text(formatCOP(totalSubtotalBruto));
    $('#totalIva').text(formatCOP(totalIva));
    $('#totalGeneral').text(formatCOP(totalGeneral));
    $('#totalDescuento').text('-' + formatCOP(totalDescuento));
    if (totalDescuento > 0) { $('#filaDescuento').show(); } else { $('#filaDescuento').hide(); }
    $('#totalConRetenciones').text(formatCOP(totalConRetenciones));

    // Actualizar panel de pagos con el total que paga el cliente (con retenciones)
    $('#pagoTotalOrden').text(formatCOP(totalConRetenciones));
    recalcularSaldo();

    if ($('#tbodyItems tr').length > 0) {
        marcarStepCompletado(3);
    } else {
        desmarcarStep(3);
    }
}

// ==========================================
// SECCION 3: Piezas (unificado con bosquejos)
// ==========================================

// --- Funciones de bosquejo por pieza ---

function piezaSubirArchivo(piezaIdx) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp';
    input.multiple = true;
    input.style.display = 'none';
    input.onchange = function() { subirBosquejoParaPieza(input, 'archivo_local', piezaIdx); };
    document.body.appendChild(input);
    input.click();
    setTimeout(function() { if (input.parentNode) document.body.removeChild(input); }, 60000);
}

function piezaAbrirCamara(piezaIdx) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        Swal.fire('Error', 'Tu navegador no soporta acceso a la camara.', 'error');
        return;
    }
    window._camaraPiezaIdx = piezaIdx;
    var modal = document.getElementById('modalCamaraPieza');
    if (!modal) return;
    var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    bsModal.show();

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(stream) {
            window._camaraStream = stream;
            var video = document.getElementById('camaraPiezaVideo');
            if (video) {
                video.srcObject = stream;
            }
        })
        .catch(function(err) {
            console.error('Error al acceder a la camara:', err);
            bsModal.hide();
            Swal.fire('Error', 'No se pudo acceder a la camara. Verifica los permisos.', 'error');
        });
}

function camaraPiezaCapturar() {
    var video = document.getElementById('camaraPiezaVideo');
    var canvas = document.getElementById('camaraPiezaCanvas');
    if (!video || !canvas) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    camaraPiezaCerrar();

    var piezaIdx = window._camaraPiezaIdx;
    canvas.toBlob(function(blob) {
        if (!blob) return;
        var formData = new FormData();
        formData.append('archivo', blob, 'foto_camara.jpg');
        formData.append('tipo_origen', 'camara');
        formData.append('nombre', 'Foto camara');
        if (wizardState.ordenId) formData.append('orden_id', wizardState.ordenId);

        $.ajax({
            url: ROUTES.subirBosquejo,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var bosquejoIndex = wizardState.bosquejos.length;
                    wizardState.bosquejos.push(response.bosquejo);
                    vincularBosquejoAPieza(piezaIdx, bosquejoIndex);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                        title: 'Bosquejo agregado', showConfirmButton: false, timer: 2000 });
                }
            },
            error: function(xhr) { handleAjaxError(xhr, 'subir el bosquejo'); }
        });
    }, 'image/jpeg', 0.85);
}

function camaraPiezaCerrar() {
    if (window._camaraStream) {
        window._camaraStream.getTracks().forEach(function(track) { track.stop(); });
        window._camaraStream = null;
    }
    var modal = document.getElementById('modalCamaraPieza');
    if (modal) {
        var bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    }
}

// Limpiar stream de camara cuando se cierra el modal por cualquier medio
$(function() {
    var modalCamara = document.getElementById('modalCamaraPieza');
    if (modalCamara) {
        modalCamara.addEventListener('hidden.bs.modal', function() {
            if (window._camaraStream) {
                window._camaraStream.getTracks().forEach(function(track) { track.stop(); });
                window._camaraStream = null;
            }
        });
    }
});

function piezaAbrirDibujo(piezaIdx) {
    window._targetPiezaForDibujo = piezaIdx;
    window._editandoBosquejoParaPieza = false;
    if (typeof initDibujoCanvas === 'function') initDibujoCanvas();
    if (typeof limpiarDibujo === 'function') limpiarDibujo();
    $('#modalDibujoTablet').modal('show');
}

function piezaEditarBosquejo(piezaIdx) {
    var $row = $('#piezaRow_' + piezaIdx);
    var bosquejoIndex = $row.attr('data-bosquejo-index');
    if (bosquejoIndex === '' || bosquejoIndex === undefined) return;
    bosquejoIndex = parseInt(bosquejoIndex);
    if (isNaN(bosquejoIndex) || !wizardState.bosquejos[bosquejoIndex]) return;
    window._targetPiezaForDibujo = piezaIdx;
    window._editandoBosquejoParaPieza = true;
    if (typeof abrirEditorBosquejo === 'function') {
        abrirEditorBosquejo(bosquejoIndex);
    }
}

function piezaRemoverBosquejo(piezaIdx) {
    var $row = $('#piezaRow_' + piezaIdx);
    $row.attr('data-bosquejo-index', '');
    $row.find('.bosquejo-thumb-container').hide();
    $row.find('.bosquejo-name-label').hide();
    $row.find('.bosquejo-empty-actions').show();
}

function piezaEditarNombreBosquejo(piezaIdx) {
    var $row = $('#piezaRow_' + piezaIdx);
    var bosquejoIndex = $row.attr('data-bosquejo-index');
    if (bosquejoIndex === '' || bosquejoIndex === undefined) return;
    bosquejoIndex = parseInt(bosquejoIndex);
    if (isNaN(bosquejoIndex) || !wizardState.bosquejos[bosquejoIndex]) return;

    var currentName = wizardState.bosquejos[bosquejoIndex].nombre || '';

    Swal.fire({
        title: 'Nombre del bosquejo',
        input: 'text',
        inputValue: currentName,
        inputPlaceholder: 'Nombre del bosquejo',
        showCancelButton: true,
        confirmButtonText: 'Guardar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#4A7C59',
        inputValidator: function(value) {
            if (!value || !value.trim()) {
                return 'El nombre no puede estar vacio.';
            }
        }
    }).then(function(result) {
        if (result.isConfirmed && result.value) {
            var newName = result.value.trim();
            wizardState.bosquejos[bosquejoIndex].nombre = newName;
            $row.find('.bosquejo-name-text').text(newName).attr('title', newName);
            $row.find('.pieza-bosquejo-thumb').attr('alt', newName).attr('title', newName);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                title: 'Nombre actualizado', showConfirmButton: false, timer: 1500 });
        }
    });
}

function subirBosquejoParaPieza(fileInput, tipoOrigen, piezaIdx) {
    var files = fileInput.files;
    if (!files || files.length === 0) return;

    var total = files.length;

    // Caso simple: 1 archivo (comportamiento original)
    if (total === 1) {
        var formData = new FormData();
        formData.append('archivo', files[0]);
        formData.append('tipo_origen', tipoOrigen || 'archivo_local');
        formData.append('nombre', files[0].name.replace(/\.[^/.]+$/, ''));
        if (wizardState.ordenId) formData.append('orden_id', wizardState.ordenId);

        $.ajax({
            url: ROUTES.subirBosquejo,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var bosquejoIndex = wizardState.bosquejos.length;
                    wizardState.bosquejos.push(response.bosquejo);
                    vincularBosquejoAPieza(piezaIdx, bosquejoIndex);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                        title: 'Bosquejo agregado', showConfirmButton: false, timer: 2000 });
                }
            },
            error: function(xhr) { handleAjaxError(xhr, 'subir el bosquejo'); }
        });
        fileInput.value = '';
        return;
    }

    // Caso multi-archivo: subir en paralelo y mantener el orden de seleccion
    var resultados = new Array(total);
    var pendientes = total;
    var errores = 0;

    function procesarFinal() {
        // Procesa los bosquejos en orden: el primero va a piezaIdx, los demas crean piezas nuevas
        for (var i = 0; i < total; i++) {
            var bosquejo = resultados[i];
            if (!bosquejo) continue;
            if (i === 0) {
                var idx = wizardState.bosquejos.length;
                wizardState.bosquejos.push(bosquejo);
                vincularBosquejoAPieza(piezaIdx, idx);
            } else {
                agregarPiezaConBosquejo(bosquejo);
            }
        }
        var exitos = total - errores;
        Swal.fire({ toast: true, position: 'top-end',
            icon: errores === 0 ? 'success' : 'warning',
            title: exitos + ' bosquejo(s) agregado(s)' + (errores > 0 ? ' (' + errores + ' con error)' : ''),
            showConfirmButton: false, timer: 2500 });
    }

    for (var i = 0; i < total; i++) {
        (function(index) {
            var file = files[index];
            var formData = new FormData();
            formData.append('archivo', file);
            formData.append('tipo_origen', tipoOrigen || 'archivo_local');
            formData.append('nombre', file.name.replace(/\.[^/.]+$/, ''));
            if (wizardState.ordenId) formData.append('orden_id', wizardState.ordenId);

            $.ajax({
                url: ROUTES.subirBosquejo,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response && response.success) {
                        resultados[index] = response.bosquejo;
                    } else {
                        errores++;
                    }
                },
                error: function(xhr) {
                    errores++;
                    handleAjaxError(xhr, 'subir el bosquejo "' + file.name + '"');
                },
                complete: function() {
                    pendientes--;
                    if (pendientes === 0) procesarFinal();
                }
            });
        })(i);
    }

    fileInput.value = '';
}

function vincularBosquejoAPieza(piezaIdx, bosquejoIndex) {
    var $row = $('#piezaRow_' + piezaIdx);
    if (!$row.length) return;
    $row.attr('data-bosquejo-index', bosquejoIndex);

    var b = wizardState.bosquejos[bosquejoIndex];
    if (!b) return;
    var imgSrc = b.ruta_miniatura || b.ruta_archivo;
    if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('/') && !imgSrc.startsWith('data:')) {
        imgSrc = '/' + imgSrc;
    }

    $row.find('.bosquejo-empty-actions').hide();
    $row.find('.bosquejo-thumb-container').show();
    $row.find('.pieza-bosquejo-thumb').attr('src', imgSrc).attr('alt', b.nombre).attr('title', b.nombre);

    // Mostrar nombre del bosquejo debajo de la miniatura
    $row.find('.bosquejo-name-text').text(b.nombre).attr('title', b.nombre);
    $row.find('.bosquejo-name-label').show();
    triggerAutoSave('bosquejo-vinculado');
}

/**
 * Sincroniza wizardState.bosquejos con los datos persistidos del backend.
 * Actualiza IDs y rutas (temp -> permanente) para evitar que re-saves borren archivos.
 */
function sincronizarBosquejosDesdeRespuesta(bosquejosBackend) {
    // El backend devuelve los bosquejos en el mismo orden que fueron enviados.
    // recopilarDatosFormulario() filtra solo los referenciados por piezas,
    // asi que necesitamos mapear por indice de los bosquejos referenciados.
    var referencedIndices = [];
    $('#tbodyPiezas tr.pieza-row').each(function() {
        var bIdx = $(this).attr('data-bosquejo-index');
        if (bIdx !== '' && bIdx !== undefined) {
            var idx = parseInt(bIdx);
            if (referencedIndices.indexOf(idx) === -1) {
                referencedIndices.push(idx);
            }
        }
    });
    referencedIndices.sort(function(a, b) { return a - b; });

    for (var i = 0; i < referencedIndices.length && i < bosquejosBackend.length; i++) {
        var wsIdx = referencedIndices[i];
        var bb = bosquejosBackend[i];
        if (wizardState.bosquejos[wsIdx]) {
            wizardState.bosquejos[wsIdx].id = bb.id;
            wizardState.bosquejos[wsIdx].ruta_archivo = bb.ruta_archivo;
            wizardState.bosquejos[wsIdx].ruta_miniatura = bb.ruta_miniatura;
        }
    }
}

function agregarPiezaConBosquejo(bosquejoData) {
    var bosquejoIndex = wizardState.bosquejos.length;
    wizardState.bosquejos.push(bosquejoData);
    // skipScroll: al importar (matriz/grupo/multi-archivo) NO mover la pantalla ni enfocar
    // la fila nueva, para no devolver el modal de matriz al inicio (perdia la posicion).
    agregarFilaPieza({ skipScroll: true });
    var piezaIdx = wizardState.piezaCounter;
    vincularBosquejoAPieza(piezaIdx, bosquejoIndex);
    generarEspecificacion(piezaIdx);
}

// --- Funciones de matriz (ahora crean piezas automaticamente) ---

function piezaSeleccionarDeMatriz(piezaIdx) {
    window._piezaDestinoMatriz = piezaIdx;
    var modalEl = document.getElementById('modalBosquejoMatriz');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function seleccionarPlantillaMatriz(plantillaId, nombre, rutaArchivo, rutaMiniatura) {
    if (typeof window._piezaDestinoMatriz !== 'undefined' && window._piezaDestinoMatriz !== null) {
        var piezaIdx = window._piezaDestinoMatriz;
        window._piezaDestinoMatriz = undefined;
        var bosquejoIndex = wizardState.bosquejos.length;
        wizardState.bosquejos.push({
            nombre: nombre,
            tipo_origen: 'plantilla',
            ruta_archivo: rutaArchivo,
            ruta_miniatura: rutaMiniatura,
            plantilla_bosquejo_id: plantillaId
        });
        vincularBosquejoAPieza(piezaIdx, bosquejoIndex);
        var modalEl = document.getElementById('modalBosquejoMatriz');
        if (modalEl) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        Swal.fire({ toast: true, position: 'top-end', icon: 'success',
            title: 'Bosquejo asignado a la pieza', showConfirmButton: false, timer: 2000 });
        return;
    }
    agregarPiezaConBosquejo({
        nombre: nombre,
        tipo_origen: 'plantilla',
        ruta_archivo: rutaArchivo,
        ruta_miniatura: rutaMiniatura,
        plantilla_bosquejo_id: plantillaId
    });
    Swal.fire({ toast: true, position: 'top-end', icon: 'success',
        title: 'Pieza creada con bosquejo', showConfirmButton: false, timer: 2000 });
}

function insertarGrupoCompleto(grupoId) {
    window._piezaDestinoMatriz = undefined;
    $.get(ROUTES.subirBosquejo.replace('subir-bosquejo', 'grupos-bosquejos'), function(response) {
        if (response.success) {
            var grupo = response.grupos.find(function(g) { return g.id === grupoId; });
            if (grupo && grupo.plantillas && grupo.plantillas.length > 0) {
                grupo.plantillas.forEach(function(p) {
                    agregarPiezaConBosquejo({
                        nombre: p.nombre,
                        tipo_origen: 'grupo_plantillas',
                        ruta_archivo: p.ruta_archivo,
                        ruta_miniatura: p.ruta_miniatura || p.ruta_archivo,
                        plantilla_bosquejo_id: p.id
                    });
                });
                $('#modalBosquejoMatriz').modal('hide');
                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                    title: grupo.plantillas.length + ' piezas creadas del grupo', showConfirmButton: false, timer: 2000 });
            }
        }
    });
}

// --- Panel flotante de Bosquejos (modeless) ---

function abrirPanelBosquejos() {
    renderPanelBosquejos();
    var panel = document.getElementById('panelBosquejos');
    if (panel) panel.style.display = 'flex';
}

function cerrarPanelBosquejos() {
    var panel = document.getElementById('panelBosquejos');
    if (panel) panel.style.display = 'none';
}

function setBosquejosPorFila(n) {
    var galeria = document.getElementById('galeriaBosquejos');
    if (galeria) galeria.style.setProperty('--cols', n);
    $('#panelBosquejosColsGroup button').removeClass('active');
    $('#panelBosquejosColsGroup button[data-cols="' + n + '"]').addClass('active');
}

function renderPanelBosquejos() {
    var $galeria = $('#galeriaBosquejos');
    if (!$galeria.length) return;
    $galeria.empty();

    var count = 0;
    $('#tbodyPiezas tr.pieza-row').each(function() {
        var $row = $(this);
        var bIdx = $row.attr('data-bosquejo-index');
        if (bIdx === '' || bIdx === undefined) return;

        // Usar la miniatura ya resuelta en la fila (evita problemas de rutas)
        var imgSrc = $row.find('.pieza-bosquejo-thumb').attr('src');
        if (!imgSrc) return;

        // Al liquidar (panel "Ver Bosquejos") el cliente necesita ver bajo cada bosquejo
        // la CANTIDAD, el MATERIAL y el CALIBRE de la pieza (en ese orden) — es lo util
        // para poner precios. El "Pieza A · Cilindro" no le servia, se reemplaza.
        var cantidad = ($row.find('.pieza-cantidad').val() || '').trim();
        var material = ($row.find('.pieza-material').val() || '').trim();
        var calibre  = ($row.find('.pieza-calibre').val() || '').trim();
        var partes = [];
        if (cantidad) partes.push('Cant: ' + cantidad);
        if (material) partes.push(material);
        if (calibre)  partes.push(calibre);
        var caption = partes.join('  ·  ') || ($row.find('.pieza-nombre').val() || 'Pieza').trim();

        var $item = $('<div class="bosquejo-galeria-item"></div>');
        $item.append($('<img>').attr('src', imgSrc).attr('alt', caption).attr('loading', 'lazy'));
        $item.append($('<div class="bosquejo-galeria-caption"></div>').text(caption).attr('title', caption));
        $galeria.append($item);
        count++;
    });

    $('#panelBosquejosCount').text(count);
    if (count === 0) {
        $galeria.html('<div class="text-center text-muted py-4" style="grid-column:1/-1;">'
            + '<i class="bi bi-images fs-1 d-block mb-2 opacity-50"></i>'
            + '<p class="mb-0">No hay bosquejos en las piezas. Agregue piezas con su bosquejo para verlos aqui.</p>'
            + '</div>');
    }
}

// --- Funciones legacy (stubs para compatibilidad) ---

function renderizarGrillaBosquejos() { /* Deprecado: bosquejos ahora en celdas de piezas */ }
function actualizarSelectBosquejosPiezas() { /* Deprecado: sin select dropdown */ }
function generarOpcionesBosquejos() { return ''; }

function abrirSelectorArchivo() {
    // Funcion legacy - mantener para posible uso
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp';
    input.style.display = 'none';
    input.onchange = function() {
        var files = input.files;
        if (!files || files.length === 0) return;
        var formData = new FormData();
        formData.append('archivo', files[0]);
        formData.append('tipo_origen', 'archivo_local');
        formData.append('nombre', files[0].name.replace(/\.[^/.]+$/, ''));
        if (wizardState.ordenId) formData.append('orden_id', wizardState.ordenId);
        $.ajax({
            url: ROUTES.subirBosquejo, method: 'POST',
            headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
            data: formData, processData: false, contentType: false,
            success: function(r) { if (r.success) wizardState.bosquejos.push(r.bosquejo); },
            error: function(xhr) { handleAjaxError(xhr, 'subir el bosquejo'); }
        });
        input.value = '';
    };
    document.body.appendChild(input);
    input.click();
    setTimeout(function() { if (input.parentNode) document.body.removeChild(input); }, 60000);
}

function abrirCamara() {
    // Legacy: redirige a modal de camara sin pieza asociada
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        Swal.fire('Error', 'Tu navegador no soporta acceso a la camara.', 'error');
        return;
    }
    window._camaraPiezaIdx = null;
    var modal = document.getElementById('modalCamaraPieza');
    if (!modal) return;
    var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    bsModal.show();

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function(stream) {
            window._camaraStream = stream;
            var video = document.getElementById('camaraPiezaVideo');
            if (video) video.srcObject = stream;
        })
        .catch(function(err) {
            console.error('Error al acceder a la camara:', err);
            bsModal.hide();
            Swal.fire('Error', 'No se pudo acceder a la camara. Verifica los permisos.', 'error');
        });
}

// --- Funciones de pieza (tabla) ---

function agregarFilaPieza(opts) {
    opts = opts || {};
    wizardState.piezaCounter++;
    var idx = wizardState.piezaCounter;
    var letra = obtenerLetraPieza($('#tbodyPiezas tr.pieza-row').length);
    var nombre = 'Pieza ' + letra;

    // Opciones de calibre
    var calOpts = '<option value="">--</option>';
    if (WIZARD_CONFIG.calibres && Array.isArray(WIZARD_CONFIG.calibres)) {
        WIZARD_CONFIG.calibres.forEach(function(c) {
            var label = typeof c === 'object' ? (c.calibre || c.label || c) : c;
            var value = typeof c === 'object' ? (c.calibre || c.value || c) : c;
            calOpts += '<option value="' + escapeHtml(String(value)) + '">' + escapeHtml(String(label)) + '</option>';
        });
    }

    var html = '<tr id="piezaRow_' + idx + '" class="pieza-row" data-idx="' + idx + '" data-bosquejo-index="" data-operario-original="">'
        + '<td class="pieza-bosquejo-cell text-center">'
        + '  <div class="bosquejo-empty-actions">'
        + '    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="piezaSubirArchivo(' + idx + ')" title="Subir archivo"><i class="bi bi-upload"></i></button>'
        + '    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="piezaAbrirCamara(' + idx + ')" title="Camara"><i class="bi bi-camera"></i></button>'
        + '    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="piezaAbrirDibujo(' + idx + ')" title="Dibujar"><i class="bi bi-pencil-square"></i></button>'
        + '    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="piezaSeleccionarDeMatriz(' + idx + ')" title="Seleccionar de matriz"><i class="bi bi-grid-3x3"></i></button>'
        + '  </div>'
        + '  <div class="bosquejo-thumb-container" style="display:none;">'
        + '    <img src="" class="pieza-bosquejo-thumb" alt="">'
        + '    <button type="button" class="bosquejo-edit-overlay" onclick="piezaEditarBosquejo(' + idx + ')" title="Editar"><i class="bi bi-pencil"></i></button>'
        + '    <button type="button" class="bosquejo-remove-overlay" onclick="piezaRemoverBosquejo(' + idx + ')" title="Quitar"><i class="bi bi-x-lg"></i></button>'
        + '  </div>'
        + '  <div class="bosquejo-name-label" style="display:none;">'
        + '    <span class="bosquejo-name-text" title="Click para editar nombre" onclick="piezaEditarNombreBosquejo(' + idx + ')"></span>'
        + '    <button type="button" class="bosquejo-name-edit-btn" onclick="piezaEditarNombreBosquejo(' + idx + ')" title="Editar nombre"><i class="bi bi-pencil-fill"></i></button>'
        + '  </div>'
        + '</td>'
        + '<td class="text-center text-muted"><span class="pieza-num">' + ($('#tbodyPiezas tr.pieza-row').length + 1) + '</span></td>'
        + '<td><input type="text" class="form-control form-control-sm pieza-nombre" value="' + nombre + '" onchange="generarEspecificacion(' + idx + ')"></td>'
        + '<td><input type="number" class="form-control form-control-sm text-center pieza-cantidad cantidad-auto-expand" value="1" min="1" step="1" inputmode="numeric" style="width:75px" onchange="generarEspecificacion(' + idx + ')"></td>'
        + '<td class="position-relative">'
        + '  <input type="text" class="form-control form-control-sm pieza-material" data-idx="' + idx + '" placeholder="Buscar..." autocomplete="off" onkeyup="buscarMaterialPieza(this)" onchange="generarEspecificacion(' + idx + ')">'
        + '  <div class="material-autocomplete-results list-group shadow-sm" id="materialResults_' + idx + '" style="display:none; position:fixed; z-index:1050; max-height:200px; overflow-y:auto;"></div>'
        + '</td>'
        + '<td><select class="form-select form-select-sm pieza-calibre select-auto-expand" onchange="generarEspecificacion(' + idx + ')">' + calOpts + '</select></td>'
        + '<td class="small text-muted pieza-especificacion">1 - ' + nombre + '</td>'
        + '<td>' + construirSelectOperario() + '</td>'
        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarFilaPieza(' + idx + ')"><i class="bi bi-trash"></i></button></td>'
        + '</tr>'
        + '<tr id="piezaNotasRow_' + idx + '" class="pieza-notas-row" data-idx="' + idx + '">'
        + '<td colspan="9" style="border-top:0; padding-top:0;">'
        + '  <div class="d-flex align-items-start gap-2">'
        + '    <label class="form-label small text-muted mb-0 mt-1 fw-semibold" style="min-width:55px;"><i class="bi bi-sticky me-1"></i>Notas:</label>'
        + '    <textarea class="form-control form-control-sm pieza-notas" data-idx="' + idx + '" placeholder="Notas de la pieza..." rows="2" style="resize:vertical;"></textarea>'
        + '  </div>'
        + '</td>'
        + '</tr>';

    $('#tbodyPiezas').append(html);
    $('#tablaPiezas').show();
    $('#piezasVacio').hide();
    renumerarFilasPiezas();
    marcarStepCompletado(2);
    if (!opts.skipAutoSave) {
        // Apuntar a la pieza recien agregada: bajar hasta ella, resaltarla y enfocar su
        // nombre. Solo en alta MANUAL ("Agregar Pieza"). En IMPORTACION (matriz / grupo /
        // multi-archivo) se OMITE con opts.skipScroll: enfocar/scrollear una fila que queda
        // detras del modal de matriz lo devolvia al INICIO y estorbaba al importar varias
        // plantillas seguidas (la galeria perdia la posicion del scroll).
        var _filaNueva = document.getElementById('piezaRow_' + idx);
        if (_filaNueva && !opts.skipScroll) {
            _filaNueva.scrollIntoView({ behavior: 'smooth', block: 'center' });
            _filaNueva.classList.add('pieza-recien-agregada');
            setTimeout(function () { _filaNueva.classList.remove('pieza-recien-agregada'); }, 1600);
            var _nombreNueva = document.querySelector('#piezaRow_' + idx + ' .pieza-nombre');
            if (_nombreNueva) { try { _nombreNueva.focus({ preventScroll: true }); } catch (e) {} }
        }
        triggerAutoSave('pieza-add');
    }
}

function eliminarFilaPieza(idx) {
    var $row = $('#piezaRow_' + idx);
    var nombre = ($row.find('.pieza-nombre').val() || '').trim();
    var tieneBosquejo = ($row.attr('data-bosquejo-index') || '') !== '';
    var detalle = nombre
        ? 'Se eliminara <b>' + escapeHtml(nombre) + '</b>.'
        : 'Se eliminara esta pieza.';
    if (tieneBosquejo) {
        detalle += '<br><span class="text-danger">Tambien se perdera el bosquejo/dibujo asociado.</span>';
    }
    confirmarEliminacionFila('¿Eliminar pieza?', detalle).then(function(confirmado) {
        if (!confirmado) return;
        $('#piezaRow_' + idx).remove();
        $('#piezaNotasRow_' + idx).remove();
        renumerarFilasPiezas();
        if ($('#tbodyPiezas tr.pieza-row').length === 0) {
            $('#tablaPiezas').hide();
            $('#piezasVacio').show();
            desmarcarStep(2);
        }
        triggerAutoSave('pieza-del');
    });
}

function renumerarFilasPiezas() {
    $('#tbodyPiezas tr.pieza-row').each(function(i) {
        $(this).find('.pieza-num').text(i + 1);
        var letra = obtenerLetraPieza(i);
        var nuevoNombre = 'Pieza ' + letra;
        $(this).find('.pieza-nombre').val(nuevoNombre);
        var idx = $(this).data('idx');
        generarEspecificacion(idx);
    });
    actualizarContadorPiezas();
}

function actualizarContadorPiezas() {
    var total = $('#tbodyPiezas tr.pieza-row').length;
    $('.contador-piezas').text(total > 0 ? ' (' + total + ')' : '');
}

function obtenerLetraPieza(index) {
    var letra = '';
    var n = index + 1;
    while (n > 0) {
        n--;
        letra = String.fromCharCode(65 + (n % 26)) + letra;
        n = Math.floor(n / 26);
    }
    return letra;
}

function generarEspecificacion(idx) {
    var $row = $('#piezaRow_' + idx);
    var cantidad = $row.find('.pieza-cantidad').val() || '1';
    var nombre = $row.find('.pieza-nombre').val() || '';
    var calibre = $row.find('.pieza-calibre').val() || '';
    var material = $row.find('.pieza-material').val() || '';

    var partes = [cantidad];
    if (nombre) partes.push(nombre);
    if (calibre) partes.push(calibre);
    if (material) partes.push(material);

    $row.find('.pieza-especificacion').text(partes.join(' - '));
}

// --- Autocomplete de Material (client-side) ---
function buscarMaterialPieza(input) {
    var idx = $(input).data('idx');
    var q = $(input).val().trim().toLowerCase();
    var $results = $('#materialResults_' + idx);
    // Solo un dropdown de material abierto a la vez: cerrar los de las demas filas
    $('.material-autocomplete-results').not($results).hide();

    var materiales = WIZARD_CONFIG.materiales || [];
    var filtrados = materiales.filter(function(m) {
        return !q || m.toLowerCase().indexOf(q) !== -1;
    });

    $results.empty();
    if (filtrados.length === 0) {
        $results.append('<div class="list-group-item text-muted small py-1">Sin resultados</div>');
    } else {
        filtrados.forEach(function(m) {
            $results.append(
                '<a href="#" class="list-group-item list-group-item-action py-1 small" '
                + 'style="white-space:nowrap;" '
                + 'onclick="seleccionarMaterialPieza(' + idx + ', \'' + escapeHtml(m) + '\'); return false;">'
                + escapeHtml(m)
                + '</a>'
            );
        });
    }
    // Posicionar con fixed para que no se corte por overflow del table-responsive
    var inputRect = input.getBoundingClientRect();
    var dropdownWidth = Math.max(inputRect.width, 220);
    $results.css({
        position: 'fixed',
        top: inputRect.bottom + 'px',
        left: inputRect.left + 'px',
        width: 'auto',
        'min-width': dropdownWidth + 'px',
        'max-width': '320px'
    });
    $results.show();
}

function seleccionarMaterialPieza(idx, material) {
    var $row = $('#piezaRow_' + idx);
    $row.find('.pieza-material').val(material);
    $('#materialResults_' + idx).hide();
    generarEspecificacion(idx);
}

function construirSelectOperario() {
    var operarios = (WIZARD_CONFIG && WIZARD_CONFIG.operarios) ? WIZARD_CONFIG.operarios : [];
    var opts = '<option value="">-- Sin operario --</option>';
    operarios.forEach(function(op) {
        opts += '<option value="' + op.id + '">' + escapeHtml(String(op.name)) + '</option>';
    });
    return '<select class="form-select form-select-sm pieza-operario" onchange="triggerAutoSave(\'pieza-operario\')">' + opts + '</select>';
}

// ==========================================
// SECCION 6: Pagos
// ==========================================
function agregarFilaPago(opts) {
    opts = opts || {};
    wizardState.pagoCounter++;
    var idx = wizardState.pagoCounter;

    var html = '<div class="pago-row" id="pagoRow_' + idx + '">'
        + '<div class="flex-grow-1 row g-2 align-items-center">'
        + '  <div class="col-sm-4">'
        + '    <div class="input-group input-group-sm">'
        + '      <span class="input-group-text">$</span>'
        + '      <input type="text" inputmode="decimal" class="form-control pago-monto money-input" placeholder="Monto" oninput="formatearMoneda(this);recalcularSaldo()">'
        + '    </div>'
        + '  </div>'
        + '  <div class="col-sm-4">'
        + '    <select class="form-select form-select-sm pago-metodo">'
        + (function () {
            var tipos = (window.TIPOS_PAGO && window.TIPOS_PAGO.length) ? window.TIPOS_PAGO : [{codigo:'efectivo', nombre:'Efectivo'}];
            return tipos.map(function (t) {
                return '<option value="' + t.codigo + '">' + t.codigo + ' - ' + t.nombre + '</option>';
            }).join('');
          })()
        + '    </select>'
        + '  </div>'
        + '  <div class="col-sm-3">'
        + '    <input type="text" class="form-control form-control-sm pago-referencia" placeholder="Referencia">'
        + '  </div>'
        + '  <div class="col-sm-1 text-center">'
        + '    <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="eliminarFilaPago(' + idx + ')"><i class="bi bi-trash"></i></button>'
        + '  </div>'
        + '</div>'
        + '</div>';

    $('#pagosContainer').append(html);
    $('#pagosVacio').hide();
    $('#panelSaldo').show();
    recalcularSaldo();
    marcarStepCompletado(5);
    if (!opts.skipAutoSave) {
        triggerAutoSave('pago-add');
        mostrarToastAgregado('Pago agregado');
    }
}

function eliminarFilaPago(idx) {
    var monto = ($('#pagoRow_' + idx + ' .pago-monto').val() || '').trim();
    var detalle = (monto && monto !== '0')
        ? 'Se eliminara el abono de <b>$' + escapeHtml(monto) + '</b>.'
        : 'Se eliminara este abono.';
    confirmarEliminacionFila('¿Eliminar abono?', detalle).then(function(confirmado) {
        if (!confirmado) return;
        $('#pagoRow_' + idx).remove();
        recalcularSaldo();
        if ($('#pagosContainer .pago-row').length === 0) {
            $('#pagosVacio').show();
            $('#panelSaldo').hide();
            desmarcarStep(5);
        }
        triggerAutoSave('pago-del');
    });
}

function recalcularSaldo() {
    var totalAbonado = 0;
    $('#pagosContainer .pago-monto').each(function() {
        totalAbonado += parseCOP($(this).val());
    });

    // Obtener total con retenciones de items (lo que paga el cliente)
    var totalGeneral = 0;
    $('#tbodyItems tr').each(function() {
        var cantidad = parseFloat($(this).find('.item-cantidad').val()) || 0;
        var precio = parseCOP($(this).find('.item-precio').val());
        var iva = $(this).find('.item-iva-check').is(':checked') ? WIZARD_CONFIG.ivaDefecto : 0;
        var descPct = parseDescuento($(this).find('.item-descuento').val());
        // Peso colombiano sin centavos: redondear a pesos enteros
        var base = Math.round(cantidad * precio);
        var ivaVal = Math.round(base * (iva / 100));
        var descMonto = Math.round(base * descPct / 100);
        totalGeneral += base + ivaVal - descMonto;
    });

    var saldo = totalGeneral - totalAbonado;

    $('#pagoTotalOrden').text(formatCOP(totalGeneral));
    $('#pagoTotalAbonado').text(formatCOP(totalAbonado));
    $('#pagoSaldo').text(formatCOP(saldo));

    // Color del saldo
    if (saldo <= 0) {
        $('#pagoSaldo').removeClass('text-danger').addClass('text-success');
    } else {
        $('#pagoSaldo').removeClass('text-success').addClass('text-danger');
    }
}

// ==========================================
// Step Navigation
// ==========================================
function irASeccion(num) {
    var sectionId = ['seccionCliente', 'seccionBosquejosPiezas', 'seccionItems', 'seccionFirma', 'seccionPagos', 'seccionFechas'];
    var target = $('#' + sectionId[num - 1]);
    if (target.length) {
        $('html, body').animate({ scrollTop: target.offset().top - 140 }, 300);
    }
    // Actualizar step activo
    $('.wizard-step').removeClass('active');
    $('.wizard-step[data-step="' + num + '"]').addClass('active');
}

function marcarStepCompletado(num) {
    $('.wizard-step[data-step="' + num + '"]').addClass('completed');
}

function desmarcarStep(num) {
    $('.wizard-step[data-step="' + num + '"]').removeClass('completed');
}

// ==========================================
// Recopilacion de Datos
// ==========================================
function recopilarDatosFormulario() {
    var items = [];
    $('#tbodyItems tr').each(function() {
        items.push({
            catalogo_item_id: $(this).find('.item-catalogo-id').val() || null,
            codigo: $(this).find('.item-codigo').val(),
            descripcion: $(this).find('.item-descripcion').val(),
            cantidad: parseFloat($(this).find('.item-cantidad').val()) || 0,
            precio_unitario: parseCOP($(this).find('.item-precio').val()),
            porcentaje_iva: $(this).find('.item-iva-check').is(':checked') ? WIZARD_CONFIG.ivaDefecto : 0,
            descuento_porcentaje: parseDescuento($(this).find('.item-descuento').val()),
            categoria: $(this).find('.item-categoria').val() || 'servicio'
        });
    });

    var piezas = [];
    $('#tbodyPiezas tr.pieza-row').each(function() {
        var bosquejoIdx = $(this).attr('data-bosquejo-index');
        var rowIdx = $(this).data('idx');
        var piezaId = $(this).data('pieza-id');
        var operarioVal = $(this).find('.pieza-operario').val();
        var operarioOrigRaw = $(this).attr('data-operario-original');
        var operarioOriginal = (operarioOrigRaw !== undefined && operarioOrigRaw !== '' && operarioOrigRaw !== null)
            ? parseInt(operarioOrigRaw) : null;
        piezas.push({
            id: piezaId || null,
            nombre: $(this).find('.pieza-nombre').val(),
            cantidad: parseInt($(this).find('.pieza-cantidad').val()) || 1,
            material: $(this).find('.pieza-material').val() || null,
            calibre: $(this).find('.pieza-calibre').val() || null,
            notas: $('#piezaNotasRow_' + rowIdx).find('.pieza-notas').val() || null,
            bosquejo_index: (bosquejoIdx !== '' && bosquejoIdx !== undefined) ? parseInt(bosquejoIdx) : null,
            operario_id: operarioVal ? parseInt(operarioVal) : null,
            operario_original_id: operarioOriginal
        });
    });

    // Filtrar bosquejos: solo enviar los referenciados por piezas
    var referencedIndices = {};
    piezas.forEach(function(p) {
        if (p.bosquejo_index !== null) referencedIndices[p.bosquejo_index] = true;
    });
    var bosquejosToSend = [];
    var indexMap = {};
    Object.keys(referencedIndices).sort(function(a,b){ return a-b; }).forEach(function(oldIdx) {
        var newIdx = bosquejosToSend.length;
        indexMap[parseInt(oldIdx)] = newIdx;
        bosquejosToSend.push(wizardState.bosquejos[parseInt(oldIdx)]);
    });
    // Remapear indices de piezas
    piezas.forEach(function(p) {
        if (p.bosquejo_index !== null && indexMap[p.bosquejo_index] !== undefined) {
            p.bosquejo_index = indexMap[p.bosquejo_index];
        }
    });

    var pagos = [];
    $('#pagosContainer .pago-row').each(function() {
        var monto = parseCOP($(this).find('.pago-monto').val());
        if (monto > 0) {
            pagos.push({
                monto: monto,
                metodo_pago: $(this).find('.pago-metodo').val(),
                referencia_pago: $(this).find('.pago-referencia').val() || null
            });
        }
    });

    return {
        orden_id: wizardState.ordenId,
        cliente_id: wizardState.clienteId,
        fecha_entrega: $('#fecha_entrega').val() || null,
        hora_entrega: $('#hora_entrega').val() || null,
        notas: $('#notas').val() || null,
        items: items,
        bosquejos: bosquejosToSend,
        piezas: piezas,
        pagos: pagos,
        firma_data: wizardState.firmaData || obtenerFirmaData()
    };
}

// ==========================================
// Guardar y Generar
// ==========================================
function guardarOrden(isAutoSave) {
    var data = recopilarDatosFormulario();

    if (!data.cliente_id && !isAutoSave) {
        Swal.fire('Error', 'Debe seleccionar un cliente para guardar la orden.', 'error');
        irASeccion(1);
        return;
    }

    // Si no tiene cliente y es autosave, no guardar
    if (!data.cliente_id && isAutoSave) return;

    // Validar sobrepago en guardado manual; en autosave abortar silenciosamente
    var sobrepago = validarSobrepagoWizard();
    if (!sobrepago.ok) {
        if (!isAutoSave) {
            Swal.fire('Error', sobrepago.mensaje, 'error');
            irASeccion(5);
        }
        return;
    }

    // Deshabilitar botones
    if (!isAutoSave) {
        $('#btnGuardar').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Guardando...');
    }

    wizardState.isSaving = true;

    // Backup en localStorage antes de enviar (proteccion contra corte de luz)
    if (window.SindenConexion) {
        SindenConexion.saveModuleData('wizard', wizardState.ordenId || 'new', {
            formData: data,
            timestamp: Date.now()
        });
    }

    // Determinar metodo segun modo edicion
    var ajaxMethod = (typeof EDIT_MODE !== 'undefined' && EDIT_MODE) ? 'PUT' : 'POST';

    $.ajax({
        url: ROUTES.guardar,
        method: ajaxMethod,
        headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function(response) {
            if (response.success) {
                wizardState.ordenId = response.orden_id;
                $('#orden_id').val(response.orden_id);

                // Sincronizar IDs y rutas de bosquejos desde el backend
                if (response.bosquejos && response.bosquejos.length > 0) {
                    sincronizarBosquejosDesdeRespuesta(response.bosquejos);
                }

                wizardState.lastSavedHash = JSON.stringify(recopilarDatosFormulario());

                // Limpiar backup de localStorage (datos guardados exitosamente)
                if (window.SindenConexion) {
                    SindenConexion.clearModuleData('wizard', wizardState.ordenId || 'new');
                }

                if (isAutoSave) {
                    // Auto-guardado silencioso, sin mensaje visible
                } else {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                        title: 'La orden ha sido guardada exitosamente.', showConfirmButton: false, timer: 3000 });
                }
            }
        },
        error: function(xhr) {
            if (!isAutoSave) handleAjaxError(xhr, 'guardar la orden');
        },
        complete: function() {
            if (!isAutoSave) {
                $('#btnGuardar').prop('disabled', false).html('<i class="bi bi-save me-1"></i> Guardar Orden');
            }
            wizardState.isSaving = false;
            if (wizardState.pendingSave) {
                wizardState.pendingSave = false;
                triggerAutoSave('pending');
            }
        }
    });
}

function generarOrden() {
    var data = recopilarDatosFormulario();

    // Validacion client-side
    var errores = validarParaGenerar(data);
    if (errores.length > 0) {
        Swal.fire({
            icon: 'error',
            title: 'Falta diligenciar informacion para poder GENERAR ORDEN',
            html: '<ul class="text-start">' + errores.map(function(e) { return '<li>' + e + '</li>'; }).join('') + '</ul>'
        });
        return;
    }

    // Confirmacion con boton habilitado tras 1 segundo
    Swal.fire({
        title: 'Esta seguro de generar orden?',
        text: 'Se asignara un numero consecutivo. Esta accion no se puede revertir.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4A7C59',
        confirmButtonText: 'Generar Orden',
        cancelButtonText: 'Cancelar',
        didOpen: function() {
            var btn = Swal.getConfirmButton();
            btn.disabled = true;
            setTimeout(function() { btn.disabled = false; }, 1000);
        }
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $('#btnGenerar').prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Generando...');

        $.ajax({
            url: ROUTES.generar,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': WIZARD_CONFIG.csrfToken },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Orden Generada',
                        text: response.message,
                        confirmButtonColor: '#4A7C59',
                        confirmButtonText: 'Aceptar'
                    }).then(function() {
                        window.location.href = ROUTES.panel;
                    });
                }
            },
            error: function(xhr) {
                handleAjaxError(xhr, 'generar la orden');
            },
            complete: function() {
                $('#btnGenerar').prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i> Generar Orden');
            }
        });
    });
}

function validarParaGenerar(data) {
    var errores = [];
    if (!data.cliente_id) errores.push('Debe seleccionar un cliente.');
    if (!data.items || data.items.length === 0) {
        errores.push('Debe agregar al menos un item.');
    } else {
        data.items.forEach(function(item, i) {
            var num = i + 1;
            if (!item.descripcion) errores.push('Item ' + num + ': falta descripcion.');
            if (!item.cantidad || item.cantidad <= 0) errores.push('Item ' + num + ': cantidad debe ser mayor a 0.');
            if (item.precio_unitario < 0) errores.push('Item ' + num + ': precio no valido.');
        });
    }
    if (!data.fecha_entrega) errores.push('Debe indicar la fecha de entrega.');
    if (!data.hora_entrega) errores.push('Debe indicar la hora de entrega.');

    var sobrepago = validarSobrepagoWizard();
    if (!sobrepago.ok) errores.push(sobrepago.mensaje);

    return errores;
}

/**
 * Valida que la suma de abonos del wizard no exceda el total calculado de la orden.
 * Devuelve { ok: true } o { ok: false, mensaje: '...' }.
 */
function validarSobrepagoWizard() {
    var totalAbonado = 0;
    $('#pagosContainer .pago-monto').each(function() {
        totalAbonado += parseCOP($(this).val());
    });

    var totalGeneral = 0;
    $('#tbodyItems tr').each(function() {
        var cantidad = parseFloat($(this).find('.item-cantidad').val()) || 0;
        var precio = parseCOP($(this).find('.item-precio').val());
        var iva = $(this).find('.item-iva-check').is(':checked') ? WIZARD_CONFIG.ivaDefecto : 0;
        var descPct = parseDescuento($(this).find('.item-descuento').val());
        // Peso colombiano sin centavos: redondear igual que el display y el backend
        var base = Math.round(cantidad * precio);
        var ivaVal = Math.round(base * (iva / 100));
        var descMonto = Math.round(base * descPct / 100);
        totalGeneral += base + ivaVal - descMonto;
    });

    if (totalAbonado > totalGeneral + 0.005) {
        return {
            ok: false,
            mensaje: 'La suma de abonos ($' + totalAbonado.toLocaleString('en-US') +
                     ') excede el total de la orden ($' + totalGeneral.toLocaleString('en-US') + ').',
        };
    }
    return { ok: true };
}

// ==========================================
// Auto-guardado
// ==========================================
function triggerAutoSave(motivo) {
    // Sin cliente: avisar una sola vez y abortar
    if (!wizardState.clienteId) {
        if (!wizardState.avisoSinClienteMostrado) {
            wizardState.avisoSinClienteMostrado = true;
            if (window.Swal) {
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'info',
                    title: 'Selecciona un cliente para activar el auto-guardado',
                    showConfirmButton: false, timer: 3000
                });
            }
        }
        return;
    }

    clearTimeout(wizardState.autoSaveDebounceTimer);
    wizardState.autoSaveDebounceTimer = setTimeout(function() {
        // Si hay un guardado en curso, encolar uno mas
        if (wizardState.isSaving) {
            wizardState.pendingSave = true;
            return;
        }
        // Evitar POST si nada cambio desde el ultimo guardado
        try {
            var currentHash = JSON.stringify(recopilarDatosFormulario());
            if (currentHash === wizardState.lastSavedHash) return;
        } catch (e) { /* noop */ }
        guardarOrden(true);
    }, 600);
}

function initAutoSaveOnEdit() {
    // Listeners delegados para edicion de filas existentes y campos globales
    var selector = '#tbodyItems input, #tbodyItems select, #tbodyItems textarea, '
        + '#tbodyPiezas input, #tbodyPiezas select, #tbodyPiezas textarea, '
        + '#pagosContainer input, #pagosContainer select, #pagosContainer textarea, '
        + '#fecha_entrega, #hora_entrega, #notas';
    $(document).on('input change', selector, function() {
        triggerAutoSave('edit');
    });
}

function initAutoSave() {
    var interval = WIZARD_CONFIG.autoSaveInterval || 300000; // 5 min default
    var idleTimer = null;

    function resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function() {
            if (wizardState.clienteId) {
                var currentHash = JSON.stringify(recopilarDatosFormulario());
                if (currentHash !== wizardState.lastSavedHash) {
                    guardarOrden(true);
                }
            }
        }, interval);
    }

    $(document).on('keypress click change input', resetIdleTimer);
}
