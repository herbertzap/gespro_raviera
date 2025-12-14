<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class NvvPendientesController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
        
        // Restringir acceso solo a Super Admin, Supervisor, Compras, Picking y Vendedor
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user->hasRole('Super Admin') && !$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Vendedor')) {
                abort(403, 'Acceso denegado. Solo Super Admin, Supervisor, Compras, Picking y Vendedor pueden acceder a esta vista.');
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Obtener parámetros de filtro
        $filtros = [
            'buscar' => $request->get('buscar', ''),
            'rango_dias' => $request->get('rango_dias', ''),
            'cliente' => $request->get('cliente', ''),
            'producto' => $request->get('producto', ''),
            'ordenar_por' => $request->get('ordenar_por', 'DIAS'),
            'orden' => $request->get('orden', 'desc'),
            'por_pagina' => $request->get('por_pagina', 20)
        ];

        // Obtener código de vendedor según el rol
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener datos de NVV pendientes
        $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($codigoVendedor, 1000);
        
        // Obtener lista de clientes únicos para el select (antes de filtrar)
        $clientes = $this->obtenerClientesUnicos($nvvPendientes);
        
        // Aplicar filtros
        $nvvFiltrados = $this->aplicarFiltrosNvv($nvvPendientes, $filtros);
        
        // Paginar los resultados
        $perPage = $filtros['por_pagina'];
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $nvvPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($nvvFiltrados, $offset, $perPage),
            count($nvvFiltrados),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        // Obtener resumen
        $resumen = $this->cobranzaService->getResumenNvvPendientes($codigoVendedor);

        return view('nvv-pendientes.index', [
            'nvvPendientes' => $nvvPaginated,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'clientes' => $clientes,
            'tipoUsuario' => $user->hasRole('Vendedor') ? 'Vendedor' : 'Administrador',
            'pageSlug' => 'nvv-pendientes'
        ]);
    }

    public function resumen(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        $resumen = $this->cobranzaService->getResumenNvvPendientes($codigoVendedor);

        return response()->json($resumen);
    }

    public function ver($numeroNvv)
    {
        $user = Auth::user();
        
        // Verificar permisos
        if (!$user->hasRole('Super Admin') && !$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Vendedor')) {
            abort(403, 'Acceso denegado.');
        }
        
        // Si es vendedor, verificar que la NVV pertenezca a su código
        if ($user->hasRole('Vendedor')) {
            $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($user->codigo_vendedor, 1000);
            $nvvEncontrada = false;
            
            foreach ($nvvPendientes as $nvv) {
                if ($nvv['NUM'] === $numeroNvv) {
                    $nvvEncontrada = true;
                    break;
                }
            }
            
            if (!$nvvEncontrada) {
                abort(403, 'No tienes permisos para ver esta NVV.');
            }
        }
        
        // Obtener detalles de la NVV
        $nvvDetalle = $this->cobranzaService->getDetalleNvv($numeroNvv);
        
        if (!$nvvDetalle) {
            abort(404, 'NVV no encontrada.');
        }
        
        // Buscar si hay una cotización en MySQL asociada a esta NVV
        $cotizacionAsociada = \App\Models\Cotizacion::where('numero_nvv', ltrim($numeroNvv, '0'))
            ->orWhere('numero_nvv', $numeroNvv)
            ->first();
        
        return view('nvv-pendientes.ver', [
            'nvv' => $nvvDetalle,
            'cotizacion' => $cotizacionAsociada,
            'pageSlug' => 'nvv-pendientes'
        ]);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener todos los datos sin paginación
        $nvvPendientes = $this->cobranzaService->getNvvPendientesDetalle($codigoVendedor, 10000);
        
        // Aplicar filtros si existen
        $filtros = [
            'buscar' => $request->get('buscar', ''),
            'rango_dias' => $request->get('rango_dias', ''),
            'cliente' => $request->get('cliente', ''),
            'producto' => $request->get('producto', ''),
            'ordenar_por' => $request->get('ordenar_por', 'DIAS'),
            'orden' => $request->get('orden', 'desc')
        ];
        
        $nvvFiltrados = $this->aplicarFiltrosNvv($nvvPendientes, $filtros);

        // Generar archivo Excel
        $filename = 'nvv_pendientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return $this->generarExcel($nvvFiltrados, $filename);
    }

    private function generarExcel($nvvPendientes, $filename)
    {
        // Crear archivo Excel usando PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título del reporte
        $sheet->setCellValue('A1', 'REPORTE DE NVV PENDIENTES');
        $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
        $sheet->setCellValue('A3', 'Total de registros: ' . count($nvvPendientes));
        
        // Estilo para el título
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2:A3')->getFont()->setSize(10);
        
        // Encabezados de la tabla
        $row = 5;
        $headers = [
            'A' . $row => 'Número NVV',
            'B' . $row => 'Cliente',
            'C' . $row => 'Productos',
            'D' . $row => 'Pendiente (Unidades)',
            'E' . $row => 'Valor Pendiente',
            'F' . $row => 'Días',
            'G' . $row => 'Rango'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Estilo para los encabezados
        $sheet->getStyle('A5:G5')->getFont()->setBold(true);
        $sheet->getStyle('A5:G5')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        
        // Datos de las NVV
        $row = 6;
        $totalPendiente = 0;
        $totalValorPendiente = 0;
        
        foreach ($nvvPendientes as $nvv) {
            $numeroNvv = ($nvv['TD'] ?? '') . '-' . ($nvv['NUM'] ?? '');
            $cliente = $nvv['CLIE'] ?? '';
            $cantidadProductos = $nvv['CANTIDAD_PRODUCTOS'] ?? 0;
            $totalPend = $nvv['TOTAL_PENDIENTE'] ?? 0;
            $valorPend = $nvv['TOTAL_VALOR_PENDIENTE'] ?? 0;
            $dias = $nvv['DIAS'] ?? 0;
            
            // Determinar rango
            $rango = '';
            if ($dias >= 1 && $dias <= 7) {
                $rango = '1-7 días';
            } elseif ($dias >= 8 && $dias <= 30) {
                $rango = '8-30 días';
            } elseif ($dias >= 31 && $dias <= 60) {
                $rango = '31-60 días';
            } elseif ($dias > 60) {
                $rango = 'Más de 60 días';
            }
            
            $sheet->setCellValue('A' . $row, $numeroNvv);
            $sheet->setCellValue('B' . $row, $cliente);
            $sheet->setCellValue('C' . $row, $cantidadProductos);
            $sheet->setCellValue('D' . $row, $totalPend);
            $sheet->setCellValue('E' . $row, $valorPend);
            $sheet->setCellValue('F' . $row, $dias);
            $sheet->setCellValue('G' . $row, $rango);
            
            // Formato numérico para valores
            $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0');
            
            $totalPendiente += $totalPend;
            $totalValorPendiente += $valorPend;
            
            $row++;
        }
        
        // Totales
        $row++;
        $sheet->setCellValue('C' . $row, 'TOTALES:');
        $sheet->setCellValue('D' . $row, $totalPendiente);
        $sheet->setCellValue('E' . $row, $totalValorPendiente);
        
        // Estilo para totales
        $sheet->getStyle('C' . $row . ':E' . $row)->getFont()->setBold(true);
        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
        
        // Autoajustar columnas
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Bordes para la tabla principal
        $lastDataRow = 5 + count($nvvPendientes) + 1; // +1 para la fila de totales
        $sheet->getStyle('A5:G' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Generar archivo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'nvv_pendientes_');
        $writer->save($tempFile);
        
        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    private function aplicarFiltrosNvv($nvvPendientes, $filtros)
    {
        // Filtrar por búsqueda general
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($buscar) {
                return strpos(strtolower($nvv['CLIE'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($nvv['NOKOPR'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($nvv['TD'] . '-' . $nvv['NUM']), $buscar) !== false;
            });
        }

        // Filtrar por rango de días
        if (!empty($filtros['rango_dias'])) {
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($filtros) {
                $dias = (int)($nvv['DIAS'] ?? 0);
                switch ($filtros['rango_dias']) {
                    case '1-7':
                        return $dias >= 1 && $dias <= 7;
                    case '8-30':
                        return $dias >= 8 && $dias <= 30;
                    case '31-60':
                        return $dias >= 31 && $dias <= 60;
                    case '60+':
                        return $dias > 60;
                    default:
                        return true;
                }
            });
        }

        // Filtrar por cliente (código exacto del select)
        if (!empty($filtros['cliente'])) {
            $clienteCodigo = strtolower($filtros['cliente']);
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($clienteCodigo) {
                $codigoFactura = strtolower($nvv['COD_CLI'] ?? '');
                return $codigoFactura === $clienteCodigo;
            });
        }

        // Filtrar por producto
        if (!empty($filtros['producto'])) {
            $producto = strtolower($filtros['producto']);
            $nvvPendientes = array_filter($nvvPendientes, function($nvv) use ($producto) {
                return strpos(strtolower($nvv['NOKOPR'] ?? ''), $producto) !== false;
            });
        }

        // Ordenar resultados
        $ordenarPor = $filtros['ordenar_por'];
        $orden = $filtros['orden'];
        
        usort($nvvPendientes, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor] ?? 0;
            $valorB = $b[$ordenarPor] ?? 0;
            
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $comparacion = $valorA <=> $valorB;
            } else {
                $comparacion = strcasecmp($valorA, $valorB);
            }
            
            return $orden === 'desc' ? -$comparacion : $comparacion;
        });

        return array_values($nvvPendientes);
    }

    /**
     * Obtener lista de clientes únicos para el select
     */
    private function obtenerClientesUnicos($nvvPendientes)
    {
        $clientes = [];
        $clientesMap = [];
        
        foreach ($nvvPendientes as $nvv) {
            $codigo = $nvv['COD_CLI'] ?? '';
            $nombre = $nvv['CLIE'] ?? '';
            
            if (!empty($codigo) && !isset($clientesMap[$codigo])) {
                $clientesMap[$codigo] = true;
                $clientes[] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre
                ];
            }
        }
        
        // Ordenar por nombre
        usort($clientes, function($a, $b) {
            return strcmp($a['nombre'], $b['nombre']);
        });
        
        return $clientes;
    }
}
