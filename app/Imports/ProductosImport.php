<?php

namespace App\Imports;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Producto;

use Carbon\Carbon;

class ProductosImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Producto([
            'NOKOPR' => $row['nombre_descripcion'],  // Ajusta los nombres de columna según el archivo Excel
            'FECRPR' => Carbon::now(),
            'estado' => 0 // Estado inicial "ingresado"
        ]);
    }
}
