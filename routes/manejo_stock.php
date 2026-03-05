<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManejoStockController;

/*
| Rutas de manejo de stock (asociar código de barras, etc.)
| Se cargan en el grupo web. Si ya tienes estas rutas en web.php, no incluyas este archivo dos veces.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('manejo-stock/api/producto', [ManejoStockController::class, 'producto'])->name('manejo-stock.producto');
});
