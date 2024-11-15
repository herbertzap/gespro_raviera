<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CategoriaController extends Controller
{
    public function index()
    {
        // Realizar la consulta a la base de datos SQL Server
        $categorias = DB::connection('sqlsrv')->select("
            select FM.KOFM, FM.NOKOFM, PF.KOPF, PF.NOKOPF, HF.KOHF, HF.NOKOHF from TABFM FM 
            inner join TABPF PF on FM.KOFM = PF.KOFM  
            INNER JOIN TABHF HF  on PF.KOFM = HF.KOFM  
            and PF.KOPF = HF.KOPF;
        ");

        // Pasar los datos a la vista
        return view('categorias.index', compact('categorias'));
    }
}
