<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CatalogoItemController;
use App\Http\Controllers\BosquejoMatrizController;
use App\Http\Controllers\OrdenController;
use App\Http\Controllers\OperarioController;
use App\Http\Controllers\EntregaController;
use App\Http\Controllers\ContabilidadController;
use App\Http\Controllers\OrdenPdfController;
use App\Http\Controllers\GarantiaController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\TablaPreciosController;
use App\Http\Controllers\ConsultaPrecioController;
use App\Http\Controllers\Recepcion\PanelController as RecepcionPanelController;
use App\Http\Controllers\Admin\PanelController as AdminPanelController;
use App\Http\Controllers\ActividadController;
use App\Http\Controllers\Auth\RoleRedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Pagina principal
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

// Dashboard - redirige segun rol del usuario
Route::get('/dashboard', [RoleRedirectController::class, 'redirect'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// ==========================================
// API: Conexion Handler (ping + CSRF refresh)
// ==========================================
Route::middleware(['auth'])->group(function () {
    Route::get('/api/ping', fn() => response()->json(['pong' => true, 'time' => now()->timestamp]))->name('api.ping');
    Route::get('/api/csrf-refresh', fn() => response()->json(['token' => csrf_token()]))->name('api.csrf-refresh');
});

// ==========================================
// RUTAS DE PERFIL (autenticadas)
// ==========================================
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy')->middleware('role:Administrador');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
});

// ==========================================
// RUTAS DE CLIENTES (Admin/Recepcion/Contabilidad)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Recepcion|Contabilidad'])
    ->prefix('recepcion')->name('recepcion.')->group(function () {
        Route::get('/clientes/autocomplete', [ClienteController::class, 'autocomplete'])->name('clientes.autocomplete');
        Route::get('/clientes/export-excel', [ClienteController::class, 'exportExcel'])->name('clientes.export-excel');
        Route::get('/clientes/export-pdf', [ClienteController::class, 'exportPdf'])->name('clientes.export-pdf');
        Route::patch('/clientes/{cliente}/toggle-activo', [ClienteController::class, 'toggleActivo'])->name('clientes.toggle-activo')->middleware('role:Administrador');
        // Importacion masiva (rutas literales ANTES de la resource; solo Admin/Recepcion)
        Route::get('/clientes/import-template', [ClienteController::class, 'downloadTemplate'])->name('clientes.import-template')->middleware('role:Administrador|Recepcion');
        Route::post('/clientes/import-excel', [ClienteController::class, 'importExcel'])->name('clientes.import-excel')->middleware('role:Administrador|Recepcion');
        Route::get('/clientes/import-history', [ClienteController::class, 'importHistory'])->name('clientes.import-history')->middleware('role:Administrador|Recepcion');
        Route::get('/clientes/import-detail/{import}', [ClienteController::class, 'importDetail'])->name('clientes.import-detail')->middleware('role:Administrador|Recepcion');
        Route::resource('clientes', ClienteController::class);

        // Ordenes - Exportacion/PDF listado (Admin/Recepcion/Contabilidad). Rutas literales ANTES de {orden}.
        Route::get('/ordenes/export-pdf', [OrdenController::class, 'exportPdf'])->name('ordenes.export-pdf');
        Route::get('/ordenes/pdf-multiple', [OrdenPdfController::class, 'multiple'])->name('ordenes.pdf-multiple');
        Route::get('/ordenes/pdf-zip', [OrdenPdfController::class, 'zip'])->name('ordenes.pdf-zip');
    });

