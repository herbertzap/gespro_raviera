<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    CategoriaController,
    BodegaController,
    ProductoController,
    ListaPrecioController,
    LogController,
    RoleController,
    PermissionController,
    UserController,
    ProfileController,
    PageController,
    Auth\LoginController
};

// Página de inicio
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Rutas de manejo de errores
Route::get('/error/{code?}', [App\Http\Controllers\ErrorController::class, 'show'])
    ->name('error.show')
    ->where('code', '[0-9]+');

// Dashboard
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard')->middleware(['auth']);

// Rutas de Cobranza
Route::middleware(['auth', 'sincronizar.clientes'])->group(function () {
    Route::get('/cobranza', [App\Http\Controllers\CobranzaController::class, 'index'])->name('cobranza.index');
    Route::get('/cobranza/export', [App\Http\Controllers\CobranzaController::class, 'export'])->name('cobranza.export');
});

// Rutas de búsqueda de productos (sin middleware de sincronización para mejor rendimiento)
Route::middleware(['auth'])->group(function () {
    Route::get('/cotizacion/buscar-productos', [App\Http\Controllers\CotizacionController::class, 'buscarProductos'])->name('cotizacion.buscar-productos');
    Route::get('/cotizacion/buscar-productos-mejorada', [App\Http\Controllers\CotizacionBusquedaMejoradaController::class, 'buscarProductos'])->name('cotizacion.buscar-productos-mejorada');
    Route::get('/cotizacion/obtener-precios', [App\Http\Controllers\CotizacionController::class, 'obtenerPrecios'])->name('cotizacion.obtener-precios');
    Route::post('/cotizacion/cheques-protestados', [App\Http\Controllers\CotizacionController::class, 'obtenerChequesProtestados'])->name('cotizacion.cheques-protestados');
    Route::post('/cotizacion/sincronizar-stock', [App\Http\Controllers\CotizacionController::class, 'sincronizarStock'])->name('cotizacion.sincronizar-stock-simple');
});

