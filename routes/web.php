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
    return view('welcome');
});

// Autenticación
Auth::routes();

// Página de inicio después del login
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home')->middleware('auth');

// Grupo de rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
    
    // Rutas genéricas del sistema
    Route::get('icons', [PageController::class, 'icons'])->name('pages.icons');
    Route::get('maps', [PageController::class, 'maps'])->name('pages.maps');
    Route::get('notifications', [PageController::class, 'notifications'])->name('pages.notifications');
    Route::get('rtl', [PageController::class, 'rtl'])->name('pages.rtl');
    Route::get('tables', [PageController::class, 'tables'])->name('pages.tables');
    Route::get('typography', [PageController::class, 'typography'])->name('pages.typography');
    Route::get('upgrade', [PageController::class, 'upgrade'])->name('pages.upgrade');
});

// **Gestión de Usuarios**
Route::middleware(['auth'])->group(function () {
    Route::resource('user', UserController::class);
    Route::resource('users', UserController::class)->except('show');
    Route::post('user/{user}/assign-role', [UserController::class, 'assignRole'])->name('user.assign-role');
});

// **Gestión de Roles**
Route::middleware(['auth'])->group(function () {
    Route::resource('roles', RoleController::class);
    Route::post('roles/{role}/assign-permissions', [RoleController::class, 'assignPermissions'])->name('roles.assign-permissions');
});

// **Gestión de Permisos**
Route::middleware(['auth'])->group(function () {
    Route::resource('permissions', PermissionController::class);
});

// **Gestión de Perfiles**
Route::middleware(['auth'])->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
});

// **Gestión de Categorías**
Route::middleware(['auth'])->group(function () {
    Route::resource('categorias', CategoriaController::class);
});

// **Gestión de Bodegas**
Route::middleware(['auth'])->group(function () {
    Route::resource('bodegas', BodegaController::class);
});

// **Gestión de Productos**
Route::middleware(['auth'])->group(function () {
    Route::get('/cargar', [ProductoController::class, 'cargarVista'])->name('productos.cargar');
    Route::post('/cargar', [ProductoController::class, 'cargarExcel'])->name('productos.procesar');
    Route::get('/validar', [ProductoController::class, 'validarVista'])->name('productos.validar');
    Route::get('/asignar', [ProductoController::class, 'asignarVista'])->name('productos.asignar');
    Route::get('/editar/{sku}', [ProductoController::class, 'editarProducto'])->name('productos.editar');
    Route::put('/actualizar/{sku}', [ProductoController::class, 'actualizarProducto'])->name('productos.actualizar');
    Route::get('/publicados', [ProductoController::class, 'productosPublicados'])->name('productos.publicados');
    Route::get('/lista-precios', [ProductoController::class, 'listaPrecios'])->name('productos.lista-precios');
});

// **Gestión de Listas de Precios**
Route::middleware(['auth'])->group(function () {
    Route::get('/listas-precios', [ListaPrecioController::class, 'index'])->name('listasPrecios.index');
    Route::get('/productos/lista-precios/{kolt}', [ListaPrecioController::class, 'productosPorLista'])->name('productos.listaPorPrecio');
});

// **Logs del Sistema**
Route::middleware(['auth'])->group(function () {
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
});

// **Subcategorías**
Route::get('/productos/subcategorias/{categoriaPadre}', [ProductoController::class, 'obtenerSubcategorias'])->name('productos.subcategorias');
Route::get('/productos/subcategorias-hijo/{categoriaPadre}/{subCategoria}', [ProductoController::class, 'obtenerSubcategoriasHijo'])->name('productos.subcategorias-hijo');

// **Edición de Precios y Bodegas**
Route::middleware(['auth'])->group(function () {
    Route::get('editar-precios/{codigo}', [ProductoController::class, 'editarPrecios'])->name('productos.editar-precios');
    Route::post('actualizar-precios', [ProductoController::class, 'actualizarPrecios'])->name('productos.actualizar-precios');
    Route::get('editar-bodegas/{codigo}', [ProductoController::class, 'editarBodegas'])->name('productos.editar-bodegas');
    Route::post('actualizar-bodegas', [ProductoController::class, 'actualizarBodegas'])->name('productos.actualizar-bodegas');
});

// **Operaciones Adicionales**
Route::post('/bodegas/agregar', [ProductoController::class, 'agregarBodega'])->name('bodegas.agregar');
Route::post('/listas/agregar', [ProductoController::class, 'agregarLista'])->name('listas.agregar');
Route::delete('/bodegas/{bodega}', [ProductoController::class, 'eliminarBodega'])->name('bodegas.eliminar');
Route::delete('/listas/{lista}', [ProductoController::class, 'eliminarLista'])->name('listas.eliminar');