// ==========================================
// RUTAS DE RECEPCION
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Recepcion'])
    ->prefix('recepcion')->name('recepcion.')->group(function () {
        Route::get('/panel', RecepcionPanelController::class)->name('panel');

        // Catalogo Items
        Route::get('/items/autocomplete', [CatalogoItemController::class, 'autocomplete'])->name('items.autocomplete');
        Route::get('/items/export-excel', [CatalogoItemController::class, 'exportExcel'])->name('items.export-excel');
        Route::get('/items/export-pdf', [CatalogoItemController::class, 'exportPdf'])->name('items.export-pdf');
        Route::patch('/items/{item}/toggle-activo', [CatalogoItemController::class, 'toggleActivo'])->name('items.toggle-activo');
        Route::get('/items/import-template', [CatalogoItemController::class, 'downloadTemplate'])->name('items.import-template');
        Route::post('/items/import-excel', [CatalogoItemController::class, 'importExcel'])->name('items.import-excel');
        Route::get('/items/import-history', [CatalogoItemController::class, 'importHistory'])->name('items.import-history');
        Route::get('/items/import-detail/{import}', [CatalogoItemController::class, 'importDetail'])->name('items.import-detail');
        Route::resource('items', CatalogoItemController::class)->except(['show', 'destroy'])->parameters(['items' => 'item']);

        // Ordenes - Creacion (Wizard)
        Route::get('/ordenes/crear', [OrdenController::class, 'create'])->name('ordenes.crear');
        Route::post('/ordenes/guardar', [OrdenController::class, 'guardar'])->name('ordenes.guardar');
        Route::post('/ordenes/generar', [OrdenController::class, 'generar'])->name('ordenes.generar');
        Route::post('/ordenes/subir-bosquejo', [OrdenController::class, 'subirBosquejo'])->name('ordenes.subir-bosquejo');
        Route::post('/ordenes/crear-cliente-inline', [OrdenController::class, 'crearClienteInline'])->name('ordenes.crear-cliente-inline');
        Route::get('/ordenes/operarios', [OrdenController::class, 'listarOperarios'])->name('ordenes.operarios');
        Route::get('/ordenes/grupos-bosquejos', [OrdenController::class, 'listarGruposBosquejos'])->name('ordenes.grupos-bosquejos');

        // Ordenes - Gestion / escritura (rutas con parametro {orden})
        Route::get('/ordenes/{orden}/editar', [OrdenController::class, 'edit'])->name('ordenes.edit')->middleware('role:Administrador|Recepcion|Contabilidad');
        Route::put('/ordenes/{orden}', [OrdenController::class, 'update'])->name('ordenes.update')->middleware('role:Administrador|Recepcion|Contabilidad');
        Route::post('/ordenes/{orden}/copiar', [OrdenController::class, 'copiar'])->name('ordenes.copiar');
        Route::post('/ordenes/{orden}/anular', [OrdenController::class, 'anular'])->name('ordenes.anular');
        Route::delete('/ordenes/{orden}', [OrdenController::class, 'destroy'])->name('ordenes.destroy');
        Route::post('/ordenes/{orden}/comentarios', [OrdenController::class, 'agregarComentario'])->name('ordenes.comentarios.store');
        Route::post('/ordenes/{orden}/pagos', [OrdenController::class, 'agregarPago'])->name('ordenes.pagos.store');

        // Ordenes - Documentos adjuntos
        Route::post('/ordenes/{orden}/documentos', [OrdenController::class, 'subirDocumento'])->name('ordenes.documentos.subir');
        Route::delete('/ordenes/{orden}/documentos/{documento}', [OrdenController::class, 'eliminarDocumento'])->name('ordenes.documentos.eliminar');

        // Actividades
        Route::get('/actividades/export-excel', [ActividadController::class, 'exportPersonalExcel'])->name('actividades.export-excel');
        Route::get('/actividades-globales/export-excel', [ActividadController::class, 'exportGlobalExcel'])->name('actividades-globales.export-excel');
        Route::get('/actividades', [ActividadController::class, 'personal'])->name('actividades');
        Route::get('/actividades-globales', [ActividadController::class, 'global'])->name('actividades-globales');

    });

// ==========================================
// RUTAS DE ORDENES SOLO-LECTURA (Admin/Recepcion/Contabilidad/Operario)
// Contabilidad y Operario solo pueden ver el listado, ver el detalle y descargar el PDF.
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Recepcion|Contabilidad|Operario'])
    ->prefix('recepcion')->name('recepcion.')->group(function () {
        // Exportacion Excel disponible para todos los roles (ruta literal ANTES de {orden})
        Route::get('/ordenes/export-excel', [OrdenController::class, 'exportExcel'])->name('ordenes.export-excel');
        Route::get('/ordenes', [OrdenController::class, 'index'])->name('ordenes.index');
        Route::get('/ordenes/{orden}/pdf', [OrdenPdfController::class, 'show'])->name('ordenes.pdf');
        Route::get('/ordenes/{orden}/documentos/{documento}/descargar', [OrdenController::class, 'descargarDocumento'])->name('ordenes.documentos.descargar');
        Route::get('/ordenes/{orden}', [OrdenController::class, 'show'])->name('ordenes.show');
    });

