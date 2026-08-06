/**
 * SINDEN - Orden Edit Init
 * Precarga el wizard con datos existentes de la orden para modo edicion.
 */

$(function() {
    if (typeof ORDEN_DATA === 'undefined' || !ORDEN_DATA) return;

    // Establecer ordenId en el estado global
    wizardState.ordenId = ORDEN_DATA.id;

    // Delay to ensure wizard is fully initialized
    setTimeout(function() {
        cargarCliente();
        cargarBosquejos();
        cargarPiezas();
        cargarItems();
        cargarPagos();
        cargarFechas();
        cargarFirma();

        // Set initial saved hash to prevent immediate auto-save
        wizardState.lastSavedHash = JSON.stringify(recopilarDatosFormulario());
    }, 200);
});

// ==========================================
// Cargar Cliente
// ==========================================
function cargarCliente() {
    if (!ORDEN_DATA.cliente) return;
    var c = ORDEN_DATA.cliente;
    seleccionarCliente(c.id, c.nombre, c.celular_1 || '', c.correo || '');
}

// ==========================================
// Cargar Items
// ==========================================
function cargarItems() {
    if (!ORDEN_DATA.items || ORDEN_DATA.items.length === 0) return;

    ORDEN_DATA.items.forEach(function(item) {
        agregarFilaItem({ skipFocus: true, skipAutoSave: true });
        var idx = wizardState.itemCounter;
        var $row = $('#itemRow_' + idx);

        $row.find('.item-catalogo-id').val(item.catalogo_item_id || '');
        $row.find('.item-codigo').val(item.codigo || '');
        $row.find('.item-descripcion').val(item.descripcion || '');
        // Cantidad "inteligente": 44.00 -> 44, 44.5 -> 44,5 (no forzar ",00" al cargar).
        var _cantItem = parseFloat(item.cantidad);
        $row.find('.item-cantidad').val(isNaN(_cantItem) ? 1 : _cantItem).each(function(){ autoExpandCantidad(this); });
        setValorMoneda($row.find('.item-precio'), item.precio_unitario || 0);
        $row.find('.item-iva-check').prop('checked', parseFloat(item.porcentaje_iva) > 0);
        // Descuento con decimales: 2.50 -> 2.5, 5.00 -> 5 (no truncar con parseInt).
        $row.find('.item-descuento').val(parseDescuento(item.descuento_porcentaje)).each(function(){ autoExpandCantidad(this); });
        $row.find('.item-categoria').val(item.categoria || 'servicio');

        if (item.catalogo_item_id) {
            $row.find('.item-codigo').prop('readonly', true).addClass('item-readonly');
            $row.find('.item-descripcion').prop('readonly', true).addClass('item-readonly');
            $row.find('.btn-desvincular-item').show();
        }

        calcularTotalFila(idx);
    });

    recalcularTotales();
}

// ==========================================
// Cargar Bosquejos
// ==========================================
function cargarBosquejos() {
    if (!ORDEN_DATA.bosquejos || ORDEN_DATA.bosquejos.length === 0) return;

    ORDEN_DATA.bosquejos.forEach(function(b) {
        wizardState.bosquejos.push({
            id: b.id,
            nombre: b.nombre,
            tipo_origen: b.tipo_origen,
            ruta_archivo: b.ruta_archivo,
            ruta_miniatura: b.ruta_miniatura,
            plantilla_bosquejo_id: b.plantilla_bosquejo_id
        });
    });

    renderizarGrillaBosquejos();
    actualizarSelectBosquejosPiezas();
}

// ==========================================
// Cargar Piezas
// ==========================================
function cargarPiezas() {
    if (!ORDEN_DATA.piezas || ORDEN_DATA.piezas.length === 0) return;

    ORDEN_DATA.piezas.forEach(function(pieza) {
        agregarFilaPieza({ skipAutoSave: true });
        var idx = wizardState.piezaCounter;
        var $row = $('#piezaRow_' + idx);

        if (pieza.id) {
            $row.data('pieza-id', pieza.id);
        }

        $row.find('.pieza-nombre').val(pieza.nombre || '');
        $row.find('.pieza-cantidad').val(parseInt(pieza.cantidad, 10) || 1).each(function(){ autoExpandCantidad(this); });

        if (pieza.material) {
            $row.find('.pieza-material').val(pieza.material);
        }
        if (pieza.calibre) {
            $row.find('.pieza-calibre').val(pieza.calibre).each(function(){ autoExpandSelect(this); });
        }
        if (pieza.notas) {
            $('#piezaNotasRow_' + idx).find('.pieza-notas').val(pieza.notas);
        }

        // Match bosquejo by DB ID in wizardState.bosquejos array
        if (pieza.orden_bosquejo_id) {
            var bosquejoIdx = -1;
            for (var i = 0; i < wizardState.bosquejos.length; i++) {
                if (wizardState.bosquejos[i].id == pieza.orden_bosquejo_id) {
                    bosquejoIdx = i;
                    break;
                }
            }
            if (bosquejoIdx >= 0) {
                vincularBosquejoAPieza(idx, bosquejoIdx);
            }
        }

        // Preseleccionar operario asignado a esta pieza (si aplica)
        if (pieza.operario_actual_id) {
            $row.find('.pieza-operario').val(String(pieza.operario_actual_id));
        }
        // Guardar el operario ORIGINAL con el que se cargo la pieza. El backend lo usa
        // (fix Opcion A) para NO revertir una transferencia que un operario hizo en vivo:
        // Recepcion solo cambia el operario si de verdad modifica este campo.
        $row.attr('data-operario-original', pieza.operario_actual_id ? String(pieza.operario_actual_id) : '');

        generarEspecificacion(idx);
    });
}