// Rutas de Cotizaciones
Route::middleware(['auth', 'sincronizar.clientes'])->group(function () {
    Route::get('/cotizaciones', [App\Http\Controllers\CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::get('/cotizacion/nueva', [App\Http\Controllers\CotizacionSimpleController::class, 'nueva'])->name('cotizacion.nueva');
    Route::get('/cotizacion/editar/{id}', [App\Http\Controllers\CotizacionSimpleController::class, 'editar'])->name('cotizacion.editar');
    Route::get('/cotizacion/ver/{id}', [App\Http\Controllers\CotizacionSimpleController::class, 'ver'])->name('cotizacion.ver');
    Route::get('/cotizacion/historial/{id}', [App\Http\Controllers\CotizacionController::class, 'historial'])->name('cotizacion.historial');
    Route::get('/cotizacion/pdf/{id}', [App\Http\Controllers\CotizacionSimpleController::class, 'generarPDF'])->name('cotizacion.pdf');
    Route::delete('/cotizacion/{id}', [App\Http\Controllers\CotizacionSimpleController::class, 'eliminar'])->name('cotizacion.eliminar');
    Route::post('/cotizacion/guardar', [App\Http\Controllers\CotizacionSimpleController::class, 'guardar'])->name('cotizacion.guardar');
    Route::put('/cotizacion/actualizar/{id}', [App\Http\Controllers\CotizacionSimpleController::class, 'actualizar'])->name('cotizacion.actualizar');
    Route::post('/cotizacion/generar-nota-venta/{id}', [App\Http\Controllers\CotizacionController::class, 'generarNotaVenta'])->name('cotizacion.generar-nota-venta');
    Route::post('/cotizacion/convertir-a-nota-venta/{id}', [App\Http\Controllers\CotizacionController::class, 'convertirANotaVenta'])->name('cotizacion.convertir-a-nota-venta');
    
    // Rutas de Stock Comprometido
    Route::post('/cotizacion/{id}/liberar-stock', [App\Http\Controllers\CotizacionController::class, 'liberarStockComprometido'])->name('cotizacion.liberar-stock');
    Route::get('/cotizacion/resumen-stock-comprometido', [App\Http\Controllers\CotizacionController::class, 'resumenStockComprometido'])->name('cotizacion.resumen-stock-comprometido');
    Route::post('/cotizacion/{id}/sincronizar-stock', [App\Http\Controllers\CotizacionController::class, 'sincronizarStock'])->name('cotizacion.sincronizar-stock');
});

// Rutas de Nota de Venta (NVV) - Páginas separadas
Route::middleware(['auth'])->group(function () {
    Route::get('/nota-venta/nueva', [App\Http\Controllers\NotaVentaController::class, 'nueva'])->name('nota-venta.nueva');
    Route::post('/nota-venta/guardar', [App\Http\Controllers\NotaVentaController::class, 'guardar'])->name('nota-venta.guardar');
    Route::get('/nota-venta/editar/{id}', [App\Http\Controllers\NotaVentaController::class, 'editar'])->name('nota-venta.editar');
    Route::put('/nota-venta/actualizar/{id}', [App\Http\Controllers\NotaVentaController::class, 'actualizar'])->name('nota-venta.actualizar');
    Route::get('/nota-venta/ver/{id}', [App\Http\Controllers\NotaVentaController::class, 'ver'])->name('nota-venta.ver');
    Route::delete('/nota-venta/{id}', [App\Http\Controllers\NotaVentaController::class, 'eliminar'])->name('nota-venta.eliminar');
    Route::post('/nota-venta/generar-nota-venta/{id}', [App\Http\Controllers\NotaVentaController::class, 'generarNotaVenta'])->name('nota-venta.generar-nota-venta');
    Route::post('/nota-venta/convertir-a-nota-venta/{id}', [App\Http\Controllers\NotaVentaController::class, 'convertirANotaVenta'])->name('nota-venta.convertir-a-nota-venta');
    
    // Rutas de Stock Comprometido para NVV
    Route::post('/nota-venta/{id}/liberar-stock', [App\Http\Controllers\NotaVentaController::class, 'liberarStockComprometido'])->name('nota-venta.liberar-stock');
    Route::get('/nota-venta/resumen-stock-comprometido', [App\Http\Controllers\NotaVentaController::class, 'resumenStockComprometido'])->name('nota-venta.resumen-stock-comprometido');
});

// Rutas de Productos (Compras)
Route::middleware(['auth'])->group(function () {
    Route::get('/productos', [App\Http\Controllers\ProductoController::class, 'index'])->name('productos.index');
    Route::get('/productos/ver/{codigo}', [App\Http\Controllers\ProductoController::class, 'ver'])->name('productos.ver');
    Route::get('/api/productos/buscar', [App\Http\Controllers\ProductoController::class, 'buscar'])->name('productos.buscar');
    Route::post('/api/productos/crear-nvv', [App\Http\Controllers\ProductoController::class, 'crearNVVDesdeProductos'])->name('productos.crear-nvv');
    Route::post('/api/productos/modificar-cantidades', [App\Http\Controllers\ProductoController::class, 'modificarCantidades'])->name('productos.modificar-cantidades');

    Route::get('/manejo-stock', [App\Http\Controllers\ManejoStockController::class, 'seleccionar'])->name('manejo-stock.select');
    Route::get('/manejo-stock/contabilidad', [App\Http\Controllers\ManejoStockController::class, 'contabilidad'])->name('manejo-stock.contabilidad');
    Route::get('/manejo-stock/historial', [App\Http\Controllers\ManejoStockController::class, 'historial'])->name('manejo-stock.historial');
    
    // Mantenedor (Solo Super Admin)
    Route::get('/mantenedor/bodegas', [App\Http\Controllers\MantenedorController::class, 'bodegas'])->name('mantenedor.bodegas');
    Route::post('/mantenedor/bodegas/crear', [App\Http\Controllers\MantenedorController::class, 'crearBodega'])->name('mantenedor.bodegas.crear');
    Route::put('/mantenedor/bodegas/{id}', [App\Http\Controllers\MantenedorController::class, 'actualizarBodega'])->name('mantenedor.bodegas.actualizar');
    Route::delete('/mantenedor/bodegas/{id}', [App\Http\Controllers\MantenedorController::class, 'eliminarBodega'])->name('mantenedor.bodegas.eliminar');
    Route::post('/mantenedor/ubicaciones/crear', [App\Http\Controllers\MantenedorController::class, 'crearUbicacion'])->name('mantenedor.ubicaciones.crear');
    Route::put('/mantenedor/ubicaciones/{id}', [App\Http\Controllers\MantenedorController::class, 'actualizarUbicacion'])->name('mantenedor.ubicaciones.actualizar');
    Route::delete('/mantenedor/ubicaciones/{id}', [App\Http\Controllers\MantenedorController::class, 'eliminarUbicacion'])->name('mantenedor.ubicaciones.eliminar');
    Route::post('/manejo-stock/contabilidad/guardar', [App\Http\Controllers\ManejoStockController::class, 'guardarCaptura'])->name('manejo-stock.contabilidad.guardar');
    Route::post('/manejo-stock/maeedo/insertar', [App\Http\Controllers\ManejoStockController::class, 'confirmarInsertMAEEDO'])->name('manejo-stock.maeedo.insertar');
    Route::get('/manejo-stock/asociar', [App\Http\Controllers\BarcodeLinkController::class, 'create'])->name('manejo-stock.asociar');
    Route::post('/manejo-stock/asociar', [App\Http\Controllers\BarcodeLinkController::class, 'store'])->name('manejo-stock.asociar.store');
    Route::get('/manejo-stock/api/barcode', [App\Http\Controllers\ManejoStockController::class, 'buscarCodigoBarras'])->name('manejo-stock.barcode');
    Route::get('/manejo-stock/api/producto', [App\Http\Controllers\ManejoStockController::class, 'producto'])->name('manejo-stock.producto');
    Route::get('/manejo-stock/api/ubicaciones', [App\Http\Controllers\ManejoStockController::class, 'buscarUbicaciones'])->name('manejo-stock.ubicaciones');
});

// Rutas de Aprobaciones
Route::middleware(['auth'])->group(function () {
    Route::get('/aprobaciones', [App\Http\Controllers\AprobacionController::class, 'index'])->name('aprobaciones.index');
    Route::get('/aprobaciones/{id}', [App\Http\Controllers\AprobacionController::class, 'show'])->name('aprobaciones.show');
    Route::get('/aprobaciones/{id}/historial', [App\Http\Controllers\AprobacionController::class, 'historial'])->name('aprobaciones.historial');
    Route::get('/aprobaciones/{id}/imprimir', [App\Http\Controllers\AprobacionController::class, 'imprimir'])->name('aprobaciones.imprimir');
    
    // Aprobaciones por rol
    Route::post('/aprobaciones/{id}/supervisor', [App\Http\Controllers\AprobacionController::class, 'aprobarSupervisor'])->name('aprobaciones.supervisor');
    Route::post('/aprobaciones/{id}/compras', [App\Http\Controllers\AprobacionController::class, 'aprobarCompras'])->name('aprobaciones.compras');
        Route::post('/aprobaciones/{id}/picking', [App\Http\Controllers\AprobacionController::class, 'aprobarPicking'])->name('aprobaciones.picking');
        Route::post('/aprobaciones/{id}/guardar-pendiente-entrega', [App\Http\Controllers\AprobacionController::class, 'guardarPendienteEntrega'])->name('aprobaciones.guardar-pendiente-entrega');
    
    // Rechazar y separar productos
    Route::post('/aprobaciones/{id}/rechazar', [App\Http\Controllers\AprobacionController::class, 'rechazar'])->name('aprobaciones.rechazar');
    Route::post('/aprobaciones/{id}/separar-stock', [App\Http\Controllers\AprobacionController::class, 'separarProductosStock'])->name('aprobaciones.separar-stock');
    Route::post('/aprobaciones/{id}/separar-por-stock', [App\Http\Controllers\AprobacionController::class, 'separarPorStock'])->name('aprobaciones.separar-por-stock');
    Route::post('/aprobaciones/{id}/guardar-separar', [App\Http\Controllers\AprobacionController::class, 'guardarSeparar'])->name('aprobaciones.guardar-separar');
    Route::post('/aprobaciones/{id}/separar-producto-individual', [App\Http\Controllers\AprobacionController::class, 'separarProductoIndividual'])->name('aprobaciones.separar-producto-individual');
    Route::post('/aprobaciones/{id}/separar-productos', [App\Http\Controllers\AprobacionController::class, 'separarProductos'])->name('aprobaciones.separar-productos');
    Route::post('/aprobaciones/{id}/modificar-cantidades', [App\Http\Controllers\AprobacionController::class, 'modificarCantidadesProductos'])->name('aprobaciones.modificar-cantidades');
    Route::post('/aprobaciones/{id}/modificar-descuentos', [App\Http\Controllers\AprobacionController::class, 'modificarDescuentosProductos'])->name('aprobaciones.modificar-descuentos');
    Route::post('/aprobaciones/{id}/sincronizar-stock', [App\Http\Controllers\AprobacionController::class, 'sincronizarStock'])->name('aprobaciones.sincronizar-stock');
});

// Ruta de prueba para historial
Route::get('/test-historial', function () {
    return view('test-historial');
});

// Ruta de prueba para historial simple
Route::get('/historial-simple/{id}', function ($id) {
    $cotizacion = \App\Models\Cotizacion::findOrFail($id);
    $historial = \App\Models\CotizacionHistorial::obtenerHistorialCompleto($id);
    return view('cotizaciones.historial-simple', compact('cotizacion', 'historial'));
});

// Rutas de Notificaciones
Route::middleware(['auth'])->group(function () {
    Route::get('/notificaciones/navbar', [App\Http\Controllers\NotificacionController::class, 'obtenerParaNavbar'])->name('notificaciones.navbar');
    Route::post('/notificaciones/{id}/marcar-leida', [App\Http\Controllers\NotificacionController::class, 'marcarComoLeida'])->name('notificaciones.marcar-leida');
    Route::post('/notificaciones/marcar-todas-leidas', [App\Http\Controllers\NotificacionController::class, 'marcarTodasComoLeidas'])->name('notificaciones.marcar-todas-leidas');
    Route::post('/notificaciones/{id}/archivar', [App\Http\Controllers\NotificacionController::class, 'archivar'])->name('notificaciones.archivar');
    Route::get('/notificaciones', [App\Http\Controllers\NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::get('/notificaciones/{id}', [App\Http\Controllers\NotificacionController::class, 'show'])->name('notificaciones.show');
    Route::get('/notificaciones/estadisticas', [App\Http\Controllers\NotificacionController::class, 'estadisticas'])->name('notificaciones.estadisticas');
});


// Rutas de NVV Pendientes
Route::middleware(['auth', 'sincronizar.clientes'])->group(function () {
    Route::get('/nvv-pendientes', [App\Http\Controllers\NvvPendientesController::class, 'index'])->name('nvv-pendientes.index');
Route::get('/nvv-pendientes/export', [App\Http\Controllers\NvvPendientesController::class, 'export'])->name('nvv-pendientes.export');
Route::get('/nvv-pendientes/resumen', [App\Http\Controllers\NvvPendientesController::class, 'resumen'])->name('nvv-pendientes.resumen');
Route::get('/nvv-pendientes/ver/{numeroNvv}', [App\Http\Controllers\NvvPendientesController::class, 'ver'])->name('nvv-pendientes.ver');
    
    // Rutas para detalle de NVV
    Route::get('/nvv-detalle/{numeroNvv}', [App\Http\Controllers\NvvDetalleController::class, 'show'])->name('nvv-detalle.show');
    Route::get('/nvv-detalle/{numeroNvv}/data', [App\Http\Controllers\NvvDetalleController::class, 'getNvvData'])->name('nvv-detalle.data');
});

// Rutas de Facturas Pendientes
Route::middleware(['auth', 'sincronizar.clientes'])->group(function () {
    Route::get('/facturas-pendientes', [App\Http\Controllers\FacturasPendientesController::class, 'index'])->name('facturas-pendientes.index');
    Route::get('/facturas-pendientes/export', [App\Http\Controllers\FacturasPendientesController::class, 'export'])->name('facturas-pendientes.export');
    Route::get('/facturas-pendientes/download', [App\Http\Controllers\FacturasPendientesController::class, 'download'])->name('facturas-pendientes.download');
    Route::get('/facturas-pendientes/resumen', [App\Http\Controllers\FacturasPendientesController::class, 'resumen'])->name('facturas-pendientes.resumen');
    Route::get('/facturas-pendientes/ver/{tipoDocumento}/{numeroDocumento}', [App\Http\Controllers\FacturasPendientesController::class, 'ver'])->name('facturas-pendientes.ver');
    
    // Ruta para obtener stock de producto específico
    Route::get('/cotizaciones/stock-producto/{codigo}', [App\Http\Controllers\CotizacionController::class, 'obtenerStockProducto'])->name('cotizaciones.stock-producto');
});

// Rutas de Facturas Emitidas
Route::middleware(['auth', 'sincronizar.clientes'])->group(function () {
    Route::get('/facturas-emitidas', [App\Http\Controllers\FacturasEmitidasController::class, 'index'])->name('facturas-emitidas.index');
    Route::get('/facturas-emitidas/export', [App\Http\Controllers\FacturasEmitidasController::class, 'export'])->name('facturas-emitidas.export');
    Route::get('/facturas-emitidas/download', [App\Http\Controllers\FacturasEmitidasController::class, 'download'])->name('facturas-emitidas.download');
});

// Rutas de Clientes
Route::middleware(['auth', 'sincronizar.clientes'])->group(function () {
    Route::get('/clientes', [App\Http\Controllers\ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/{codigo}', [App\Http\Controllers\ClienteController::class, 'show'])->name('clientes.show');
    Route::post('/clientes/buscar', [App\Http\Controllers\ClienteController::class, 'buscar'])->name('clientes.buscar');
    Route::get('/clientes/buscar', [App\Http\Controllers\ClienteController::class, 'buscarAjax'])->name('clientes.buscar.ajax');
    Route::post('/clientes/sincronizar', [App\Http\Controllers\ClienteController::class, 'sincronizar'])->name('clientes.sincronizar');
    Route::get('/clientes/estadisticas', [App\Http\Controllers\ClienteController::class, 'estadisticas'])->name('clientes.estadisticas');
});

// Rutas de Compras
Route::middleware(['auth', 'handle.errors'])->group(function () {
    Route::get('/compras', [App\Http\Controllers\ComprasController::class, 'index'])->name('compras.index');
    Route::get('/compras/productos-bajo-stock', [App\Http\Controllers\ComprasController::class, 'productosBajoStock'])->name('compras.productos-bajo-stock');
    Route::get('/compras/historial', [App\Http\Controllers\ComprasController::class, 'historial'])->name('compras.historial');
    Route::get('/compras/crear', [App\Http\Controllers\ComprasController::class, 'crear'])->name('compras.crear');
    Route::post('/compras', [App\Http\Controllers\ComprasController::class, 'store'])->name('compras.store');
    Route::get('/compras/{id}', [App\Http\Controllers\ComprasController::class, 'show'])->name('compras.show');
    Route::post('/compras/{id}/actualizar-estado', [App\Http\Controllers\ComprasController::class, 'actualizarEstado'])->name('compras.actualizar-estado');
});

// Rutas de Picking
Route::middleware(['auth', 'handle.errors'])->group(function () {
    Route::get('/picking', [App\Http\Controllers\PickingController::class, 'index'])->name('picking.index');
    Route::get('/picking/pendientes', [App\Http\Controllers\PickingController::class, 'pendientes'])->name('picking.pendientes');
    Route::get('/picking/en-preparacion', [App\Http\Controllers\PickingController::class, 'enPreparacion'])->name('picking.en-preparacion');
    Route::post('/picking/{numeroNvv}/iniciar', [App\Http\Controllers\PickingController::class, 'iniciarPreparacion'])->name('picking.iniciar');
    Route::get('/picking/{numeroNvv}/preparar', [App\Http\Controllers\PickingController::class, 'preparar'])->name('picking.preparar');
    Route::post('/picking/{numeroNvv}/completar', [App\Http\Controllers\PickingController::class, 'completarPreparacion'])->name('picking.completar');
    Route::get('/picking/historial', [App\Http\Controllers\PickingController::class, 'historial'])->name('picking.historial');
    Route::get('/picking/{numeroNvv}/imprimir', [App\Http\Controllers\PickingController::class, 'imprimirPicking'])->name('picking.imprimir');
});

// Ruta para página de cliente
Route::get('/cliente/{codigo}', [App\Http\Controllers\ClienteController::class, 'show'])->name('cliente.show');

// Autenticación
Auth::routes();

// Ruta alternativa para logout en caso de error CSRF
Route::get('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout.get');

// Página de inicio después del login
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware(['auth']);

// Rutas protegidas
Route::middleware(['auth', 'handle.errors'])->group(function () {
    // **Gestión de Usuarios**
    Route::middleware('permission:gestionar usuarios')->group(function () {
        Route::resource('user', UserController::class)->except('show');
        Route::resource('users', UserController::class)->except('show'); // Alias para compatibilidad
        Route::post('user/{user}/assign-role', [UserController::class, 'assignRole'])->name('user.assign-role');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // **Gestión de Roles**
    Route::middleware('permission:gestionar roles')->group(function () {
        Route::resource('roles', RoleController::class);
        Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('roles.assign-permissions');
    });

    // **Gestión de Permisos**
    Route::middleware('permission:gestionar permisos')->group(function () {
        Route::resource('permissions', PermissionController::class);
    });

    // **Gestión de Perfiles**
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // **Gestión de Categorías**
    Route::middleware('permission:gestionar categorías')->group(function () {
        Route::resource('categorias', CategoriaController::class);
    });

    // **Gestión de Bodegas**
    Route::middleware('permission:gestionar bodegas')->group(function () {
        Route::resource('bodegas', BodegaController::class);
    });

    // **Gestión de Productos**
    Route::middleware('permission:ver productos')->group(function () {
        Route::get('/productos/cargar', [ProductoController::class, 'cargarVista'])->name('productos.cargar')->middleware('permission:cargar productos');
        Route::post('/productos/cargar', [ProductoController::class, 'cargarExcel'])->name('productos.procesar')->middleware('permission:cargar productos');
        Route::get('/productos/validar', [ProductoController::class, 'validarVista'])->name('productos.validar')->middleware('permission:validar productos');
        Route::get('/productos/asignar', [ProductoController::class, 'asignarVista'])->name('productos.asignar')->middleware('permission:asignar productos');
        Route::get('/productos/editar/{sku}', [ProductoController::class, 'editarProducto'])->name('productos.editar')->middleware('permission:editar productos');
        Route::put('/productos/actualizar/{sku}', [ProductoController::class, 'actualizarProducto'])->name('productos.actualizar')->middleware('permission:editar productos');
        Route::get('/productos/publicados', [ProductoController::class, 'productosPublicados'])->name('productos.publicados')->middleware('permission:publicar productos');
        Route::get('/productos/lista-precios', [ProductoController::class, 'listaPrecios'])->name('productos.lista-precios')->middleware('permission:gestionar listas de precios');
        
        // Rutas de sincronización de productos
        Route::post('/productos/sincronizar', [ProductoController::class, 'sincronizar'])->name('productos.sincronizar');
        Route::get('/productos/estadisticas', [ProductoController::class, 'estadisticas'])->name('productos.estadisticas');
    });

    // **Gestión de Listas de Precios**
    Route::middleware('permission:gestionar listas de precios')->group(function () {
        Route::get('/listas-precios', [ListaPrecioController::class, 'index'])->name('listasPrecios.index');
        Route::get('/productos/lista-precios/{kolt}', [ListaPrecioController::class, 'productosPorLista'])->name('productos.listaPorPrecio');
    });

    // **Logs del Sistema**
    Route::middleware('permission:ver logs')->group(function () {
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    });

    // **Subcategorías**
    Route::get('/productos/subcategorias/{categoriaPadre}', [ProductoController::class, 'obtenerSubcategorias'])->name('productos.subcategorias');
    Route::get('/productos/subcategorias-hijo/{categoriaPadre}/{subCategoria}', [ProductoController::class, 'obtenerSubcategoriasHijo'])->name('productos.subcategorias-hijo');

    // **Edición de Precios y Bodegas**
    Route::middleware('permission:editar productos')->group(function () {
        Route::get('/productos/editar-precios/{codigo}', [ProductoController::class, 'editarPrecios'])->name('productos.editar-precios');
        Route::post('/productos/actualizar-precios', [ProductoController::class, 'actualizarPrecios'])->name('productos.actualizar-precios');
        Route::get('/productos/editar-bodegas/{codigo}', [ProductoController::class, 'editarBodegas'])->name('productos.editar-bodegas');
        Route::post('/productos/actualizar-bodegas', [ProductoController::class, 'actualizarBodegas'])->name('productos.actualizar-bodegas');
    });

    // **Operaciones Adicionales**
    Route::post('/bodegas/agregar', [ProductoController::class, 'agregarBodega'])->name('bodegas.agregar')->middleware('permission:gestionar bodegas');
    Route::post('/listas/agregar', [ProductoController::class, 'agregarLista'])->name('listas.agregar')->middleware('permission:gestionar listas de precios');
    Route::delete('/bodegas/{bodega}', [ProductoController::class, 'eliminarBodega'])->name('bodegas.eliminar')->middleware('permission:gestionar bodegas');
    Route::delete('/listas/{lista}', [ProductoController::class, 'eliminarLista'])
    ->name('listas.eliminar')
    ->middleware('permission:gestionar listas de precios');

    // **Gestión de Stock Local**
    Route::middleware('permission:view_stock')->group(function () {
        Route::get('/stock', [App\Http\Controllers\StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/{id}/edit', [App\Http\Controllers\StockController::class, 'edit'])->name('stock.edit');
        Route::put('/stock/{id}', [App\Http\Controllers\StockController::class, 'update'])->name('stock.update');
        Route::post('/stock/sincronizar', [App\Http\Controllers\StockController::class, 'sincronizar'])->name('stock.sincronizar');
        Route::get('/stock/sin-stock', [App\Http\Controllers\StockController::class, 'productosSinStock'])->name('stock.sin-stock');
        Route::get('/stock/bajo-stock', [App\Http\Controllers\StockController::class, 'productosBajoStock'])->name('stock.bajo-stock');
    });

});

// Rutas de Administración de Usuarios
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Gestión de usuarios
    Route::get('/users', [App\Http\Controllers\Admin\UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create-from-vendedor', [App\Http\Controllers\Admin\UserManagementController::class, 'vendedoresDisponibles'])->name('users.create-from-vendedor');
    Route::post('/users/create-from-vendedor', [App\Http\Controllers\Admin\UserManagementController::class, 'createFromVendedor'])->name('users.store-from-vendedor');
    Route::get('/users/{user}/edit', [App\Http\Controllers\Admin\UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [App\Http\Controllers\Admin\UserManagementController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/change-password', [App\Http\Controllers\Admin\UserManagementController::class, 'changePassword'])->name('users.change-password');
    Route::delete('/users/{user}', [App\Http\Controllers\Admin\UserManagementController::class, 'destroy'])->name('users.destroy');
    
    // Sincronización de vendedores
    Route::post('/vendedores/sincronizar', function() {
        try {
            \Artisan::call('vendedores:sincronizar');
            return response()->json(['success' => true, 'message' => 'Vendedores sincronizados exitosamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    })->name('vendedores.sincronizar');

    // Gestión de Múltiplos de Productos
    Route::get('/productos/multiplos', [App\Http\Controllers\Admin\ProductoMultiploController::class, 'index'])->name('productos.multiplos');
    Route::post('/productos/multiplos/cargar', [App\Http\Controllers\Admin\ProductoMultiploController::class, 'cargarExcel'])->name('productos.multiplos.cargar');
    Route::put('/productos/multiplos/{id}', [App\Http\Controllers\Admin\ProductoMultiploController::class, 'actualizar'])->name('productos.multiplos.actualizar');
    Route::post('/productos/multiplos/{id}/restablecer', [App\Http\Controllers\Admin\ProductoMultiploController::class, 'restablecer'])->name('productos.multiplos.restablecer');
});

// Webhook para despliegue automático desde GitHub
Route::post('/webhook/github', [App\Http\Controllers\WebhookController::class, 'github'])->name('webhook.github');
