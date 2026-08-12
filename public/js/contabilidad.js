/**
 * SINDEN - Modulo Contabilidad JS
 * Maneja DataTables, aprobaciones AJAX, pagos inline y seleccion masiva.
 */

var ordenesPendientesTable = null;
var pagosPendientesTable = null;

// ============================================================
// ORDENES PENDIENTES
// ============================================================

function initOrdenesPendientesTable(config) {
    ordenesPendientesTable = $('#ordenesPendientesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: config.ajaxUrl,
            data: function(d) {
                d.numero_orden = $('#filtroNumeroOrden').val();
                d.cliente = $('#filtroCliente').val();
                d.fecha_desde = $('#filtroFechaDesde').val();
                d.fecha_hasta = $('#filtroFechaHasta').val();
            }
        },
        columns: [
            { data: 'numero_orden', name: 'numero_orden', width: '90px' },
            { data: 'cliente_nombre', name: 'cliente.nombre' },
            { data: 'total_formatted', name: 'total', className: 'text-end', width: '100px' },
            { data: 'pagado_formatted', name: 'total_pagado', className: 'text-end', width: '100px' },
            { data: 'saldo_formatted', name: 'saldo', className: 'text-end', width: '110px' },
            { data: 'porcentaje_pagado', name: 'porcentaje_pagado', orderable: false, searchable: false, className: 'text-center', width: '80px' },
            { data: 'estado_trabajo_badge', name: 'estado_trabajo', className: 'text-center', width: '100px' },
            { data: 'pagos_pendientes', name: 'pagos_pendientes', orderable: false, searchable: false, className: 'text-center', width: '70px' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '100px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        order: [[0, 'desc']], // Ordenar por numero de orden descendente
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal || 0;
            $('#totalRegistros').text(total + ' registro' + (total !== 1 ? 's' : ''));
        }
    });

    // Filtros
    $('#btnFiltrar').on('click', function() {
        ordenesPendientesTable.draw();
    });

    $('#btnLimpiar').on('click', function() {
        $('#filtroNumeroOrden, #filtroCliente, #filtroFechaDesde, #filtroFechaHasta').val('');
        ordenesPendientesTable.draw();
    });

    // Enter para filtrar
    $('#filtroNumeroOrden, #filtroCliente').on('keypress', function(e) {
        if (e.which === 13) ordenesPendientesTable.draw();
    });

    // Abrir modal agregar pago
    $(document).on('click', '.btn-agregar-pago', function() {
        var btn = $(this);
        var saldoMax = parseFloat(btn.data('orden-saldo-num')) || 0;
        $('#pagoOrdenId').val(btn.data('orden-id')).data('saldo-max', saldoMax);
        $('#pagoMonto').attr('max', saldoMax).data('saldo-max', saldoMax);
        $('#pagoMontoMax').text('$' + saldoMax.toLocaleString('en-US'));

        // Build info HTML dynamically (Tailwind CDN strips empty divs)
        $('#infoPagoOrdenContainer').html(
            '<div class="alert alert-light border">' +
            '<div class="fw-semibold">Orden ' + btn.data('orden-numero') + '</div>' +
            '<div class="small text-muted">' + btn.data('orden-cliente') + '</div>' +
            '<div class="mt-1">Saldo: <span class="fw-bold text-danger">$' + btn.data('orden-saldo') + '</span></div>' +
            '<div class="small text-muted mt-1">Maximo permitido: <span class="fw-semibold">$' + saldoMax.toLocaleString('en-US') + '</span></div>' +
            '</div>'
        );

        $('#pagoMonto').val('');
        $('#pagoMetodo').prop('selectedIndex', 0);
        $('#pagoReferencia').val('');
        $('#modalAgregarPago').modal('show');

        // Focus monto despues de abrir
        setTimeout(function() { $('#pagoMonto').focus(); }, 500);
    });

    // Registrar pago
    $('#btnRegistrarPago').on('click', function() {
        var ordenId = $('#pagoOrdenId').val();
        var monto = $('#pagoMonto').val();
        var metodo = $('#pagoMetodo').val();
        var referencia = $('#pagoReferencia').val();

        if (!monto || parseFloat(monto) <= 0) {
            Swal.fire({ icon: 'warning', title: 'Monto requerido', text: 'Ingrese un monto valido mayor a 0.' });
            return;
        }

        var saldoMax = parseFloat($('#pagoOrdenId').data('saldo-max')) || 0;
        if (parseFloat(monto) > saldoMax + 0.005) {
            Swal.fire({
                icon: 'warning',
                title: 'Monto excede el saldo',
                text: 'El maximo permitido es $' + saldoMax.toLocaleString('en-US') + '.',
            });
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Registrando...');

        $.ajax({
            url: config.pagoStoreUrl + '/' + ordenId + '/pagos',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': config.csrfToken },
            data: { monto: monto, metodo_pago: metodo, referencia_pago: referencia },
            success: function(res) {
                $('#modalAgregarPago').modal('hide');
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: res.message || 'Pago registrado',
                    showConfirmButton: false, timer: 3000
                });
                ordenesPendientesTable.draw(false);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON?.message || 'Error al registrar el pago.';
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Registrar Pago');
            }
        });
    });
}

