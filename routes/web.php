<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\BodegaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ListaPrecioController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Auth::routes();

Route::get('/home', 'App\Http\Controllers\HomeController@index')->name('home')->middleware('auth');

Route::group(['middleware' => 'auth'], function () {
		Route::get('icons', ['as' => 'pages.icons', 'uses' => 'App\Http\Controllers\PageController@icons']);
		Route::get('maps', ['as' => 'pages.maps', 'uses' => 'App\Http\Controllers\PageController@maps']);
		Route::get('notifications', ['as' => 'pages.notifications', 'uses' => 'App\Http\Controllers\PageController@notifications']);
		Route::get('rtl', ['as' => 'pages.rtl', 'uses' => 'App\Http\Controllers\PageController@rtl']);
		Route::get('tables', ['as' => 'pages.tables', 'uses' => 'App\Http\Controllers\PageController@tables']);
		Route::get('typography', ['as' => 'pages.typography', 'uses' => 'App\Http\Controllers\PageController@typography']);
		Route::get('upgrade', ['as' => 'pages.upgrade', 'uses' => 'App\Http\Controllers\PageController@upgrade']);
});

Route::group(['middleware' => 'auth'], function () {
	Route::resource('user', 'App\Http\Controllers\UserController', ['except' => ['show']]);
	Route::get('profile', ['as' => 'profile.edit', 'uses' => 'App\Http\Controllers\ProfileController@edit']);
	Route::put('profile', ['as' => 'profile.update', 'uses' => 'App\Http\Controllers\ProfileController@update']);
	Route::put('profile/password', ['as' => 'profile.password', 'uses' => 'App\Http\Controllers\ProfileController@password']);
});

Route::resource('categorias', CategoriaController::class);
Route::resource('bodegas', BodegaController::class);
Route::prefix('productos')->group(function () {
    Route::get('/cargar', [ProductoController::class, 'cargarVista'])->name('productos.cargar');
    Route::post('/cargar', [ProductoController::class, 'cargarExcel']);
    Route::get('/validar', [ProductoController::class, 'validarVista'])->name('productos.validar');
    Route::get('/asignar', [ProductoController::class, 'asignarVista'])->name('productos.asignar');
    Route::get('/editar/{id}', [ProductoController::class, 'editarProducto'])->name('productos.editar');
    Route::put('/actualizar/{id}', [ProductoController::class, 'actualizarProducto'])->name('productos.actualizar');

});

Route::get('/productos/subcategorias/{categoriaPadre}', [ProductoController::class, 'obtenerSubcategorias']);
Route::get('/productos/subcategorias-hijo/{categoriaPadre}/{subCategoria}', [ProductoController::class, 'obtenerSubcategoriasHijo']);
Route::get('/productos/publicados', [ProductoController::class, 'productosPublicados'])->name('productos.publicados');
Route::get('/productos/lista-precios', [ProductoController::class, 'listaPrecios'])->name('productos.lista-precios');
Route::get('/listas-precios', [ListaPrecioController::class, 'index'])->name('listasPrecios.index');
Route::get('/productos/lista-precios/{kolt}', [ListaPrecioController::class, 'productosPorLista'])->name('productos.listaPorPrecio');

//editar valores y bodega de produto.
Route::get('productos/editar-precios/{codigo}', [ProductoController::class, 'editarPrecios'])->name('productos.editar-precios');
Route::get('productos/editar-bodegas/{codigo}', [ProductoController::class, 'editarBodegas'])->name('productos.editar-bodegas');
Route::post('productos/actualizar-precios', [ProductoController::class, 'actualizarPrecios'])->name('productos.actualizar-precios');
Route::post('productos/actualizar-bodegas', [ProductoController::class, 'actualizarBodegas'])->name('productos.actualizar-bodegas');










