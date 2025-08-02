<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class BodegaController extends Controller
{
    public function index()
    {
        // Realizar la consulta a la base de datos SQL Server
        $bodegas = DB::connection('sqlsrv')->select("
            SELECT EMPRESA, KOSU, KOBO, KOFUBO, NOKOBO, DIBO FROM TABBO  ORDER BY KOPA  DESC ;

        ");

        // Pasar los datos a la vista
        return view('bodegas.index', compact('bodegas'));
    }
}