// ==========================================
// RUTAS DE ENTREGAS (todos los roles)
// ==========================================
Route::middleware(['auth', 'verified'])
    ->prefix('recepcion')->name('recepcion.')->group(function () {
        // Entregas Pendientes - Exportacion (rutas literales ANTES de {orden})
        Route::get('/entregas-pendientes/export-excel', [EntregaController::class, 'exportPendientesExcel'])->name('entregas-pendientes.export-excel');
        Route::get('/entregas-historial/export-excel', [EntregaController::class, 'exportHistorialExcel'])->name('entregas-historial.export-excel');

        // Entregas Pendientes
        Route::get('/entregas-pendientes', [EntregaController::class, 'pendientes'])->name('entregas-pendientes');
        Route::get('/entregas-pendientes/{orden}/flujo', [EntregaController::class, 'flujo'])->name('entregas.flujo');
        Route::post('/entregas-pendientes/{orden}/entregar', [EntregaController::class, 'entregarPiezas'])->name('entregas.entregar');
        Route::post('/entregas-pendientes/{orden}/entrega-rapida', [EntregaController::class, 'entregaRapida'])->name('entregas.entrega-rapida');
        Route::post('/entregas-pendientes/{orden}/foto-entrega', [EntregaController::class, 'subirFotoEntrega'])->name('entregas.foto-entrega');
        Route::delete('/entregas-pendientes/{orden}/foto-entrega/{foto}', [EntregaController::class, 'eliminarFotoEntrega'])->name('entregas.foto-entrega.eliminar');

        // Historial de Entregas
        Route::get('/entregas-historial', [EntregaController::class, 'historial'])->name('entregas.historial');

        // Historial de entregas por pieza (AJAX)
        Route::get('/entregas-pendientes/pieza/{pieza}/historial', [EntregaController::class, 'historialPieza'])->name('entregas.historial-pieza');
    });

// ==========================================
// RUTAS DE GARANTIAS (Admin/Recepcion)
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Recepcion'])
    ->prefix('recepcion')->name('recepcion.')->group(function () {
        Route::get('/garantias/export-excel', [GarantiaController::class, 'exportExcel'])->name('garantias.export-excel');
        Route::get('/garantias', [GarantiaController::class, 'index'])->name('garantias.index');
        Route::get('/ordenes/{orden}/piezas-entregadas', [GarantiaController::class, 'piezasEntregadas'])->name('garantias.piezas-entregadas');
        Route::post('/ordenes/{orden}/garantias', [GarantiaController::class, 'store'])->name('garantias.store');
        Route::post('/garantias/{garantia}/estado', [GarantiaController::class, 'cambiarEstado'])->name('garantias.cambiar-estado');
        Route::post('/garantias/{garantia}/asignar-operario', [GarantiaController::class, 'asignarOperario'])->name('garantias.asignar-operario');

        // Consulta de Precios
        Route::get('/consulta-precios', [ConsultaPrecioController::class, 'index'])->name('consulta-precios.index');
        Route::post('/consulta-precios/buscar', [ConsultaPrecioController::class, 'consultar'])->name('consulta-precios.buscar');
    });