// ============================================================
// PAGOS PENDIENTES
// ============================================================

function actualizarStatsPendientes(stats) {
    if (!stats) return;
    $('#statPorAprobar .card-value').text(stats.por_aprobar);
    $('#statMontoPendiente .card-value').text(stats.monto_pendiente);
    $('#statAprobadosHoy .card-value').text(stats.aprobados_hoy);
}

var selectedPagos = {};

function initPagosPendientesTable(config) {
    pagosPendientesTable = $('#pagosPendientesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: config.ajaxUrl,
        columns: [
            { data: 'checkbox', name: 'checkbox', orderable: false, searchable: false, className: 'text-center', width: '40px' },
            { data: 'fecha_formatted', name: 'fecha_formatted', width: '130px' },
            { data: 'numero_orden', name: 'numero_orden', width: '90px' },
            { data: 'cliente_nombre', name: 'cliente.nombre' },
            { data: 'monto_formatted', name: 'monto', className: 'text-end', width: '120px' },
            { data: 'metodo_badge', name: 'metodo_pago', className: 'text-center', width: '120px' },
            { data: 'referencia_pago', name: 'referencia_pago', defaultContent: '-' },
            { data: 'registrado_por_nombre', name: 'registrado_por_nombre' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '110px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        order: [[1, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal || 0;
            $('#totalRegistros').text(total + ' registro' + (total !== 1 ? 's' : ''));
            // Restaurar checkboxes seleccionados
            restoreCheckboxes();
        }
    });

    // Select All
    $('#selectAllPagos').on('change', function() {
        var checked = $(this).is(':checked');
        $('.pago-checkbox').each(function() {
            $(this).prop('checked', checked);
            var id = $(this).val();
            var monto = parseFloat($(this).data('monto'));
            if (checked) {
                selectedPagos[id] = monto;
            } else {
                delete selectedPagos[id];
            }
        });
        updateBulkBar();
    });

    // Individual checkbox
    $(document).on('change', '.pago-checkbox', function() {
        var id = $(this).val();
        var monto = parseFloat($(this).data('monto'));
        if ($(this).is(':checked')) {
            selectedPagos[id] = monto;
        } else {
            delete selectedPagos[id];
        }
        updateBulkBar();

        // Update select all state
        var total = $('.pago-checkbox').length;
        var checked = $('.pago-checkbox:checked').length;
        $('#selectAllPagos').prop('checked', total > 0 && total === checked);
    });

    // Aprobar individual
    $(document).on('click', '.btn-aprobar-pago', function() {
        var btn = $(this);
        var pagoId = btn.data('pago-id');
        var monto = btn.data('pago-monto');
        var metodo = btn.data('pago-metodo');
        var orden = btn.data('orden-numero');

        Swal.fire({
            title: 'Aprobar pago?',
            html: '<div class="text-start">'
                + '<div class="mb-2"><strong>Monto:</strong> $' + monto + '</div>'
                + '<div class="mb-2"><strong>Metodo:</strong> ' + metodo + '</div>'
                + '<div><strong>Orden:</strong> ' + orden + '</div>'
                + '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i>Aprobar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: config.aprobarUrl + '/' + pagoId + '/aprobar',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': config.csrfToken },
                    success: function(res) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: res.message || 'Pago aprobado',
                            showConfirmButton: false, timer: 3000
                        });
                        delete selectedPagos[pagoId];
                        updateBulkBar();
                        actualizarStatsPendientes(res.stats);
                        pagosPendientesTable.draw(false);
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Error al aprobar.' });
                    }
                });
            }
        });
    });

    // Rechazar individual
    $(document).on('click', '.btn-rechazar-pago', function() {
        var btn = $(this);
        var pagoId = btn.data('pago-id');
        var monto = btn.data('pago-monto');
        var orden = btn.data('orden-numero');

        Swal.fire({
            title: 'Rechazar pago?',
            html: 'Se eliminara el pago de <strong>$' + monto + '</strong> de la orden <strong>' + orden + '</strong>.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-x-lg me-1"></i>Rechazar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: config.rechazarUrl + '/' + pagoId + '/rechazar',
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': config.csrfToken },
                    success: function(res) {
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: res.message || 'Pago rechazado',
                            showConfirmButton: false, timer: 3000
                        });
                        delete selectedPagos[pagoId];
                        updateBulkBar();
                        actualizarStatsPendientes(res.stats);
                        pagosPendientesTable.draw(false);
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Error al rechazar.' });
                    }
                });
            }
        });
    });

    // Aprobar seleccionados (bulk)
    $('#btnAprobarSeleccionados').on('click', function() {
        aprobarMasivo(config);
    });

    // Aprobar todos
    $('#btnAprobarTodos').on('click', function() {
        // Seleccionar todos los visibles primero
        $('.pago-checkbox').each(function() {
            $(this).prop('checked', true);
            selectedPagos[$(this).val()] = parseFloat($(this).data('monto'));
        });
        updateBulkBar();
        aprobarMasivo(config);
    });
}

