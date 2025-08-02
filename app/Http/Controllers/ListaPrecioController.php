<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ListaPrecioController extends Controller
{
    public function index()
    {
        // Consulta SQL a la tabla TABPP en SQL Server
        $listasPrecios = DB::connection('sqlsrv')
            ->table('TABPP')
            ->select('KOLT', 'MOLT', 'TIMOLT', 'NOKOLT', 'ECUDEF01UD', 'ECUDEF02UD')
            ->orderBy('KOLT', 'ASC')
            ->get();

        return view('listasPrecios.index', compact('listasPrecios'));
    }

    public function productosPorLista($kolt)
    {
        // Consulta productos filtrados por lista de precios
        $productos = DB::connection('sqlsrv')->table('TABPRE')
            ->select(
                'KOLT', 'KOPR', 'KOPRRA', 'KOPRTE', 'ECUACION', 'RLUD', 
                'PP01UD', 'MG01UD', 'DTMA01UD', 'PP02UD', 'MG02UD', 'DTMA02UD', 'ECUACIONU2'
            )
            ->where('KOLT', $kolt)
            ->paginate(20);

        return view('productos.listaPrecios', compact('productos'));
    }
}