// ==========================================
// RUTAS DE BOSQUEJOS MATRIZ (todos los roles)
// ==========================================
Route::middleware(['auth', 'verified'])
    ->prefix('recepcion')->name('recepcion.')->group(function () {
        // Lectura: cualquier usuario con permiso ver_bosquejos_matriz
        Route::middleware('permission:ver_bosquejos_matriz')->group(function () {
            Route::get('/bosquejos-matriz', [BosquejoMatrizController::class, 'index'])->name('bosquejos-matriz.index');
            Route::get('/bosquejos-matriz/bosquejos/{bosquejo}/descargar', [BosquejoMatrizController::class, 'downloadBosquejo'])->name('bosquejos-matriz.bosquejos.descargar');
        });
        // Escritura: solo Administrador
        Route::middleware('role:Administrador')->group(function () {
            Route::post('/bosquejos-matriz/grupos', [BosquejoMatrizController::class, 'storeGrupo'])->name('bosquejos-matriz.grupos.store');
            Route::put('/bosquejos-matriz/grupos/{grupo}', [BosquejoMatrizController::class, 'updateGrupo'])->name('bosquejos-matriz.grupos.update');
            Route::delete('/bosquejos-matriz/grupos/{grupo}', [BosquejoMatrizController::class, 'destroyGrupo'])->name('bosquejos-matriz.grupos.destroy');
            Route::post('/bosquejos-matriz/bosquejos', [BosquejoMatrizController::class, 'storeBosquejo'])->name('bosquejos-matriz.bosquejos.store');
            Route::put('/bosquejos-matriz/bosquejos/{bosquejo}', [BosquejoMatrizController::class, 'updateBosquejo'])->name('bosquejos-matriz.bosquejos.update');
            Route::delete('/bosquejos-matriz/bosquejos/{bosquejo}', [BosquejoMatrizController::class, 'destroyBosquejo'])->name('bosquejos-matriz.bosquejos.destroy');
            Route::post('/bosquejos-matriz/nombre-genericos', [BosquejoMatrizController::class, 'updateNombreGenericos'])->name('bosquejos-matriz.nombre-genericos');
        });
    });

// ==========================================
// RUTAS DE OPERARIO
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Operario'])
    ->prefix('operario')->name('operario.')->group(function () {
        // Dashboard
        Route::get('/panel', [OperarioController::class, 'panel'])->name('panel');

        // Ordenes asignadas
        Route::get('/ordenes-asignadas/export-excel', [OperarioController::class, 'exportOrdenesAsignadasExcel'])->name('ordenes-asignadas.export-excel');
        Route::get('/ordenes-asignadas', [OperarioController::class, 'ordenesAsignadas'])->name('ordenes-asignadas');

        // Vista de trabajo
        Route::get('/ordenes/{orden}', [OperarioController::class, 'trabajar'])->name('ordenes.trabajar');

        // Buscar orden
        Route::get('/buscar', [OperarioController::class, 'buscar'])->name('buscar');
        Route::get('/buscar-orden', [OperarioController::class, 'buscarOrden'])->name('buscar-orden');

        // Complementar
        Route::get('/complementar/export-excel', [OperarioController::class, 'exportComplementarExcel'])->name('complementar.export-excel');
        Route::get('/complementar', [OperarioController::class, 'complementar'])->name('complementar');

        // Garantias asignadas
        Route::get('/garantias/export-excel', [GarantiaController::class, 'exportMisGarantiasExcel'])->name('garantias.export-excel');
        Route::get('/garantias', [GarantiaController::class, 'misGarantias'])->name('garantias');
        Route::post('/garantias/{garantia}/completar', [GarantiaController::class, 'completarTrabajo'])->name('garantias.completar');

        // AJAX: Trabajo con piezas
        Route::post('/ordenes/{orden}/actualizar-avances', [OperarioController::class, 'actualizarAvances'])->name('ordenes.actualizar-avances');
        Route::post('/piezas/{pieza}/transferir', [OperarioController::class, 'transferirPieza'])->name('piezas.transferir');
        Route::post('/ordenes/{orden}/transferir-masivo', [OperarioController::class, 'transferirMasivo'])->name('ordenes.transferir-masivo');
        Route::post('/piezas/{pieza}/dejar-cola', [OperarioController::class, 'dejarEnCola'])->name('piezas.dejar-cola');
        Route::post('/piezas/{pieza}/tomar', [OperarioController::class, 'tomarPieza'])->name('piezas.tomar');
        Route::post('/piezas/{pieza}/foto', [OperarioController::class, 'subirFoto'])->name('piezas.foto');
        Route::post('/piezas/{pieza}/observacion', [OperarioController::class, 'guardarObservacion'])->name('piezas.observacion');

        // AJAX: Bloqueo
        Route::post('/ordenes/{orden}/bloquear', [OperarioController::class, 'bloquear'])->name('ordenes.bloquear');
        Route::post('/ordenes/{orden}/heartbeat', [OperarioController::class, 'heartbeat'])->name('ordenes.heartbeat');
        Route::post('/ordenes/{orden}/desbloquear', [OperarioController::class, 'desbloquear'])->name('ordenes.desbloquear');
        Route::get('/ordenes/{orden}/estado-bloqueo', [OperarioController::class, 'estadoBloqueo'])->name('ordenes.estado-bloqueo');

        // AJAX: Operarios disponibles
        Route::get('/operarios-disponibles', [OperarioController::class, 'operariosDisponibles'])->name('operarios-disponibles');

        // Actividades
        Route::get('/actividades/export-excel', [ActividadController::class, 'exportPersonalExcel'])->name('actividades.export-excel');
        Route::get('/actividades', [ActividadController::class, 'personal'])->name('actividades');
    });