function aprobarMasivo(config) {
    var ids = Object.keys(selectedPagos);
    if (ids.length === 0) {
        Swal.fire({ icon: 'info', title: 'Sin seleccion', text: 'Seleccione al menos un pago para aprobar.' });
        return;
    }

    var montoTotal = 0;
    ids.forEach(function(id) { montoTotal += selectedPagos[id]; });

    Swal.fire({
        title: '<span style="color:#dc3545;"><i class="bi bi-exclamation-triangle-fill me-2"></i>Accion Delicada</span>',
        html: '<div style="text-align:center;">' +
              '<p style="font-size:1.1rem;margin-bottom:0.75rem;">Esta a punto de aprobar <strong style="color:#dc3545;">' + ids.length + ' pago(s)</strong></p>' +
              '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:0.75rem;margin:0.75rem 0;">' +
              '<div style="font-size:0.9rem;color:#856404;">Monto total a aprobar</div>' +
              '<div style="font-size:1.5rem;font-weight:bold;color:#dc3545;">$' + formatNumber(montoTotal) + '</div>' +
              '</div>' +
              '<div style="background:#f8d7da;border:1px solid #dc3545;border-radius:8px;padding:0.75rem;color:#721c24;font-size:0.9rem;">' +
              '<i class="bi bi-shield-exclamation me-1"></i><strong>Atencion:</strong> Esta accion aprobara todos los pagos seleccionados y no se puede deshacer. Verifique antes de continuar.' +
              '</div>' +
              '</div>',
        icon: 'warning',
        iconColor: '#dc3545',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-check-all me-1"></i>Si, aprobar todos',
        cancelButtonText: '<i class="bi bi-x-lg me-1"></i>Cancelar',
        focusCancel: true,
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: config.aprobarMasivoUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': config.csrfToken },
                data: { pago_ids: ids },
                success: function(res) {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: res.message || 'Pagos aprobados',
                        showConfirmButton: false, timer: 4000
                    });
                    selectedPagos = {};
                    updateBulkBar();
                    actualizarStatsPendientes(res.stats);
                    pagosPendientesTable.draw();
                },
                error: function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Error al aprobar pagos.' });
                }
            });
        }
    });
}