// ==========================================
// Cargar Pagos
// ==========================================
function cargarPagos() {
    if (!ORDEN_DATA.pagos || ORDEN_DATA.pagos.length === 0) return;

    var bloquearPagos = (typeof IS_GENERATED !== 'undefined' && IS_GENERATED)
                     && (typeof IS_ADMIN !== 'undefined' && !IS_ADMIN);

    ORDEN_DATA.pagos.forEach(function(pago) {
        agregarFilaPago({ skipAutoSave: true });
        var idx = wizardState.pagoCounter;
        var $row = $('#pagoRow_' + idx);

        setValorMoneda($row.find('.pago-monto'), pago.monto || 0);
        var $metodoSel = $row.find('.pago-metodo');
        var metodo = pago.metodo_pago || (window.TIPOS_PAGO && window.TIPOS_PAGO[0] ? window.TIPOS_PAGO[0].codigo : 'efectivo');
        // Si el codigo historico ya no esta en el select (tipo desactivado/eliminado), agregarlo como option para preservar el valor.
        if (metodo && $metodoSel.find('option[value="' + metodo + '"]').length === 0) {
            var etiquetaHist = metodo;
            if (window.TIPOS_PAGO_MAPA && window.TIPOS_PAGO_MAPA[metodo]) {
                etiquetaHist = window.TIPOS_PAGO_MAPA[metodo].codigo + ' - ' + window.TIPOS_PAGO_MAPA[metodo].nombre;
            }
            $metodoSel.append('<option value="' + metodo + '">' + etiquetaHist + ' (inactivo)</option>');
        }
        $metodoSel.val(metodo);
        $row.find('.pago-referencia').val(pago.referencia_pago || '');

        if (bloquearPagos) {
            $row.find('.pago-monto').prop('disabled', true);
            $row.find('.pago-metodo').prop('disabled', true);
            $row.find('.pago-referencia').prop('disabled', true);
            $row.find('.btn-outline-danger').remove();
        }
    });

    // Si no es admin y la orden ya fue generada, ocultar boton de agregar pago
    if (bloquearPagos) {
        $('#seccionPagos .card-header .btn-primary').hide();
    }

    recalcularSaldo();
}

// ==========================================
// Cargar Fechas y Notas
// ==========================================
function cargarFechas() {
    if (ORDEN_DATA.fecha_entrega) {
        var inputFecha = document.getElementById('fecha_entrega');
        // El input de fecha usa Flatpickr con altInput: si solo seteamos el value
        // del input real (que queda oculto), el campo visible no muestra la fecha.
        // Hay que actualizar la instancia de Flatpickr con setDate.
        if (inputFecha && inputFecha._flatpickr) {
            inputFecha._flatpickr.setDate(ORDEN_DATA.fecha_entrega, false);
        } else {
            $('#fecha_entrega').val(ORDEN_DATA.fecha_entrega);
        }
        marcarStepCompletado(6);
    }
    if (ORDEN_DATA.hora_entrega) {
        // La hora guardada puede traer segundos (HH:MM:SS); el select usa HH:MM.
        var hora = ORDEN_DATA.hora_entrega.substring(0, 5);
        var $hora = $('#hora_entrega');
        // Si la hora guardada no existe como opcion (orden antigua con minutos
        // distintos a 00/30), agregarla para que se muestre correctamente.
        if ($hora.find('option[value="' + hora + '"]').length === 0) {
            $hora.append('<option value="' + hora + '">' + hora + '</option>');
        }
        $hora.val(hora);
    }
    if (ORDEN_DATA.notas) {
        $('#notas').val(ORDEN_DATA.notas);
    }
}

// ==========================================
// Cargar Firma existente
// ==========================================
function cargarFirma() {
    if (!ORDEN_DATA.ruta_firma_cliente) return;

    var src = ORDEN_DATA.ruta_firma_cliente;
    if (src && !src.startsWith('http') && !src.startsWith('/') && !src.startsWith('data:')) {
        src = '/' + src;
    }

    // Pintar la firma existente sobre el canvas (no como img separado)
    if (typeof cargarFirmaEnCanvas === 'function') {
        cargarFirmaEnCanvas(src);
    }
    marcarStepCompletado(4);
}

// ==========================================
// Liberar el candado de edicion al salir de la pagina, para que los operarios
// puedan volver a trabajar la orden de inmediato (no esperar a que expire).
// ==========================================
(function () {
    var liberado = false;
    function liberarEdicionOrden() {
        if (liberado) return;
        try {
            var id = (typeof ORDEN_DATA !== 'undefined' && ORDEN_DATA && ORDEN_DATA.id) ? ORDEN_DATA.id : null;
            if (!id) return;
            var metaTok = document.querySelector('meta[name="csrf-token"]');
            var token = metaTok ? metaTok.getAttribute('content') : '';
            var fd = new FormData();
            fd.append('_token', token);
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/recepcion/ordenes/' + id + '/liberar-edicion', fd);
                liberado = true;
            }
        } catch (e) {}
    }
    window.addEventListener('pagehide', liberarEdicionOrden);
    window.addEventListener('beforeunload', liberarEdicionOrden);
})();