// ==========================================
// RUTAS DE CONTABILIDAD
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador|Contabilidad'])
    ->prefix('contabilidad')->name('contabilidad.')->group(function () {
        // Panel
        Route::get('/panel', [ContabilidadController::class, 'panel'])->name('panel');

        // Ordenes con saldo pendiente
        Route::get('/ordenes-pendientes/export-excel', [ContabilidadController::class, 'ordenesPendientesExportExcel'])->name('ordenes-pendientes.export-excel');
        Route::get('/ordenes-pendientes', [ContabilidadController::class, 'ordenesPendientes'])->name('ordenes-pendientes');

        // Historial financiero (todas las ordenes)
        Route::get('/historial-financiero', [ContabilidadController::class, 'historialFinanciero'])->name('historial-financiero');
        Route::get('/historial-financiero/export', [ContabilidadController::class, 'historialFinancieroExport'])->name('historial-financiero.export');

        // Ver orden (solo lectura, reutiliza vista de recepcion)
        Route::get('/ordenes/{orden}', [OrdenController::class, 'show'])->name('ordenes.show');

        // Pagos de una orden (JSON para modal)
        Route::get('/ordenes/{orden}/pagos', [ContabilidadController::class, 'pagosOrden'])->name('ordenes.pagos.index');

        // Agregar pago a orden (auto-aprobado)
        Route::post('/ordenes/{orden}/pagos', [ContabilidadController::class, 'agregarPago'])->name('ordenes.pagos.store');

        // Pagos pendientes de aprobacion
        Route::get('/pagos-pendientes/export-excel', [ContabilidadController::class, 'pagosPendientesExportExcel'])->name('pagos-pendientes.export-excel');
        Route::get('/pagos-pendientes', [ContabilidadController::class, 'pagosPendientes'])->name('pagos-pendientes');

        // Aprobar pagos masivo (ANTES de ruta con parametro)
        Route::post('/pagos/aprobar-masivo', [ContabilidadController::class, 'aprobarPagosMasivo'])->name('pagos.aprobar-masivo');

        // Aprobar pago individual
        Route::post('/pagos/{pago}/aprobar', [ContabilidadController::class, 'aprobarPago'])->name('pagos.aprobar');

        // Rechazar pago pendiente
        Route::delete('/pagos/{pago}/rechazar', [ContabilidadController::class, 'rechazarPago'])->name('pagos.rechazar');

        // Reporte de ventas por items
        Route::get('/reporte-items', [ContabilidadController::class, 'reporteItems'])->name('reporte-items');
        Route::get('/reporte-items/export', [ContabilidadController::class, 'reporteItemsExport'])->name('reporte-items.export');

        // Catalogo Items (solo lectura)
        Route::get('/items/export-excel', [CatalogoItemController::class, 'exportExcel'])->name('items.export-excel');
        Route::get('/items', [CatalogoItemController::class, 'index'])->name('items.index');

        // Actividades
        Route::get('/actividades/export-excel', [ActividadController::class, 'exportPersonalExcel'])->name('actividades.export-excel');
        Route::get('/actividades', [ActividadController::class, 'personal'])->name('actividades');
    });