function updateBulkBar() {
    var ids = Object.keys(selectedPagos);
    var count = ids.length;

    if (count > 0) {
        var montoTotal = 0;
        ids.forEach(function(id) { montoTotal += selectedPagos[id]; });
        $('#bulkCount').text(count);
        $('#bulkMonto').text('$' + formatNumber(montoTotal));
        $('#bulkBar').slideDown(200);
    } else {
        $('#bulkBar').slideUp(200);
    }
}

function restoreCheckboxes() {
    $('.pago-checkbox').each(function() {
        var id = $(this).val();
        if (selectedPagos[id] !== undefined) {
            $(this).prop('checked', true);
        }
    });
}

// ============================================================
// HISTORIAL FINANCIERO
// ============================================================

var historialFinancieroTable = null;

function initHistorialFinancieroTable(config) {
    historialFinancieroTable = $('#historialFinancieroTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: config.ajaxUrl,
            data: function(d) {
                d.numero_orden = $('#filtroNumeroOrden').val();
                d.cliente = $('#filtroCliente').val();
                d.estado_pago = $('#filtroEstadoPago').val();
                d.fecha_desde = $('#filtroFechaDesde').val();
                d.fecha_hasta = $('#filtroFechaHasta').val();
            }
        },
        columns: [
            { data: 'numero_orden', name: 'numero_orden', width: '80px' },
            { data: 'cliente_nombre', name: 'cliente.nombre' },
            { data: 'fecha_creacion', name: 'created_at', width: '95px' },
            { data: 'total_formatted', name: 'total', className: 'text-end', width: '100px' },
            { data: 'pagado_formatted', name: 'total_pagado', className: 'text-end', width: '100px' },
            { data: 'saldo_formatted', name: 'saldo', className: 'text-end', width: '100px' },
            { data: 'porcentaje_pagado', name: 'porcentaje_pagado', orderable: false, searchable: false, className: 'text-center', width: '70px' },
            { data: 'estado_pago_badge', name: 'estado_pago', className: 'text-center', width: '110px' },
            { data: 'num_pagos', name: 'pagos_count', orderable: false, searchable: false, className: 'text-center', width: '60px' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, className: 'text-end', width: '90px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        order: [[2, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50], [10, 15, 25, 50]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var total = settings._iRecordsTotal || 0;
            $('#totalRegistros').text(total + ' registro' + (total !== 1 ? 's' : ''));
            // Total del FILTRO COMPLETO: lo calcula el servidor sobre todas las ordenes
            // del filtro (no solo la pagina visible), asi nunca se cuelga y siempre es el
            // total real del dia/filtro aunque haya cientos de ordenes.
            var json = settings.json;
            var t = json && json.totalesFiltro;
            if (t) {
                $('#hfCount').text(t.count);
                $('#hfTotal').text(t.total);
                $('#hfPagado').text(t.pagado);
                $('#hfSaldo').text(t.saldo);
                $('#hfSubtotal').text(t.subtotal);
                $('#hfIva').text(t.iva);
            }
            // Tarjetas de resumen ACOTADAS al filtro (ya no son totales globales sin fin)
            if (json && json.cards) {
                $('#cardTotalOrdenes').text(json.cards.totalOrdenes);
                $('#cardOrdenesPagadas').text(json.cards.ordenesPagadas);
                $('#cardTotalRecaudado').text(json.cards.totalRecaudado);
                $('#cardTotalPorCobrar').text(json.cards.totalPorCobrar);
            }
            if (json && json.rango) { $('#rangoResumen').text(json.rango); }
        }
    });

    // Filtros
    $('#btnFiltrarHistorial').on('click', function() {
        historialFinancieroTable.draw();
        actualizarExportUrlHistorial(config.exportUrl);
    });

    $('#btnLimpiarHistorial').on('click', function() {
        $('#filtroNumeroOrden, #filtroCliente, #filtroFechaDesde, #filtroFechaHasta').val('');
        $('#filtroEstadoPago').val('todos');
        historialFinancieroTable.draw();
        actualizarExportUrlHistorial(config.exportUrl);
    });

    // Enter para filtrar
    $('#filtroNumeroOrden, #filtroCliente').on('keypress', function(e) {
        if (e.which === 13) {
            historialFinancieroTable.draw();
            actualizarExportUrlHistorial(config.exportUrl);
        }
    });

    // URL inicial del export
    if (config.exportUrl) {
        actualizarExportUrlHistorial(config.exportUrl);
    }

    // Al hacer clic en Excel, refrescar la URL con los filtros actuales (por si cambiaron
    // un filtro y no dieron "Filtrar"). El href se actualiza antes de que el navegador
    // siga el enlace, asi el Excel siempre respeta los filtros vigentes.
    $('#btnExportar').on('click', function() {
        actualizarExportUrlHistorial(config.exportUrl);
    });

    // Ver pagos de una orden (modal)
    $(document).on('click', '.btn-ver-pagos', function() {
        var btn = $(this);
        var ordenId = btn.data('orden-id');
        var ordenNumero = btn.data('orden-numero');

        $('#modalOrdenNumero').text(ordenNumero);
        $('#modalResumenContainer').html('');
        $('#modalPagosContainer').html(
            '<div class="text-center py-4">' +
            '<div class="spinner-border text-primary" role="status"></div>' +
            '<div class="mt-2 text-muted">Cargando pagos...</div>' +
            '</div>'
        );
        $('#modalHistorialPagos').modal('show');

        $.ajax({
            url: config.pagosUrl + '/' + ordenId + '/pagos',
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': config.csrfToken },
            success: function(res) {
                // Resumen
                var estadoBadge = '';
                var saldoRaw = Number(res.orden.saldo_raw || 0);
                if (res.orden.estado_pago === 'pagada' || saldoRaw === 0) {
                    estadoBadge = '<span class="badge bg-success">PAGADA</span>';
                } else if (saldoRaw < 0) {
                    estadoBadge = '<span class="badge bg-info text-dark">SALDO A FAVOR</span>';
                } else if (res.pagos.length > 0) {
                    estadoBadge = '<span class="badge bg-danger">SALDO PEND.</span>';
                } else {
                    estadoBadge = '<span class="badge bg-secondary">SIN PAGOS</span>';
                }

                var saldoLabel, saldoClass;
                if (saldoRaw < 0) {
                    saldoLabel = 'Saldo a favor del cliente:';
                    saldoClass = 'text-info';
                } else {
                    saldoLabel = 'Saldo:';
                    saldoClass = 'text-danger';
                }

                $('#modalResumenContainer').html(
                    '<div class="alert alert-light border mb-3">' +
                    '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<span class="fw-semibold">' + res.orden.cliente + '</span>' +
                    estadoBadge +
                    '</div>' +
                    '<div class="d-flex gap-3 flex-wrap">' +
                    '<span class="small"><strong>Total:</strong> ' + res.orden.total + '</span>' +
                    '<span class="small text-success"><strong>Pagado:</strong> ' + res.orden.total_pagado + '</span>' +
                    '<span class="small ' + saldoClass + '"><strong>' + saldoLabel + '</strong> ' + res.orden.saldo + '</span>' +
                    '</div>' +
                    '</div>'
                );

                // Tabla de pagos
                if (res.pagos.length === 0) {
                    $('#modalPagosContainer').html(
                        '<div class="text-center py-4 text-muted">' +
                        '<i class="bi bi-inbox fs-1 d-block mb-2"></i>' +
                        'Esta orden no tiene pagos registrados.' +
                        '</div>'
                    );
                    return;
                }

                var html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">';
                html += '<thead class="table-light"><tr>';
                html += '<th>#</th><th class="text-end">Monto</th><th class="text-center">Metodo</th>';
                html += '<th>Referencia</th><th>Creado Por</th><th class="text-center">Estado</th>';
                html += '</tr></thead><tbody>';

                for (var i = 0; i < res.pagos.length; i++) {
                    var p = res.pagos[i];
                    var estadoPago;

                    if (p.rechazado) {
                        estadoPago = '<span class="badge bg-danger bg-opacity-10 text-danger border">Rechazado</span>';
                        if (p.rechazado_por) {
                            estadoPago += '<br><small class="text-muted">Rechazado por ' + p.rechazado_por + '</small>';
                        }
                        if (p.fecha_rechazo) {
                            estadoPago += '<br><small class="text-muted">' + p.fecha_rechazo + '</small>';
                        }
                    } else if (p.aprobado) {
                        estadoPago = '<span class="badge bg-success bg-opacity-10 text-success border">Aprobado</span>';
                        if (p.aprobado_por) {
                            estadoPago += '<br><small class="text-muted">Aprobado por ' + p.aprobado_por + '</small>';
                        }
                        if (p.fecha_aprobacion) {
                            estadoPago += '<br><small class="text-muted">' + p.fecha_aprobacion + '</small>';
                        }
                    } else {
                        estadoPago = '<span class="badge bg-warning bg-opacity-10 text-dark border">Pendiente</span>';
                    }

                    var rowStyle = p.rechazado ? ' style="opacity:0.55;"' : '';
                    var montoStyle = p.rechazado ? ' style="text-decoration:line-through;"' : '';

                    var creadoPor = '<div>' + p.registrado_por + '</div>';
                    creadoPor += '<small class="text-muted">' + p.fecha + '</small>';

                    html += '<tr' + rowStyle + '>';
                    html += '<td class="text-muted">' + (i + 1) + '</td>';
                    html += '<td class="text-end fw-semibold"' + montoStyle + '>' + p.monto + '</td>';
                    html += '<td class="text-center">' + p.metodo_badge + '</td>';
                    html += '<td class="small">' + p.referencia_pago + '</td>';
                    html += '<td class="small">' + creadoPor + '</td>';
                    html += '<td class="text-center">' + estadoPago + '</td>';
                    html += '</tr>';
                }

                html += '</tbody></table></div>';
                $('#modalPagosContainer').html(html);
            },
            error: function() {
                $('#modalPagosContainer').html(
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>Error al cargar los pagos.' +
                    '</div>'
                );
            }
        });
    });
}

