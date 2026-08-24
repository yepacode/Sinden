/**
 * SINDEN - Conexion Handler (conexion-handler.js)
 * Maneja: deteccion online/offline, banner UI, intercepcion AJAX,
 * backup en localStorage, sincronizacion al reconectar, recuperacion de datos.
 */
window.SindenConexion = (function($) {
    'use strict';

    // ==========================================
    // ESTADO INTERNO
    // ==========================================
    var state = {
        online: true, // Asumir online hasta que un ping ACTIVO falle (navigator.onLine no es confiable en Windows)
        lastPingSuccess: null,
        syncInProgress: false,
        pingInterval: null,
        initialized: false,
        confirmingOffline: false, // Flag para evitar multiples pings de confirmacion simultaneos
        consecutivePingFails: 0   // Requiere varios fallos consecutivos antes de marcar offline
    };

    var CONFIG = {
        PING_URL: '/api/ping',
        CSRF_REFRESH_URL: '/api/csrf-refresh',
        PING_INTERVAL_ONLINE: 15000,   // 15s cuando online
        PING_INTERVAL_OFFLINE: 5000,   // 5s cuando offline
        PING_TIMEOUT: 5000,            // timeout del ping
        LS_PREFIX: 'sinden_cx_',
        MAX_QUEUE_AGE_MS: 3600000,     // 1 hora max para datos encolados
        // URLs que se ignoran en deteccion de errores
        IGNORED_URL_PATTERNS: ['/heartbeat', 'draw=', '/api/ping', '/notificaciones']
    };

    // ==========================================
    // INICIALIZACION
    // ==========================================
    function init() {
        if (state.initialized) return;
        state.initialized = true;

        // Eventos del navegador (no confiar ciegamente: confirmar con ping activo)
        window.addEventListener('online', function() { doPing(); });
        window.addEventListener('offline', function() { confirmOffline(); });

        // Al volver a la pestana o enfocar la ventana: ping + refresco SILENCIOSO del
        // token, para que la sesion Y el token esten frescos ANTES de que el usuario
        // actue. Previene de raiz el 419 tras suspension del equipo o pestana dormida
        // (el timer de 15s puede haberse congelado mientras no estaba visible). Con
        // throttle porque visibilitychange y focus suelen dispararse casi a la vez.
        var ultimoDespertar = 0;
        function alDespertar() {
            var ahora = Date.now();
            if (ahora - ultimoDespertar < 2000) return; // throttle 2s
            ultimoDespertar = ahora;
            doPing();
            // Refresco silencioso: si la sesion murio, refreshCsrfToken rechaza y NO
            // molestamos aqui; el relogin se muestra cuando el usuario haga una accion real.
            refreshCsrfToken().catch(function() { /* noop */ });
        }
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) alDespertar();
        });
        window.addEventListener('focus', alDespertar);

        // Interceptor AJAX global
        setupAjaxInterceptor();
        setupGlobalAjaxError();

        // Iniciar ping
        startPing();

        // Verificar datos de recuperacion
        setTimeout(function() { checkRecoveryData(); }, 1500);

        // Limpiar datos expirados
        cleanExpiredData();
    }

    // ==========================================
    // DETECCION: PING ACTIVO
    // ==========================================
    function startPing() {
        if (state.pingInterval) clearInterval(state.pingInterval);

        var interval = state.online ? CONFIG.PING_INTERVAL_ONLINE : CONFIG.PING_INTERVAL_OFFLINE;
        state.pingInterval = setInterval(function() { doPing(); }, interval);
    }

    function doPing() {
        $.ajax({
            url: CONFIG.PING_URL,
            method: 'GET',
            timeout: CONFIG.PING_TIMEOUT,
            global: false, // No dispara ajaxError global
            success: function() {
                state.lastPingSuccess = Date.now();
                state.consecutivePingFails = 0;
                if (!state.online) setOnline();
            },
            error: function(xhr) {
                // Cualquier respuesta HTTP (incluso 4xx/5xx) significa que SI hay conexion
                if (xhr.status > 0) {
                    state.lastPingSuccess = Date.now();
                    state.consecutivePingFails = 0;
                    if (!state.online) setOnline();
                    return;
                }
                // status 0 = error de red real. Requerir 2 fallos consecutivos antes de marcar offline
                state.consecutivePingFails++;
                if (state.consecutivePingFails >= 2 && state.online) {
                    setOffline();
                }
            }
        });
    }

    // Confirmacion activa antes de marcar offline (evita falsos positivos)
    function confirmOffline() {
        if (state.confirmingOffline) return;
        state.confirmingOffline = true;
        $.ajax({
            url: CONFIG.PING_URL,
            method: 'GET',
            timeout: CONFIG.PING_TIMEOUT,
            global: false,
            complete: function(xhr) {
                state.confirmingOffline = false;
                // Si hubo cualquier respuesta del servidor, seguimos online
                if (xhr.status > 0) {
                    state.consecutivePingFails = 0;
                    if (!state.online) setOnline();
                    return;
                }
                // Sin respuesta -> realmente offline
                state.consecutivePingFails = 2;
                if (state.online) setOffline();
            }
        });
    }

    // ==========================================
    // TRANSICIONES DE ESTADO
    // ==========================================
    function setOnline() {
        if (state.online) return;
        state.online = true;

        // UI
        hideOfflineBanner();
        updateIndicator(true);
        enableSubmitButtons();

        // Reiniciar ping con intervalo normal
        startPing();

        // Sincronizar datos pendientes
        syncPendingData();
    }

    function setOffline() {
        if (!state.online) return;
        state.online = false;

        // UI
        showOfflineBanner();
        updateIndicator(false);
        disableSubmitButtons();

        // Reiniciar ping con intervalo rapido
        startPing();
    }

    function isOnline() {
        return state.online;
    }

    // ==========================================
    // UI: BANNER OFFLINE
    // ==========================================
    function showOfflineBanner() {
        var banner = $('#sindenOfflineBanner');
        if (banner.length) {
            banner.addClass('visible');
        }
    }

    function hideOfflineBanner() {
        var banner = $('#sindenOfflineBanner');
        if (banner.length) {
            banner.removeClass('visible');
        }
    }

    // ==========================================
    // UI: INDICADOR EN HEADER
    // ==========================================
    function updateIndicator(online) {
        var dot = $('#conexionDot');
        if (!dot.length) return;

        if (online) {
            dot.removeClass('offline').addClass('online').attr('title', 'Conectado');
        } else {
            dot.removeClass('online').addClass('offline').attr('title', 'Sin conexion');
        }
    }

    // ==========================================
    // UI: DESHABILITAR/HABILITAR BOTONES
    // ==========================================
    var SUBMIT_SELECTORS = [
        'button[type="submit"]',
        '#btnActualizarOrden',
        '#btnGuardar',
        '#btnGenerar',
        '#btnRegistrarPago',
        '.btn-aprobar-pago',
        '.btn-aprobar-masivo',
        '[data-action="guardar"]',
        '[data-action="generar"]'
    ].join(', ');

    function disableSubmitButtons() {
        $(SUBMIT_SELECTORS).each(function() {
            var $btn = $(this);
            if (!$btn.prop('disabled')) {
                $btn.prop('disabled', true).attr('data-offline-disabled', 'true');
            }
        });
    }

    function enableSubmitButtons() {
        $('[data-offline-disabled="true"]').each(function() {
            $(this).prop('disabled', false).removeAttr('data-offline-disabled');
        });
    }

    // ==========================================
    // INTERCEPCION AJAX: $.ajaxPrefilter
    // ==========================================
    function setupAjaxInterceptor() {
        $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
            // No interceptar pings propios
            if (options.url && options.url.indexOf('/api/ping') !== -1) return;
            if (options.url && options.url.indexOf('/api/csrf-refresh') !== -1) return;

            // Si estamos online, solo inyectar CSRF actualizado
            if (state.online) {
                injectCsrfToken(options);
                return;
            }

            // OFFLINE: determinar accion segun metodo y tipo de datos
            var method = (options.type || options.method || 'GET').toUpperCase();

            if (method === 'GET') {
                // GETs offline: abortar silenciosamente (excepto los ignorados)
                if (!isIgnoredUrl(options.url)) {
                    showToast('warning', 'Sin conexion', 'No se puede cargar la informacion sin conexion.');
                }
                jqXHR.abort();
                return;
            }

            // POST/PUT/DELETE offline
            var isFormData = options.data instanceof FormData ||
                            (options.contentType && options.contentType.indexOf('multipart') !== -1);

            if (isFormData) {
                // Archivos: no se pueden encolar
                Swal.fire({
                    icon: 'warning',
                    title: 'Sin conexion',
                    text: 'No se pueden enviar archivos sin conexion. Intente de nuevo cuando se restablezca la conexion.',
                    confirmButtonColor: '#4A7C59'
                });
                jqXHR.abort();
                return;
            }

            // JSON/form data: encolar
            var queueItem = {
                url: options.url,
                method: method,
                data: options.data,
                contentType: options.contentType || 'application/x-www-form-urlencoded',
                timestamp: Date.now()
            };

            addToQueue(queueItem);
            showToast('info', 'Guardado localmente', 'Se enviara automaticamente al reconectar.');
            jqXHR.abort();
        });
    }

    // ==========================================
    // INTERCEPCION AJAX: Error global
    // ==========================================
    function setupGlobalAjaxError() {
        $(document).ajaxError(function(event, xhr, settings) {
            // Ignorar requests abortados (por nosotros o el usuario)
            if (xhr.statusText === 'abort') return;

            // Ignorar URLs que sabemos pueden fallar sin ser offline
            if (isIgnoredUrl(settings.url)) return;

            // Status 0 = posible error de red. NO marcar offline directamente:
            // confirmar con un ping activo para evitar falsos positivos
            // (extensiones, peticiones canceladas por navegacion, blips momentaneos, etc.)
            if (xhr.status === 0 && xhr.readyState === 0) {
                if (state.online) {
                    confirmOffline();
                }
            }

            // 419 = CSRF token expirado (token viejo) o sesion caida.
            if (xhr.status === 419) {
                // Evitar bucle: solo un reintento automatico por request.
                if (settings && settings._csrfRetried) {
                    avisarReloginNecesario();
                    return;
                }
                refreshCsrfToken().then(function() {
                    // Token renovado. Un 419 se rechaza ANTES del controlador, asi que la
                    // operacion NO se ejecuto: reintentarla es seguro (sin doble-guardado).
                    if (settings && settings.url) {
                        settings._csrfRetried = true;
                        // El ajaxPrefilter refresca el header, pero Laravel prioriza el
                        // _token del body: hay que refrescarlo ahi tambien o el reintento
                        // volveria a dar 419.
                        reinyectarTokenEnBody(settings);
                        $.ajax(settings);
                    } else {
                        showToast('info', 'Sesion actualizada', 'Intente la operacion de nuevo.');
                    }
                }).fail(function() {
                    // No se pudo renovar -> la sesion realmente expiro.
                    avisarReloginNecesario();
                });
            }

            // 401 = sesion expirada
            if (xhr.status === 401) {
                avisarReloginNecesario();
            }
        });
    }

    // ==========================================
    // HELPERS AJAX
    // ==========================================
    function isIgnoredUrl(url) {
        if (!url) return false;
        for (var i = 0; i < CONFIG.IGNORED_URL_PATTERNS.length; i++) {
            if (url.indexOf(CONFIG.IGNORED_URL_PATTERNS[i]) !== -1) return true;
        }
        return false;
    }

    function injectCsrfToken(options) {
        var token = $('meta[name="csrf-token"]').attr('content');
        if (token) {
            if (!options.headers) options.headers = {};
            options.headers['X-CSRF-TOKEN'] = token;
        }
    }

    // ==========================================
    // LOCAL STORAGE: COLA DE REQUESTS
    // ==========================================
    function addToQueue(item) {
        var queue = getQueue();
        queue.push(item);
        saveToLS('queue', queue);
    }

    function getQueue() {
        return loadFromLS('queue') || [];
    }

    function clearQueue() {
        removeFromLS('queue');
    }

    // ==========================================
    // LOCAL STORAGE: DATOS DE MODULO
    // ==========================================
    function saveModuleData(module, key, data) {
        var lsKey = module + '_' + key;
        saveToLS(lsKey, data);
    }

    function loadModuleData(module, key) {
        var lsKey = module + '_' + key;
        return loadFromLS(lsKey);
    }

    function clearModuleData(module, key) {
        var lsKey = module + '_' + key;
        removeFromLS(lsKey);
    }

    // ==========================================
    // LOCAL STORAGE: PRIMITIVAS
    // ==========================================
    function saveToLS(key, data) {
        try {
            localStorage.setItem(CONFIG.LS_PREFIX + key, JSON.stringify(data));
        } catch (e) {
            // localStorage lleno o no disponible
            console.warn('SindenConexion: No se pudo guardar en localStorage', e);
        }
    }

    function loadFromLS(key) {
        try {
            var raw = localStorage.getItem(CONFIG.LS_PREFIX + key);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function removeFromLS(key) {
        try {
            localStorage.removeItem(CONFIG.LS_PREFIX + key);
        } catch (e) {}
    }

    function cleanExpiredData() {
        try {
            var now = Date.now();
            for (var i = localStorage.length - 1; i >= 0; i--) {
                var key = localStorage.key(i);
                if (key && key.indexOf(CONFIG.LS_PREFIX) === 0) {
                    var data = loadFromLS(key.replace(CONFIG.LS_PREFIX, ''));
                    if (data && data.timestamp && (now - data.timestamp) > CONFIG.MAX_QUEUE_AGE_MS) {
                        localStorage.removeItem(key);
                    }
                }
            }

            // Limpiar cola de items expirados
            var queue = getQueue();
            if (queue.length > 0) {
                var filtered = queue.filter(function(item) {
                    return (now - item.timestamp) < CONFIG.MAX_QUEUE_AGE_MS;
                });
                if (filtered.length !== queue.length) {
                    saveToLS('queue', filtered);
                }
            }
        } catch (e) {}
    }

    // ==========================================
    // SINCRONIZACION AL RECONECTAR
    // ==========================================
    function syncPendingData() {
        if (state.syncInProgress) return;

        var queue = getQueue();
        var hasOperarioData = hasModuleData('operario');
        var hasWizardData = hasModuleData('wizard');

        if (queue.length === 0 && !hasOperarioData && !hasWizardData) return;

        state.syncInProgress = true;

        var totalItems = queue.length + (hasOperarioData ? 1 : 0) + (hasWizardData ? 1 : 0);

        showToast('info', 'Sincronizando', 'Enviando ' + totalItems + ' operacion(es) pendiente(s)...');

        // Paso 1: Refrescar CSRF
        refreshCsrfToken()
            .then(function() {
                // Paso 2: Procesar cola FIFO
                return processQueue();
            })
            .then(function(results) {
                state.syncInProgress = false;

                var exitosos = results.filter(function(r) { return r.success; }).length;
                var fallidos = results.filter(function(r) { return !r.success; }).length;

                if (exitosos > 0 && fallidos === 0) {
                    showToast('success', 'Sincronizado', exitosos + ' operacion(es) enviada(s) correctamente.');
                } else if (exitosos > 0 && fallidos > 0) {
                    showToast('warning', 'Sincronizacion parcial',
                        exitosos + ' enviada(s), ' + fallidos + ' fallida(s). Se reintentaran.');
                } else if (fallidos > 0) {
                    showToast('error', 'Error de sincronizacion',
                        fallidos + ' operacion(es) no se pudieron enviar. Se reintentaran.');
                }
            })
            .catch(function() {
                state.syncInProgress = false;
                showToast('error', 'Error', 'No se pudo sincronizar. Se reintentara.');
            });
    }

    function processQueue() {
        var queue = getQueue();
        var results = [];

        if (queue.length === 0) {
            return $.Deferred().resolve(results).promise();
        }

        // Procesar secuencialmente (FIFO)
        var deferred = $.Deferred();
        var index = 0;

        function processNext() {
            if (index >= queue.length) {
                // Limpiar items exitosos de la cola
                var remaining = [];
                for (var i = 0; i < queue.length; i++) {
                    if (!results[i] || !results[i].success) {
                        remaining.push(queue[i]);
                    }
                }
                if (remaining.length > 0) {
                    saveToLS('queue', remaining);
                } else {
                    clearQueue();
                }
                deferred.resolve(results);
                return;
            }

            var item = queue[index];

            // Inyectar CSRF actualizado
            var data = item.data;
            if (typeof data === 'string') {
                try {
                    var parsed = JSON.parse(data);
                    parsed._token = $('meta[name="csrf-token"]').attr('content');
                    data = JSON.stringify(parsed);
                } catch (e) {
                    // Si no es JSON, agregar token como param
                    if (data.indexOf('_token=') !== -1) {
                        data = data.replace(/_token=[^&]*/, '_token=' + encodeURIComponent($('meta[name="csrf-token"]').attr('content')));
                    }
                }
            }

            $.ajax({
                url: item.url,
                method: item.method,
                data: data,
                contentType: item.contentType,
                global: false, // No disparar interceptores
                timeout: 10000,
                success: function() {
                    results.push({ success: true, index: index });
                    index++;
                    processNext();
                },
                error: function(xhr) {
                    results.push({ success: false, index: index, status: xhr.status });
                    index++;
                    processNext();
                }
            });
        }

        processNext();
        return deferred.promise();
    }

    // ==========================================
    // CSRF TOKEN REFRESH
    // ==========================================
    // Flag para no apilar varios modales de re-login simultaneos.
    var avisandoRelogin = false;

    function refreshCsrfToken() {
        return $.ajax({
            url: CONFIG.CSRF_REFRESH_URL,
            method: 'GET',
            global: false,
            timeout: 5000,
            dataType: 'json' // si la sesion murio, /api/csrf-refresh redirige al login (HTML) y esto rechaza
        }).then(function(response) {
            if (response && response.token) {
                var newToken = response.token;

                // Actualizar meta tag
                $('meta[name="csrf-token"]').attr('content', newToken);

                // Actualizar variables globales conocidas
                if (typeof window.CSRF_TOKEN !== 'undefined') {
                    window.CSRF_TOKEN = newToken;
                }
                if (window.WIZARD_CONFIG && window.WIZARD_CONFIG.csrfToken) {
                    window.WIZARD_CONFIG.csrfToken = newToken;
                }

                // Actualizar header global de jQuery
                $.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': newToken }
                });
                return true;
            }
            // No vino token -> tratar como sesion caida (rechaza la promesa)
            return $.Deferred().reject().promise();
        });
    }

    // Sesion realmente expirada: avisar claro y mandar al login (una sola vez).
    function avisarReloginNecesario() {
        if (avisandoRelogin) return;
        avisandoRelogin = true;
        Swal.fire({
            icon: 'warning',
            title: 'Tu sesion expiro',
            text: 'Por seguridad debes iniciar sesion de nuevo. Lo que ya se habia guardado se conservo.',
            confirmButtonText: 'Iniciar sesion',
            confirmButtonColor: '#4A7C59',
            allowOutsideClick: false
        }).then(function() {
            window.location.href = '/login';
        });
    }

    // Reescribe el _token dentro del CUERPO del request con el token fresco del meta.
    // Laravel resuelve el CSRF como $request->input('_token') ?: header('X-CSRF-TOKEN'):
    // el _token del body GANA sobre el header. Como muchos requests de la app mandan
    // _token en el body (operario, wizard, dibujo), refrescar solo el header no basta;
    // sin esto el reintento re-fallaria con 419. Mismo patron que processQueue.
    function reinyectarTokenEnBody(settings) {
        if (!settings || settings.data == null) return;
        var token = $('meta[name="csrf-token"]').attr('content');
        if (!token) return;
        var data = settings.data;
        try {
            if (typeof FormData !== 'undefined' && data instanceof FormData) {
                data.set('_token', token);
            } else if (typeof data === 'string') {
                var esJson = (settings.contentType && String(settings.contentType).indexOf('json') !== -1)
                             || /^\s*[\{\[]/.test(data);
                if (esJson) {
                    var obj = JSON.parse(data);
                    obj._token = token;
                    settings.data = JSON.stringify(obj);
                } else if (data.indexOf('_token=') !== -1) {
                    settings.data = data.replace(/_token=[^&]*/, '_token=' + encodeURIComponent(token));
                } else if (data.length) {
                    settings.data = data + '&_token=' + encodeURIComponent(token);
                } else {
                    settings.data = '_token=' + encodeURIComponent(token);
                }
            } else if (typeof data === 'object') {
                data._token = token;
            }
        } catch (e) { /* el header fresco queda como fallback */ }
    }

    // ==========================================
    // VERIFICACION DE DATOS DE RECUPERACION
    // ==========================================
    function hasModuleData(modulePrefix) {
        try {
            for (var i = 0; i < localStorage.length; i++) {
                var key = localStorage.key(i);
                if (key && key.indexOf(CONFIG.LS_PREFIX + modulePrefix + '_') === 0) {
                    return true;
                }
            }
        } catch (e) {}
        return false;
    }

    function checkRecoveryData() {
        // La recuperacion especifica de operario y wizard se maneja en sus propios JS
        // Aqui solo verificamos la cola generica
        var queue = getQueue();
        if (queue.length === 0) return;

        var oldestTime = queue[0].timestamp;
        var fecha = new Date(oldestTime).toLocaleString('es-CO', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });

        Swal.fire({
            title: 'Operaciones pendientes',
            html: 'Se encontraron <b>' + queue.length + '</b> operacion(es) no enviada(s) desde el <b>' + fecha + '</b>.<br>¿Desea enviarlas ahora?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Enviar ahora',
            cancelButtonText: 'Descartar',
            confirmButtonColor: '#4A7C59'
        }).then(function(result) {
            if (result.isConfirmed) {
                syncPendingData();
            } else {
                clearQueue();
            }
        });
    }

    // ==========================================
    // UTILIDADES
    // ==========================================
    function showToast(icon, title, text) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            text: text,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
    }

    // ==========================================
    // API PUBLICA
    // ==========================================
    return {
        init: init,
        isOnline: isOnline,
        saveModuleData: saveModuleData,
        loadModuleData: loadModuleData,
        clearModuleData: clearModuleData,
        syncNow: syncPendingData
    };

})(jQuery);

// Auto-inicializar al cargar DOM
$(function() {
    window.SindenConexion.init();
});
