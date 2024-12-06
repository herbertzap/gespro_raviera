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

// Rutas protegidas
Route::middleware(['auth'])->group(function () {
    // **Gestión de Usuarios**
    Route::middleware('permission:gestionar usuarios')->group(function () {
        Route::resource('user', UserController::class)->except('show');
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

});
