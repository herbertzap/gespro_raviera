<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        // Realizar la consulta a la base de datos SQL Server
        $categorias_padre = DB::connection('sqlsrv')->select("
            select FM.KOFM, FM.NOKOFM from TABFM FM;
        ");

        $categorias_sc = DB::connection('sqlsrv')->select("
            SELECT PF.KOFM, FM.NOKOFM, PF.KOPF,PF.NOKOPF
            FROM TABPF  PF
            JOIN TABFM FM
            ON PF.KOFM = FM.KOFM;
        ");

        $categorias_sch = DB::connection('sqlsrv')->select("
            SELECT HF.KOFM, FM.NOKOFM, HF.KOPF,PF.NOKOPF, HF.KOHF, HF.NOKOHF FROM TABHF HF
            JOIN TABFM FM
            ON HF.KOFM = FM.KOFM
            JOIN TABPF PF
            ON HF.KOPF = PF.KOPF;
        ");

        // Pasar los datos a la vista
        return view('categorias.index', compact('categorias_padre', 'categorias_sc','categorias_sch'));
    }
}