function formatNumber(num) {
    // Contabilidad usa formato US: coma para miles (el resto de la app usa punto).
    return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function actualizarExportUrlHistorial(baseUrl) {
    if (!baseUrl) return;
    var params = new URLSearchParams();
    var numeroOrden = $('#filtroNumeroOrden').val();
    var cliente = $('#filtroCliente').val();
    var estadoPago = $('#filtroEstadoPago').val();
    var desde = $('#filtroFechaDesde').val();
    var hasta = $('#filtroFechaHasta').val();
    if (numeroOrden) params.set('numero_orden', numeroOrden);
    if (cliente) params.set('cliente', cliente);
    if (estadoPago && estadoPago !== 'todos') params.set('estado_pago', estadoPago);
    if (desde) params.set('fecha_desde', desde);
    if (hasta) params.set('fecha_hasta', hasta);
    var qs = params.toString();
    $('#btnExportar').attr('href', baseUrl + (qs ? '?' + qs : ''));
}

// ============================================================
// REPORTE VENTAS POR ITEMS
// ============================================================

var reporteItemsTable = null;

function initReporteItemsTable(config) {
    reporteItemsTable = $('#reporteItemsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: config.ajaxUrl,
            data: function(d) {
                d.busqueda = $('#filtroBusqueda').val();
                d.categoria = $('#filtroCategoria').val();
                d.estado_pago = $('#filtroEstadoPago').val();
                d.fecha_desde = $('#filtroFechaDesde').val();
                d.fecha_hasta = $('#filtroFechaHasta').val();
            }
        },
        columns: [
            { data: 'numero_orden_link', name: 'ordenes.numero_orden', width: '80px' },
            { data: 'fecha_orden_formatted', name: 'ordenes.created_at', width: '90px' },
            { data: 'codigo', name: 'orden_items.codigo', width: '100px' },
            { data: 'descripcion', name: 'orden_items.descripcion' },
            { data: 'categoria_badge', name: 'orden_items.categoria', className: 'text-center', width: '120px' },
            { data: 'cantidad_formatted', name: 'orden_items.cantidad', className: 'text-center', width: '80px' },
            { data: 'precio_formatted', name: 'orden_items.precio_unitario', className: 'text-end', width: '100px' },
            { data: 'descuento_formatted', name: 'orden_items.descuento_porcentaje', className: 'text-center', width: '90px', orderable: false },
            { data: 'subtotal_formatted', name: 'orden_items.subtotal', className: 'text-end', width: '100px' },
            { data: 'iva_formatted', name: 'orden_items.monto_iva', className: 'text-end', width: '80px' },
            { data: 'total_formatted', name: 'orden_items.total', className: 'text-end', width: '100px' }
        ],
        dom: '<"d-flex flex-wrap align-items-center justify-content-between mb-2"<"d-flex align-items-center gap-2"lB>f>rt<"d-flex justify-content-between"ip>',
        buttons: [
            { extend: 'colvis', text: '<i class="bi bi-layout-three-columns"></i> Columnas', className: 'btn btn-sm btn-outline-secondary' }
        ],
        order: [[1, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100], [10, 15, 25, 50, 100]],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        drawCallback: function(settings) {
            var info = reporteItemsTable.page.info();
            $('#totalRegistros').text(info.recordsTotal + ' registro' + (info.recordsTotal !== 1 ? 's' : ''));
            // Actualizar totales del footer
            var json = settings.json;
            if (json && json.totales) {
                $('#sumaSubtotal').text(json.totales.subtotal);
                $('#sumaIva').text(json.totales.iva);
                $('#sumaTotal').text(json.totales.total);
                if (json.totales.descuento !== undefined) {
                    $('#sumaDescuento').text('-' + json.totales.descuento);
                }
            }
            // Tarjetas de resumen ACOTADAS al filtro (ya no son totales globales sin fin)
            if (json && json.cards) {
                $('#cardServicios').text(json.cards.servicios);
                $('#cardMateriales').text(json.cards.materiales);
                $('#cardProductos').text(json.cards.productos);
                $('#cardSinIva').text(json.cards.sinIva);
                $('#cardIva').text(json.cards.iva);
                $('#cardDescuentos').text(json.cards.descuentos);
                $('#cardGranTotal').text(json.cards.granTotal);
            }
            if (json && json.rango) { $('#rangoResumen').text(json.rango); }
        }
    });

    // Filtrar
    $('#btnFiltrarReporte').on('click', function() {
        reporteItemsTable.draw();
        actualizarExportUrl(config.exportUrl);
    });

    // Limpiar
    $('#btnLimpiarReporte').on('click', function() {
        $('#filtroBusqueda').val('');
        $('#filtroCategoria').val('todas');
        $('#filtroEstadoPago').val('todos');
        $('#filtroFechaDesde').val('');
        $('#filtroFechaHasta').val('');
        reporteItemsTable.draw();
        actualizarExportUrl(config.exportUrl);
    });

    // Enter en busqueda
    $('#filtroBusqueda').on('keypress', function(e) {
        if (e.which === 13) {
            reporteItemsTable.draw();
            actualizarExportUrl(config.exportUrl);
        }
    });

    // URL inicial del export + refresco al hacer clic en Excel (igual que Historial
    // Financiero): asi el Excel siempre respeta los filtros vigentes aunque el usuario
    // cambie un filtro y no de "Filtrar".
    actualizarExportUrl(config.exportUrl);
    $('#btnExportar').on('click', function() {
        actualizarExportUrl(config.exportUrl);
    });
}

function actualizarExportUrl(baseUrl) {
    var params = new URLSearchParams();
    var busqueda = $('#filtroBusqueda').val();
    var categoria = $('#filtroCategoria').val();
    var estadoPago = $('#filtroEstadoPago').val();
    var desde = $('#filtroFechaDesde').val();
    var hasta = $('#filtroFechaHasta').val();
    if (busqueda) params.set('busqueda', busqueda);
    if (categoria && categoria !== 'todas') params.set('categoria', categoria);
    if (estadoPago && estadoPago !== 'todos') params.set('estado_pago', estadoPago);
    if (desde) params.set('fecha_desde', desde);
    if (hasta) params.set('fecha_hasta', hasta);
    var qs = params.toString();
    $('#btnExportar').attr('href', baseUrl + (qs ? '?' + qs : ''));
}
