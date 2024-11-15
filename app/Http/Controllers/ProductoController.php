<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ProductosImport;

class ProductoController extends Controller
{
    public function cargarVista()
    {
        return view('productos.cargar'); // Vista para cargar el archivo Excel
    }

    public function cargarExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls'
        ]);

        Excel::import(new ProductosImport, $request->file('archivo'));

        return redirect()->route('productos.cargar')->with('success', 'Productos cargados exitosamente.');
    }
}
