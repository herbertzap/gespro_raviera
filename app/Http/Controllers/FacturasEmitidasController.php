<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CobranzaService;
use Illuminate\Support\Facades\Auth;

class FacturasEmitidasController extends Controller
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
            'vendedor' => $request->get('vendedor', ''), // Para supervisor (select simple)
            'ordenar_por' => $request->get('ordenar_por', 'NRO_DOCTO'),
            'orden' => $request->get('orden', 'desc'),
        ];

        // Determinar código de vendedor
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener TODAS las facturas emitidas (sin límite para filtros)
        $facturasEmitidas = $this->cobranzaService->getFacturasEmitidas($codigoVendedor, 10000);
        
        // Aplicar filtros
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasEmitidas, $filtros);
        
        // Implementar paginación manual
        $perPage = 30;
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        
        $totalFacturas = count($facturasFiltradas);
        $facturasPaginadas = array_slice($facturasFiltradas, $offset, $perPage);
        
        // Crear objeto de paginación manual
        $pagination = new \Illuminate\Pagination\LengthAwarePaginator(
            $facturasPaginadas,
            $totalFacturas,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );
        
        // Agregar parámetros de filtro a la paginación
        $pagination->appends($filtros);
        
        // Obtener vendedores para el filtro (solo para supervisor)
        $vendedores = [];
        if ($user->hasRole('Super Admin') || $user->hasRole('Supervisor')) {
            $vendedores = $this->cobranzaService->getVendedores();
        }

        // Obtener lista de clientes únicos para el select
        $clientes = $this->obtenerClientesUnicos($facturasEmitidas);

        return view('facturas-emitidas.index', compact('facturasFiltradas', 'filtros', 'vendedores', 'pagination', 'clientes'));
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        
        // Obtener filtros
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
            'dias_max' => $request->get('dias_min', ''),
            'fecha_desde' => $request->get('fecha_desde', ''),
            'fecha_hasta' => $request->get('fecha_hasta', ''),
            'vendedor' => $request->get('vendedor', ''),
            'ordenar_por' => $request->get('ordenar_por', 'NRO_DOCTO'),
            'orden' => $request->get('orden', 'desc'),
        ];

        // Determinar código de vendedor
        $codigoVendedor = null;
        if ($user->hasRole('Vendedor')) {
            $codigoVendedor = $user->codigo_vendedor;
        }

        // Obtener todas las facturas emitidas
        $facturasEmitidas = $this->cobranzaService->getFacturasEmitidas($codigoVendedor, 10000);
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasEmitidas, $filtros);

        // Generar URL de descarga
        $filtrosEncoded = base64_encode(json_encode($filtros));
        $downloadUrl = route('facturas-emitidas.download', ['filtros' => $filtrosEncoded]);

        return response()->json([
            'success' => true,
            'download_url' => $downloadUrl,
            'total_registros' => count($facturasFiltradas)
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
        
        // Obtener todas las facturas emitidas sin paginación
        $facturasEmitidas = $this->cobranzaService->getFacturasEmitidas($codigoVendedor, 10000);
        $facturasFiltradas = $this->aplicarFiltrosFacturas($facturasEmitidas, $filtros);

        // Generar Excel
        $filename = 'facturas_emitidas_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return $this->generarExcel($facturasFiltradas, $filename);
    }

    private function aplicarFiltrosFacturas($facturas, $filtros)
    {
        // Aplicar filtros (misma lógica que FacturasPendientesController)
        if (!empty($filtros['buscar'])) {
            $buscar = strtolower($filtros['buscar']);
            $facturas = array_filter($facturas, function($factura) use ($buscar) {
                return strpos(strtolower($factura['NRO_DOCTO'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($factura['NOMBRE_CLIENTE'] ?? ''), $buscar) !== false ||
                       strpos(strtolower($factura['CODIGO_CLIENTE'] ?? ''), $buscar) !== false;
            });
        }

        if (!empty($filtros['estado'])) {
            $facturas = array_filter($facturas, function($factura) use ($filtros) {
                return ($factura['ESTADO'] ?? '') === $filtros['estado'];
            });
        }

        if (!empty($filtros['tipo_documento'])) {
            $facturas = array_filter($facturas, function($factura) use ($filtros) {
                return ($factura['TIPO_DOCTO'] ?? '') === $filtros['tipo_documento'];
            });
        }

        // Filtrar por cliente (código exacto del select)
        if (!empty($filtros['cliente'])) {
            $clienteCodigo = strtolower($filtros['cliente']);
            $facturas = array_filter($facturas, function($factura) use ($clienteCodigo) {
                $codigoFactura = strtolower($factura['CODIGO_CLIENTE'] ?? '');
                return $codigoFactura === $clienteCodigo;
            });
        }

        if (!empty($filtros['fecha_desde'])) {
            $fechaDesde = $filtros['fecha_desde'];
            $facturas = array_filter($facturas, function($factura) use ($fechaDesde) {
                $fechaEmision = $factura['EMISION'] ?? '';
                $fechaConvertida = $this->convertirFechaSqlServer($fechaEmision);
                return $fechaConvertida >= $fechaDesde;
            });
        }

        if (!empty($filtros['fecha_hasta'])) {
            $fechaHasta = $filtros['fecha_hasta'];
            $facturas = array_filter($facturas, function($factura) use ($fechaHasta) {
                $fechaEmision = $factura['EMISION'] ?? '';
                $fechaConvertida = $this->convertirFechaSqlServer($fechaEmision);
                return $fechaConvertida <= $fechaHasta;
            });
        }

        // Filtrar por vendedor (para supervisor)
        if (!empty($filtros['vendedor'])) {
            $vendedorFiltro = $filtros['vendedor'];
            $facturas = array_filter($facturas, function($factura) use ($vendedorFiltro) {
                $codigoVendedor = $factura['CODIGO_VENDEDOR'] ?? '';
                return $codigoVendedor === $vendedorFiltro;
            });
        }

        // Ordenar
        $ordenarPor = $filtros['ordenar_por'] ?? 'NRO_DOCTO';
        $orden = $filtros['orden'] ?? 'desc';
        
        usort($facturas, function($a, $b) use ($ordenarPor, $orden) {
            $valorA = $a[$ordenarPor] ?? '';
            $valorB = $b[$ordenarPor] ?? '';
            
            if ($orden === 'asc') {
                return $valorA <=> $valorB;
            } else {
                return $valorB <=> $valorA;
            }
        });

        return array_values($facturas);
    }

    private function convertirFechaSqlServer($fechaSqlServer)
    {
        if (empty($fechaSqlServer)) {
            return '';
        }
        
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

    private function generarExcel($facturas, $filename)
    {
        // Crear archivo Excel usando PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Título del reporte
        $sheet->setCellValue('A1', 'REPORTE DE FACTURAS EMITIDAS');
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
            'H' . $row => 'Valor Neto',
            'I' . $row => 'Valor Total',
            'J' . $row => 'Abonos',
            'K' . $row => 'Saldo',
            'L' . $row => 'Estado',
            'M' . $row => 'Vendedor'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Estilo para los encabezados
        $sheet->getStyle('A5:M5')->getFont()->setBold(true);
        $sheet->getStyle('A5:M5')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E0E0E0');
        
        // Datos de las facturas
        $row = 6;
        $totalNeto = 0;
        $totalValor = 0;
        $totalSaldo = 0;
        $totalAbonos = 0;
        
        foreach ($facturas as $factura) {
            $sheet->setCellValue('A' . $row, $factura['TIPO_DOCTO'] ?? '');
            $sheet->setCellValue('B' . $row, $factura['NRO_DOCTO'] ?? '');
            $sheet->setCellValue('C' . $row, $factura['NOMBRE_CLIENTE'] ?? '');
            $sheet->setCellValue('D' . $row, $factura['CODIGO_CLIENTE'] ?? '');
            $sheet->setCellValue('E' . $row, $factura['EMISION'] ?? '');
            $sheet->setCellValue('F' . $row, $factura['VENCIMIENTO'] ?? '');
            $sheet->setCellValue('G' . $row, $factura['DIAS_VENCIDO'] ?? 0);
            $sheet->setCellValue('H' . $row, $factura['VALOR_NETO'] ?? 0);
            $sheet->setCellValue('I' . $row, $factura['VALOR_TOTAL'] ?? 0);
            $sheet->setCellValue('J' . $row, $factura['ABONOS'] ?? 0);
            $sheet->setCellValue('K' . $row, $factura['SALDO'] ?? 0);
            $sheet->setCellValue('L' . $row, $factura['ESTADO'] ?? '');
            $sheet->setCellValue('M' . $row, $factura['NOMBRE_VENDEDOR'] ?? '');
            
            $totalNeto += (float)($factura['VALOR_NETO'] ?? 0);
            $totalValor += (float)($factura['VALOR_TOTAL'] ?? 0);
            $totalSaldo += (float)($factura['SALDO'] ?? 0);
            $totalAbonos += (float)($factura['ABONOS'] ?? 0);
            
            $row++;
        }
        
        // Fila de totales principales
        $row++;
        $sheet->setCellValue('A' . $row, 'TOTALES GENERALES:');
        $sheet->setCellValue('H' . $row, $totalNeto);
        $sheet->setCellValue('I' . $row, $totalValor);
        $sheet->setCellValue('J' . $row, $totalAbonos);
        $sheet->setCellValue('K' . $row, $totalSaldo);
        
        // Estilo para totales
        $sheet->getStyle('A' . $row . ':L' . $row)->getFont()->setBold(true);
        $sheet->getStyle('A' . $row . ':L' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D4E6F1');
        
        // Resumen por estado
        $row += 2;
        $sheet->setCellValue('A' . $row, 'RESUMEN POR ESTADO:');
        $row++;
        
        $estados = [];
        foreach ($facturas as $factura) {
            $estado = $factura['ESTADO'] ?? 'N/A';
            if (!isset($estados[$estado])) {
                $estados[$estado] = ['cantidad' => 0, 'valor' => 0, 'saldo' => 0];
            }
            $estados[$estado]['cantidad']++;
            $estados[$estado]['valor'] += (float)($factura['VALOR_TOTAL'] ?? 0);
            $estados[$estado]['saldo'] += (float)($factura['SALDO'] ?? 0);
        }
        
        foreach ($estados as $estado => $datos) {
            $sheet->setCellValue('A' . $row, $estado . ':');
            $sheet->setCellValue('B' . $row, $datos['cantidad'] . ' facturas');
            $sheet->setCellValue('H' . $row, $datos['valor']);
            $sheet->setCellValue('I' . $row, $datos['saldo']);
            $row++;
        }
        
        // Resumen por vendedor
        $row += 1;
        $sheet->setCellValue('A' . $row, 'RESUMEN POR VENDEDOR:');
        $row++;
        
        $vendedores = [];
        foreach ($facturas as $factura) {
            $vendedor = $factura['NOMBRE_VENDEDOR'] ?? 'Sin vendedor';
            if (!isset($vendedores[$vendedor])) {
                $vendedores[$vendedor] = ['cantidad' => 0, 'valor' => 0, 'saldo' => 0];
            }
            $vendedores[$vendedor]['cantidad']++;
            $vendedores[$vendedor]['valor'] += (float)($factura['VALOR_TOTAL'] ?? 0);
            $vendedores[$vendedor]['saldo'] += (float)($factura['SALDO'] ?? 0);
        }
        
        foreach ($vendedores as $vendedor => $datos) {
            $sheet->setCellValue('A' . $row, $vendedor . ':');
            $sheet->setCellValue('B' . $row, $datos['cantidad'] . ' facturas');
            $sheet->setCellValue('H' . $row, $datos['valor']);
            $sheet->setCellValue('I' . $row, $datos['saldo']);
            $row++;
        }
        
        // Información adicional para el vendedor
        $row += 2;
        $sheet->setCellValue('A' . $row, 'INFORMACIÓN PARA EL VENDEDOR:');
        $row++;
        $sheet->setCellValue('A' . $row, '• Total de ventas del período: $' . number_format($totalValor, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Total neto de ventas del período: $' . number_format($totalNeto, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Saldo pendiente de cobro: $' . number_format($totalSaldo, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Total de abonos recibidos: $' . number_format($totalAbonos, 0, ',', '.'));
        $row++;
        $sheet->setCellValue('A' . $row, '• Porcentaje de cobranza: ' . ($totalValor > 0 ? round(($totalAbonos / $totalValor) * 100, 1) : 0) . '%');
        
        // Autoajustar columnas
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Bordes para la tabla principal
        $lastDataRow = 5 + count($facturas);
        $sheet->getStyle('A5:M' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Generar archivo
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'facturas_emitidas_');
        $writer->save($tempFile);
        
        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Obtener lista de clientes únicos para el select
     */
    private function obtenerClientesUnicos($facturasEmitidas)
    {
        $clientes = [];
        $clientesMap = [];
        
        foreach ($facturasEmitidas as $factura) {
            $codigo = $factura['CODIGO_CLIENTE'] ?? '';
            $nombre = $factura['CLIENTE'] ?? '';
            
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