<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class FacturasPendientesController extends Controller
{
    protected $cobranzaService;

    public function __construct(CobranzaService $cobranzaService)
    {
        $this->cobranzaService = $cobranzaService;
        
        // Restringir acceso por permisos
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->can('ver_facturas_pendientes')) {
                abort(403, 'No tienes permisos para acceder a esta vista.');
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
            'estado' => $request->get('estado', ''),
            'tipo_documento' => $request->get('tipo_documento', ''),
            'cliente' => $request->get('cliente', ''),
            'vendedor' => $request->get('vendedor', ''),
            'saldo_min' => $request->get('saldo_min', ''),
            'saldo_max' => $request->get('saldo_max', ''),
            'valor_min' => $request->get('valor_min', ''),
            'valor_max' => $request->get('valor_max', ''),
            'abonos_min' => $request->get('abonos_min', ''),
            'abonos_max' => $request->get('abonos_max', ''),
            'dias_min' => $request->get('dias_min', ''),
            'dias_max' => $request->get('dias_max', ''),
            'fecha_desde' => $request->get('fecha_desde', ''),
            'fecha_hasta' => $request->get('fecha_hasta', ''),
            'vendedores' => $request->get('vendedores', []), // Para supervisor
            'ordenar_por' => $request->get('ordenar_por', 'NRO_DOCTO'),
            'orden' => $request->get('orden', 'desc'),
            'por_pagina' => $request->get('por_pagina', 10)
        ];

        // Obtener código de vendedor según el rol
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener datos de facturas pendientes con paginación
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes($codigoVendedor, 1000);
        
        // Aplicar filtros
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasPendientes, $filtros);
        
        // Paginar manualmente
        $perPage = $filtros['por_pagina'];
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $facturasPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($facturasFiltradas, $offset, $perPage),
            count($facturasFiltradas),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Obtener resumen
        $resumen = $this->cobranzaService->getResumenFacturasPendientes($codigoVendedor);

        return view('facturas-pendientes.index', [
            'facturasPendientes' => $facturasPaginated,
            'resumen' => $resumen,
            'filtros' => $filtros,
            'tipoUsuario' => $user->hasRole('Vendedor') ? 'Vendedor' : 'Administrador',
            'pageSlug' => 'facturas-pendientes'
        ]);
    }

    public function resumen(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        $resumen = $this->cobranzaService->getResumenFacturasPendientes($codigoVendedor);

        return response()->json($resumen);
    }

    public function ver($tipoDocumento, $numeroDocumento)
    {
        $user = Auth::user();
        
        // Verificar permisos
        if (!$user->can('ver_facturas_pendientes')) {
            abort(403, 'No tienes permisos para acceder a esta vista.');
        }
        
        // Si es vendedor, verificar que la factura pertenezca a su código
        if ($user->hasRole('Vendedor')) {
            $facturasPendientes = $this->cobranzaService->getFacturasPendientes($user->codigo_vendedor, 1000);
            $facturaEncontrada = false;
            
            foreach ($facturasPendientes as $factura) {
                if ($factura['TIPO_DOCTO'] === $tipoDocumento && $factura['NRO_DOCTO'] === $numeroDocumento) {
                    $facturaEncontrada = true;
                    break;
                }
            }
            
            if (!$facturaEncontrada) {
                abort(403, 'No tienes permisos para ver esta factura.');
            }
        }
        
        // Obtener detalles de la factura
        $facturaDetalle = $this->cobranzaService->getFacturaDetalle($tipoDocumento, $numeroDocumento);
        
        if (!$facturaDetalle) {
            abort(404, 'Factura no encontrada.');
        }
        
        // Obtener productos de la factura
        $productosFactura = $this->cobranzaService->getProductosFactura($tipoDocumento, $numeroDocumento);
        
        // Obtener relación FCV con NVV asociada
        $relacionFcvNvv = $this->cobranzaService->getRelacionFcvNvv($tipoDocumento, $numeroDocumento);
        
        return view('facturas-pendientes.ver', [
            'factura' => $facturaDetalle,
            'productos' => $productosFactura,
            'relacionFcvNvv' => $relacionFcvNvv,
            'pageSlug' => 'facturas-pendientes'
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
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes($codigoVendedor, 10000);
        
        // Aplicar filtros si existen
        $filtros = [
            'buscar' => $request->get('buscar', ''),
            'estado' => $request->get('estado', ''),
            'tipo_documento' => $request->get('tipo_documento', ''),
            'cliente' => $request->get('cliente', ''),
            'saldo_min' => $request->get('saldo_min', ''),
            'saldo_max' => $request->get('saldo_max', ''),
            'fecha_desde' => $request->get('fecha_desde', ''),
            'fecha_hasta' => $request->get('fecha_hasta', ''),
            'vendedores' => $request->get('vendedores', [])
        ];
        
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasPendientes, $filtros);

        // Generar archivo Excel
        $filename = 'facturas_pendientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return response()->json([
            'success' => true,
            'message' => 'Exportación completada',
            'filename' => $filename,
            'total_registros' => count($facturasFiltradas),
            'download_url' => route('facturas-pendientes.download', [
                'filtros' => base64_encode(json_encode($filtros))
            ])
        ]);
    }

    public function download(Request $request)
    {
        $user = Auth::user();
        
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Decodificar filtros
        $filtros = json_decode(base64_decode($request->get('filtros', '{}')), true);
        
        // Obtener todos los datos sin paginación
        $facturasPendientes = $this->cobranzaService->getFacturasPendientes($codigoVendedor, 10000);
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasPendientes, $filtros);

        // Generar Excel
        $filename = 'facturas_pendientes_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return $this->generarExcel($facturasFiltradas, $filename);
    }

    private function generarExcel($facturas, $filename)
    {
        // Crear archivo Excel usando PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título del reporte
        $sheet->setCellValue('A1', 'REPORTE DE FACTURAS PENDIENTES');
        $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i:s'));
        $sheet->setCellValue('A3', 'Total de registros: ' . count($facturas));
        
        // Estilo para el título
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2:A3')->getFont()->setSize(10);
        
        // Encabezados de la tabla
        $row = 5;
        $headers = [
            'A' . $row => 'Tipo Doc',
            'B' . $row => 'Nro Doc',
            'C' . $row => 'Cliente',
            'D' . $row => 'Código',
            'E' . $row => 'Fecha Emisión',
            'F' . $row => 'Fecha Vencimiento',
            'G' . $row => 'Días Pendientes',
            'H' . $row => 'Valor Total',
            'I' . $row => 'Saldo',
            'J' . $row => 'Abonos',
            'K' . $row => 'Estado',
            'L' . $row => 'Vendedor'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Estilo para encabezados
        $sheet->getStyle('A' . $row . ':L' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':L' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        
        // Datos
        $row = 6;
        $totalValor = 0;
        $totalSaldo = 0;
        $totalAbonos = 0;
        $estados = [];
        $vendedores = [];
        
        foreach ($facturas as $factura) {
            $sheet->setCellValue('A' . $row, $factura['TIPO_DOCTO'] ?? '');
            $sheet->setCellValue('B' . $row, $factura['NRO_DOCTO'] ?? '');
            $sheet->setCellValue('C' . $row, $factura['CLIENTE'] ?? '');
            $sheet->setCellValue('D' . $row, $factura['CODIGO'] ?? '');
            $sheet->setCellValue('E' . $row, $factura['EMISION'] ?? '');
            $sheet->setCellValue('F' . $row, $factura['U_VCMTO'] ?? '');
            $sheet->setCellValue('G' . $row, $factura['DIAS'] ?? 0);
            $sheet->setCellValue('H' . $row, $factura['VALOR'] ?? 0);
            $sheet->setCellValue('I' . $row, $factura['SALDO'] ?? 0);
            $sheet->setCellValue('J' . $row, $factura['ABONOS'] ?? 0);
            $sheet->setCellValue('K' . $row, $factura['ESTADO'] ?? '');
            $sheet->setCellValue('L' . $row, $factura['VENDEDOR'] ?? '');
            
            // Acumular totales
            $valor = (float)($factura['VALOR'] ?? 0);
            $saldo = (float)($factura['SALDO'] ?? 0);
            $abonos = (float)($factura['ABONOS'] ?? 0);
            
            $totalValor += $valor;
            $totalSaldo += $saldo;
            $totalAbonos += $abonos;
            
            // Contar por estado
            $estado = $factura['ESTADO'] ?? 'SIN ESTADO';
            $estados[$estado] = ($estados[$estado] ?? 0) + 1;
            
            // Contar por vendedor
            $vendedor = $factura['VENDEDOR'] ?? 'SIN VENDEDOR';
            $vendedores[$vendedor] = ($vendedores[$vendedor] ?? 0) + 1;
            
            $row++;
        }
        
        // Fila de totales principales
        $row++;
        $sheet->setCellValue('A' . $row, 'TOTALES GENERALES:');
        $sheet->setCellValue('H' . $row, $totalValor);
        $sheet->setCellValue('I' . $row, $totalSaldo);
        $sheet->setCellValue('J' . $row, $totalAbonos);
        
        // Formatear totales en negrita
        $sheet->getStyle('A' . $row . ':L' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':L' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D4EDDA');
        
        // Resumen por estado
        $row += 2;
        $sheet->setCellValue('A' . $row, 'RESUMEN POR ESTADO:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        foreach ($estados as $estado => $cantidad) {
            $sheet->setCellValue('A' . $row, $estado . ':');
            $sheet->setCellValue('B' . $row, $cantidad . ' facturas');
            $row++;
        }
        
        // Resumen por vendedor
        $row += 1;
        $sheet->setCellValue('A' . $row, 'RESUMEN POR VENDEDOR:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        foreach ($vendedores as $vendedor => $cantidad) {
            $sheet->setCellValue('A' . $row, $vendedor . ':');
            $sheet->setCellValue('B' . $row, $cantidad . ' facturas');
            $row++;
        }
        
        // Información adicional para el vendedor
        $row += 2;
        $sheet->setCellValue('A' . $row, 'INFORMACIÓN PARA EL VENDEDOR:');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;
        
        $sheet->setCellValue('A' . $row, '• Total de ventas del período: $' . number_format($totalValor, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Saldo pendiente de cobro: $' . number_format($totalSaldo, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Total de abonos recibidos: $' . number_format($totalAbonos, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Porcentaje de cobranza: ' . ($totalValor > 0 ? round(($totalAbonos / $totalValor) * 100, 1) : 0) . '%');
        
        // Autoajustar columnas
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Bordes para la tabla de datos
        $sheet->getStyle('A5:L' . (5 + count($facturas) + 1))->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Crear respuesta
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    private function aplicarFiltrosFacturas($facturasPendientes, $filtros)
    {
        // Filtrar por búsqueda general
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($buscar) {
                return strpos(strtolower($factura['CLIENTE'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($factura['TIPO_DOCTO'] . '-' . $factura['NRO_DOCTO']), $buscar) !== false ||
                       strpos(strtolower($factura['CODIGO'] ?? ''), $buscar) !== false;
            });
        }

        // Filtrar por estado
        if (!empty($filtros['estado'])) {
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($filtros) {
                return $factura['ESTADO'] === $filtros['estado'];
            });
        }

        // Filtrar por tipo de documento
        if (!empty($filtros['tipo_documento'])) {
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($filtros) {
                return $factura['TIPO_DOCTO'] === $filtros['tipo_documento'];
            });
        }

        // Filtrar por cliente
        if (!empty($filtros['cliente'])) {
            $cliente = strtolower($filtros['cliente']);
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($cliente) {
                return strpos(strtolower($factura['CLIENTE'] ?? ''), $cliente) !== false;
            });
        }

        // Filtrar por saldo mínimo
        if (!empty($filtros['saldo_min'])) {
            $saldoMin = (float)$filtros['saldo_min'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($saldoMin) {
                return (float)($factura['SALDO'] ?? 0) >= $saldoMin;
            });
        }

        // Filtrar por saldo máximo
        if (!empty($filtros['saldo_max'])) {
            $saldoMax = (float)$filtros['saldo_max'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($saldoMax) {
                return (float)($factura['SALDO'] ?? 0) <= $saldoMax;
            });
        }

        // Filtrar por rango de fechas
        if (!empty($filtros['fecha_desde'])) {
            $fechaDesde = $filtros['fecha_desde'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($fechaDesde) {
                $fechaEmision = $factura['EMISION'] ?? '';
                // Convertir fecha de SQL Server a formato YYYY-MM-DD
                $fechaConvertida = $this->convertirFechaSqlServer($fechaEmision);
                return $fechaConvertida >= $fechaDesde;
            });
        }

        if (!empty($filtros['fecha_hasta'])) {
            $fechaHasta = $filtros['fecha_hasta'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($fechaHasta) {
                $fechaEmision = $factura['EMISION'] ?? '';
                // Convertir fecha de SQL Server a formato YYYY-MM-DD
                $fechaConvertida = $this->convertirFechaSqlServer($fechaEmision);
                return $fechaConvertida <= $fechaHasta;
            });
        }

        // Filtrar por vendedores (para supervisor)
        if (!empty($filtros['vendedores']) && is_array($filtros['vendedores'])) {
            $vendedores = $filtros['vendedores'];
            $facturasPendientes = array_filter($facturasPendientes, function($factura) use ($vendedores) {
                $vendedor = $factura['VENDEDOR'] ?? '';
                return in_array($vendedor, $vendedores);
            });
        }

        // Ordenar resultados
        $ordenarPor = $filtros['ordenar_por'] ?? 'NRO_DOCTO';
        $orden = $filtros['orden'] ?? 'desc';
        
        usort($facturasPendientes, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor] ?? 0;
            $valorB = $b[$ordenarPor] ?? 0;
            
            if (is_numeric($valorA) && is_numeric($valorB)) {
                $comparacion = $valorA <=> $valorB;
            } else {
                $comparacion = strcasecmp($valorA, $valorB);
            }
            
            return $orden === 'desc' ? -$comparacion : $comparacion;
        });

        return array_values($facturasPendientes);
    }

    private function convertirFechaSqlServer($fechaSqlServer)
    {
        if (empty($fechaSqlServer)) {
            return '';
        }
        
        // Convertir fecha de formato "Oct 23 2025 12:00AM" a "2025-10-23"
        try {
            // Intentar diferentes formatos
            $formatos = [
                'M j Y H:i:s:000A',  // Oct 23 2025 12:00:00:000AM
                'M j Y H:i:s:000a',  // Oct 23 2025 12:00:00:000am
                'M j Y H:iA',        // Oct 23 2025 12:00AM
                'M j Y H:ia',        // Oct 23 2025 12:00am
                'M j Y',             // Oct 23 2025
            ];
            
            foreach ($formatos as $formato) {
                $fecha = \DateTime::createFromFormat($formato, $fechaSqlServer);
                if ($fecha !== false) {
                    return $fecha->format('Y-m-d');
                }
            }
            
            // Si ningún formato funciona, intentar con strtotime
            $timestamp = strtotime($fechaSqlServer);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
            
        } catch (\Exception $e) {
            // Si falla la conversión, devolver la fecha original
        }
        
        return $fechaSqlServer;
    }
}