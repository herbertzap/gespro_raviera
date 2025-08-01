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
    PageController
};

// Página de inicio
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Dashboard
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard')->middleware('auth');

// Rutas de Cobranza
Route::get('/cobranza', [App\Http\Controllers\CobranzaController::class, 'index'])->name('cobranza.index')->middleware('auth');
Route::get('/cobranza/export', [App\Http\Controllers\CobranzaController::class, 'export'])->name('cobranza.export')->middleware('auth');

// Rutas de Cotizaciones
Route::middleware('auth')->group(function () {
    Route::get('/cotizaciones', [App\Http\Controllers\CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::get('/cotizacion/nueva', [App\Http\Controllers\CotizacionController::class, 'nueva'])->name('cotizacion.nueva');
    Route::get('/cotizacion/buscar-productos', [App\Http\Controllers\CotizacionController::class, 'buscarProductos'])->name('cotizacion.buscar-productos');
    Route::get('/cotizacion/obtener-precios', [App\Http\Controllers\CotizacionController::class, 'obtenerPrecios'])->name('cotizacion.obtener-precios');
    Route::post('/cotizacion/guardar', [App\Http\Controllers\CotizacionController::class, 'guardar'])->name('cotizacion.guardar');
    Route::post('/cotizacion/generar-nota-venta/{id}', [App\Http\Controllers\CotizacionController::class, 'generarNotaVenta'])->name('cotizacion.generar-nota-venta');
});

// Rutas de Clientes
Route::get('/clientes', [App\Http\Controllers\ClienteController::class, 'index'])->name('clientes.index')->middleware('auth');
Route::post('/clientes/buscar', [App\Http\Controllers\ClienteController::class, 'buscar'])->name('clientes.buscar')->middleware('auth');
Route::post('/clientes/validar', [App\Http\Controllers\ClienteController::class, 'validar'])->name('clientes.validar')->middleware('auth');
Route::get('/clientes/{codigoCliente}/facturas', [App\Http\Controllers\ClienteController::class, 'facturasPendientes'])->name('clientes.facturas')->middleware('auth');

// Autenticación
Auth::routes();

// Página de inicio después del login
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth');

// Rutas protegidas
Route::middleware(['auth'])->group(function () {
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
