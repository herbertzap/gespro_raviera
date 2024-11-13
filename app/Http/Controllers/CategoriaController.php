<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the categories.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Realiza una consulta a la base de datos SQL Server
        // Suponiendo que tienes una conexión configurada para SQL Server
        $categorias = DB::connection('sqlsrv')->table('nombre_de_la_tabla_de_categorias')->get();

        // Retorna la vista y pasa los datos de categorías a la misma
        return view('categorias.index', compact('categorias'));
    }
}