// ==========================================
// RUTAS DE ADMINISTRACION
// ==========================================
Route::middleware(['auth', 'verified', 'role:Administrador'])->prefix('admin')->name('admin.')->group(function () {
    // Panel de Administracion
    Route::get('/panel', AdminPanelController::class)->name('panel');

    // Gestion de Usuarios
    Route::get('usuarios/export-excel', [AdminUserController::class, 'exportExcel'])->name('usuarios.export-excel');
    Route::patch('usuarios/{user}/toggle-activo', [AdminUserController::class, 'toggleActivo'])->name('usuarios.toggle-activo');
    Route::resource('usuarios', AdminUserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except(['destroy']);

    // Configuracion del Sistema
    Route::get('/configuracion', [ConfiguracionController::class, 'index'])->name('configuracion');
    Route::post('/configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
    Route::post('/configuracion/logo', [ConfiguracionController::class, 'uploadLogo'])->name('configuracion.upload-logo');
    Route::delete('/configuracion/logo', [ConfiguracionController::class, 'deleteLogo'])->name('configuracion.delete-logo');
    Route::post('/configuracion/fondo', [ConfiguracionController::class, 'uploadFondo'])->name('configuracion.upload-fondo');
    Route::delete('/configuracion/fondo', [ConfiguracionController::class, 'deleteFondo'])->name('configuracion.delete-fondo');

    // Tipos de Pago (CRUD)
    Route::post('/configuracion/tipos-pago', [ConfiguracionController::class, 'storeTipoPago'])->name('configuracion.tipos-pago.store');
    Route::put('/configuracion/tipos-pago/{tipo}', [ConfiguracionController::class, 'updateTipoPago'])->name('configuracion.tipos-pago.update');
    Route::delete('/configuracion/tipos-pago/{tipo}', [ConfiguracionController::class, 'destroyTipoPago'])->name('configuracion.tipos-pago.destroy');
    Route::post('/configuracion/tipos-pago/{tipo}/restore', [ConfiguracionController::class, 'restoreTipoPago'])->name('configuracion.tipos-pago.restore');
    Route::delete('/configuracion/tipos-pago/{id}/eliminar', [ConfiguracionController::class, 'eliminarTipoPago'])->name('configuracion.tipos-pago.eliminar');

    // Tabla de Precios
    Route::get('/tabla-precios', [TablaPreciosController::class, 'index'])->name('tabla-precios.index');
    Route::post('/tabla-precios/actualizar', [TablaPreciosController::class, 'updatePrecios'])->name('tabla-precios.update');
    Route::get('/tabla-precios/servicios', [TablaPreciosController::class, 'servicios'])->name('tabla-precios.servicios');
    Route::post('/tabla-precios/servicios', [TablaPreciosController::class, 'storeServicio'])->name('tabla-precios.servicios.store');
    Route::put('/tabla-precios/servicios/{tipo_servicio}', [TablaPreciosController::class, 'updateServicio'])->name('tabla-precios.servicios.update');
    Route::delete('/tabla-precios/servicios/{tipo_servicio}', [TablaPreciosController::class, 'destroyServicio'])->name('tabla-precios.servicios.destroy');
    Route::get('/tabla-precios/export-excel', [TablaPreciosController::class, 'exportExcel'])->name('tabla-precios.export');
    Route::get('/tabla-precios/plantilla-excel', [TablaPreciosController::class, 'plantillaExcel'])->name('tabla-precios.plantilla');
    Route::post('/tabla-precios/import-excel', [TablaPreciosController::class, 'importExcel'])->name('tabla-precios.import');

    // Actividades
    Route::get('/actividades/export-excel', [ActividadController::class, 'exportPersonalExcel'])->name('actividades.export-excel');
    Route::get('/actividades-globales/export-excel', [ActividadController::class, 'exportGlobalExcel'])->name('actividades-globales.export-excel');
    Route::get('/actividades', [ActividadController::class, 'personal'])->name('actividades');
    Route::get('/actividades-globales', [ActividadController::class, 'global'])->name('actividades-globales');
});

// ==========================================
// NOTIFICACIONES (todos los usuarios autenticados)
// ==========================================
Route::middleware(['auth'])->group(function () {
    Route::get('/notificaciones', [\App\Http\Controllers\NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::delete('/notificaciones/eliminar-todas', [\App\Http\Controllers\NotificacionController::class, 'eliminarTodas'])->name('notificaciones.eliminar-todas');
    Route::delete('/notificaciones/{id}', [\App\Http\Controllers\NotificacionController::class, 'destroy'])->name('notificaciones.destroy');
    Route::post('/notificaciones/marcar-leidas', [\App\Http\Controllers\NotificacionController::class, 'marcarLeidas'])->name('notificaciones.marcar-leidas');
});

require __DIR__.'/auth.php';
