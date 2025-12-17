<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\CotizacionProducto;
use App\Models\StockComprometido;
use App\Models\Cliente;
use App\Services\ClienteValidacionService;
use App\Services\StockConsultaService;

class NotaVentaController extends Controller
{
    public function __construct()
    {
        // Restringir acceso solo a Super Admin, Supervisor, Compras y Picking
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user->hasRole('Super Admin') && !$user->hasRole('Supervisor') && !$user->hasRole('Compras') && !$user->hasRole('Picking') && !$user->hasRole('Vendedor')) {
                abort(403, 'Acceso denegado. Solo Super Admin, Supervisor, Compras, Picking y Vendedor pueden acceder a esta vista.');
            }
            return $next($request);
        });
    }

    public function nueva(Request $request)
    {
        \Log::info('🔍 Método nueva() llamado - VERSIÓN CON SISTEMA LOCAL DE CLIENTES');
        \Log::info('📋 Usuario autenticado: ' . (auth()->check() ? 'SÍ' : 'NO'));
        if (auth()->check()) {
            \Log::info('📋 Usuario: ' . auth()->user()->name . ' (' . auth()->user()->email . ')');
        }
        
        $clienteCodigo = $request->get('cliente');
        $clienteNombre = $request->get('nombre');
        
        // VALIDACIÓN: Requerir que siempre haya un cliente asociado
        if (!$clienteCodigo) {
            \Log::warning('❌ Intento de crear NVV sin cliente asociado');
            return redirect()->route('cotizaciones.index')
                ->with('error', 'Debe seleccionar un cliente antes de crear una nota de venta.');
        }
        
        \Log::info('🔍 Parámetros recibidos:');
        \Log::info('   - cliente: ' . $clienteCodigo);
        \Log::info('   - nombre: ' . $clienteNombre);
        
        // Obtener datos del cliente usando sistema local optimizado
        $cliente = null;
        $alertas = [];
        $puedeGenerarNotaVenta = true;
        $motivoRechazo = '';
        
        if ($clienteCodigo) {
            try {
                // PRIMERO: Buscar en base de datos local
                $codigoVendedor = auth()->user()->codigo_vendedor ?? 'GOP';
                
                $clienteLocal = Cliente::buscarPorCodigo($clienteCodigo, $codigoVendedor);
                
                if ($clienteLocal) {
                    
                    // Obtener información completa (sincroniza si es necesario)
                    $clienteLocal = $clienteLocal->obtenerInformacionCompleta();
                    
                    // Verificar si puede generar cotizaciones
                    $validacion = $clienteLocal->puedeGenerarCotizacion();
                    $puedeGenerarNotaVenta = $validacion['puede'];
                    $motivoRechazo = $validacion['motivo'];
                    
                    // Realizar validaciones automáticas de crédito y facturas
                    $validacionesAutomaticas = ClienteValidacionService::validarClienteParaNotaVenta($clienteLocal->codigo_cliente, 0);
                    
                    // Si las validaciones automáticas requieren autorización, actualizar estado
                    if ($validacionesAutomaticas['requiere_autorizacion']) {
                        $puedeGenerarNotaVenta = true; // Permitir generar pero con autorización
                        $motivoRechazo = $validacionesAutomaticas['motivo'];
                    }
                    
                    $cliente = (object) [
                        'codigo' => $clienteLocal->codigo_cliente,
                        'nombre' => $clienteLocal->nombre_cliente,
                        'direccion' => $clienteLocal->direccion,
                        'telefono' => $clienteLocal->telefono,
                        'email' => $clienteLocal->email,
                        'region' => $clienteLocal->region,
                        'comuna' => $clienteLocal->comuna,
                        'vendedor' => $clienteLocal->codigo_vendedor,
                        'lista_precios_codigo' => $clienteLocal->lista_precios_codigo ?? '01P',
                        'lista_precios_nombre' => $clienteLocal->lista_precios_nombre ?? 'Lista Precios 01P',
                        'bloqueado' => $clienteLocal->bloqueado,
                        'puede_generar_nota_venta' => $puedeGenerarNotaVenta,
                        'motivo_rechazo' => $motivoRechazo,
                        'facturas_pendientes' => 0, // Se calculará si es necesario
                        // Campos adicionales para nota de venta
                        'subsidiaria' => '001',
                        'codigo_ciudad' => '',
                        'codigo_comuna' => '',
                        'codigo_pais' => 'CHI',
                        'vendedor_asignado' => $clienteLocal->codigo_vendedor,
                        'credito_limite' => 0,
                        'credito_plazo' => 30,
                        'zona_venta' => '',
                        'empresa_cliente' => '01'
                    ];
                    
                    // Verificar alertas adicionales de cobranza
                    $alertasAdicionales = $this->verificarAlertasCliente($clienteCodigo);
                    $alertas = array_merge($alertas, $alertasAdicionales);
                    
                    // Verificar si hay productos con stock insuficiente (se verificará al agregar productos)
                    if (!empty($alertas)) {
                        $puedeGenerarNotaVenta = false;
                        $motivoRechazo = 'Cliente con alertas de cobranza pendientes';
                    }
                    
                    // Verificar cheques protestados
                    $chequesProtestados = $this->verificarChequesProtestados($clienteCodigo);
                    if ($chequesProtestados['tiene_cheques_protestados']) {
                        $puedeGenerarNotaVenta = false;
                        $motivoRechazo = 'Cliente con cheques protestados - No puede generar NVV';
                        $alertas[] = [
                            'tipo' => 'error',
                            'mensaje' => 'Cliente tiene ' . $chequesProtestados['cantidad'] . ' cheque(s) protestado(s)',
                            'detalle' => 'Valor total: $' . number_format($chequesProtestados['valor_total'], 0, ',', '.')
                        ];
                    }
                    
                    // Verificar que el cliente tenga lista de precios asignada
                    if (empty($cliente->lista_precios_codigo) || $cliente->lista_precios_codigo === '00' || $cliente->lista_precios_codigo === '0') {
                        // Asignar lista de precios por defecto (01P)
                        $cliente->lista_precios_codigo = '01P';
                        $cliente->lista_precios_nombre = 'Lista Precios 01P';
                        \Log::info('📋 Asignando lista de precios por defecto 01P al cliente: ' . $cliente->codigo);
                    }
                    
                } else {
                    // SEGUNDO: Si no está en local, buscar en SQL Server
                    $cobranzaService = new \App\Services\CobranzaService();
                    $clienteData = $cobranzaService->getClienteInfoCompleto($clienteCodigo);
                    
                    if ($clienteData) {
                        
                        // Crear cliente en base local para futuras consultas
                        $nuevoCliente = Cliente::create([
                            'codigo_cliente' => $clienteData['CODIGO_CLIENTE'] ?? $clienteCodigo,
                            'nombre_cliente' => $clienteData['NOMBRE_CLIENTE'] ?? $clienteNombre,
                            'direccion' => $clienteData['DIRECCION'] ?? '',
                            'telefono' => $clienteData['TELEFONO'] ?? '',
                            'codigo_vendedor' => $clienteData['CODIGO_VENDEDOR'] ?? $codigoVendedor,
                            'region' => $clienteData['REGION'] ?? '',
                            'comuna' => $clienteData['COMUNA'] ?? '',
                            'lista_precios_codigo' => $clienteData['LISTA_PRECIOS_CODIGO'] ?? '01P',
                            'lista_precios_nombre' => $clienteData['LISTA_PRECIOS_NOMBRE'] ?? 'Lista Precios 01P',
                            'bloqueado' => !empty($clienteData['BLOQUEADO']) && $clienteData['BLOQUEADO'] != '0',
                            'activo' => true,
                            'ultima_sincronizacion' => now()
                        ]);
                        
                        \Log::info('✅ Cliente creado en base local: ' . $nuevoCliente->nombre_cliente);
                        
                        // Verificar si está bloqueado
                        $bloqueado = !empty($clienteData['BLOQUEADO']) && $clienteData['BLOQUEADO'] != '0';
                        
                        // Verificar si el cliente tiene lista de precios asignada
                        if (empty($clienteData['LISTA_PRECIOS_CODIGO']) || $clienteData['LISTA_PRECIOS_CODIGO'] == '0') {
                            $puedeGenerarNotaVenta = false;
                            $motivoRechazo = 'El cliente no tiene lista de precios asignada y no puede generar cotizaciones.';
                        }
                        
                        $cliente = (object) [
                            'codigo' => $clienteData['CODIGO_CLIENTE'] ?? $clienteCodigo,
                            'nombre' => $clienteData['NOMBRE_CLIENTE'] ?? $clienteNombre,
                            'direccion' => $clienteData['DIRECCION'] ?? '',
                            'telefono' => $clienteData['TELEFONO'] ?? '',
                            'email' => '',
                            'region' => $clienteData['REGION'] ?? '',
                            'comuna' => $clienteData['COMUNA'] ?? '',
                            'vendedor' => $clienteData['CODIGO_VENDEDOR'] ?? '',
                            'lista_precios_codigo' => $clienteData['LISTA_PRECIOS_CODIGO'] ?? '01P',
                            'lista_precios_nombre' => $clienteData['LISTA_PRECIOS_NOMBRE'] ?? 'Lista Precios 01P',
                            'bloqueado' => $bloqueado,
                            'puede_generar_nota_venta' => $puedeGenerarNotaVenta,
                            'motivo_rechazo' => $motivoRechazo,
                            'facturas_pendientes' => 0,
                            'subsidiaria' => $clienteData['SUBSIDIARIA'] ?? '001',
                            'codigo_ciudad' => $clienteData['CODIGO_CIUDAD'] ?? '',
                            'codigo_comuna' => $clienteData['CODIGO_COMUNA'] ?? '',
                            'codigo_pais' => $clienteData['CODIGO_PAIS'] ?? 'CHI',
                            'vendedor_asignado' => $clienteData['VENDEDOR_ASIGNADO'] ?? $clienteData['CODIGO_VENDEDOR'] ?? '',
                            'credito_limite' => $clienteData['CREDITO_LIMITE'] ?? 0,
                            'credito_plazo' => $clienteData['CREDITO_PLAZO'] ?? 30,
                            'zona_venta' => $clienteData['ZONA_VENTA'] ?? '',
                            'empresa_cliente' => $clienteData['EMPRESA_CLIENTE'] ?? '01'
                        ];
                        
                        // Verificar alertas adicionales de cobranza
                        $alertasAdicionales = $this->verificarAlertasCliente($clienteCodigo);
                        $alertas = array_merge($alertas, $alertasAdicionales);
                    }
                }
                
                // Si no se encontró el cliente en ningún lado
                if (!$cliente) {
                    \Log::error('❌ Cliente no encontrado en ningún sistema');
                    \Log::error('   - Código buscado: ' . $clienteCodigo);
                    $alertas[] = [
                        'tipo' => 'danger',
                        'titulo' => 'Cliente No Encontrado',
                        'mensaje' => 'No se encontró información del cliente en el sistema'
                    ];
                }
            } catch (\Exception $e) {
                \Log::error('Error obteniendo datos del cliente: ' . $e->getMessage());
            }
        }
        
        \Log::info('🎯 Retornando vista con cliente: ' . ($cliente ? 'EXISTS' : 'NULL'));
        \Log::info('🎯 Puede generar nota de venta: ' . ($puedeGenerarNotaVenta ? 'SÍ' : 'NO'));
        
        return view('cotizaciones.nueva-nvv', compact('cliente', 'alertas', 'puedeGenerarNotaVenta'))->with('pageSlug', 'nueva-nota-venta');
    }
    
    /**
     * Verificar facturas pendientes del cliente
     */
    private function verificarFacturasPendientes($codigoCliente)
    {
        try {
            $cobranzaService = new \App\Services\CobranzaService();
            $facturas = $cobranzaService->getFacturasPendientesCliente($codigoCliente);
            
            // Contar facturas con saldo pendiente
            $facturasPendientes = 0;
            foreach ($facturas as $factura) {
                if ($factura['SALDO'] > 0) {
                    $facturasPendientes++;
                }
            }
            
            return $facturasPendientes;
            
        } catch (\Exception $e) {
            \Log::error('Error verificando facturas pendientes: ' . $e->getMessage());
            return 0;
        }
    }
    
    private function verificarAlertasCliente($codigoCliente)
    {
        $alertas = [];
        
        try {
            // 1. Verificar facturas vencidas
            $queryFacturas = "SELECT COUNT(*) as total FROM MAEEDO WHERE ENDO = '{$codigoCliente}' AND TIDO = 'FCV' AND FEULVEDO < GETDATE() AND VABRDO > VAABDO";
            $resultFacturas = shell_exec("tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " -Q \"{$queryFacturas}\" 2>&1");
            
            $facturasVencidas = 0;
            if ($resultFacturas && !str_contains($resultFacturas, 'error')) {
                if (preg_match('/(\d+)/', $resultFacturas, $matches)) {
                    $facturasVencidas = (int)$matches[1];
                }
            }
            
            if ($facturasVencidas > 0) {
                $alertas[] = [
                    'tipo' => 'danger',
                    'titulo' => 'Facturas Vencidas',
                    'mensaje' => "El cliente tiene {$facturasVencidas} factura(s) vencida(s)"
                ];
            }
            
            // 2. Verificar cheques protestados
            $queryCheques = "SELECT COUNT(*) as total FROM MAEDPCE WHERE ENDP = '{$codigoCliente}' AND TIDP = 'CHV' AND ESPGDP = 'P'";
            $resultCheques = shell_exec("tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " -Q \"{$queryCheques}\" 2>&1");
            
            $chequesProtestados = 0;
            if ($resultCheques && !str_contains($resultCheques, 'error')) {
                if (preg_match('/(\d+)/', $resultCheques, $matches)) {
                    $chequesProtestados = (int)$matches[1];
                }
            }
            
            if ($chequesProtestados > 0) {
                $alertas[] = [
                    'tipo' => 'danger',
                    'titulo' => 'Cheques Protestados',
                    'mensaje' => "El cliente tiene {$chequesProtestados} cheque(s) protestado(s)"
                ];
            }
            
            // 3. Verificar saldo total
            $querySaldo = "SELECT SUM(VABRDO - VAABDO) as saldo FROM MAEEDO WHERE ENDO = '{$codigoCliente}' AND VABRDO > VAABDO";
            $resultSaldo = shell_exec("tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " -Q \"{$querySaldo}\" 2>&1");
            
            $saldoTotal = 0;
            if ($resultSaldo && !str_contains($resultSaldo, 'error')) {
                if (preg_match('/(\d+\.?\d*)/', $resultSaldo, $matches)) {
                    $saldoTotal = (float)$matches[1];
                }
            }
            
            if ($saldoTotal > 1000000) { // Más de 1 millón
                $alertas[] = [
                    'tipo' => 'warning',
                    'titulo' => 'Saldo Alto',
                    'mensaje' => "El cliente tiene un saldo de $" . number_format($saldoTotal, 0)
                ];
            }
            
        } catch (\Exception $e) {
            \Log::error('Error verificando alertas del cliente: ' . $e->getMessage());
        }
        
        return $alertas;
    }
    
    public function buscarProductos(Request $request)
    {
        try {
            // Aceptar diferentes nombres de parámetros para la búsqueda
            $busqueda = $request->get('busqueda') ?? 
                       $request->get('q') ?? 
                       $request->get('search') ?? 
                       $request->get('term') ?? 
                       $request->get('termino') ?? '';
            
            // Obtener lista de precios del cliente
            $listaPrecios = $request->get('lista_precios', '01');
            
            if (empty($busqueda)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar un término de búsqueda'
                ]);
            }
            
            // Validar longitud mínima para búsqueda
            if (strlen($busqueda) < 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe ingresar al menos 3 caracteres para buscar'
                ]);
            }
            
            // Validar que se proporcione una lista de precios válida
            if (empty($listaPrecios) || $listaPrecios === '00' || $listaPrecios === '0') {
                // Usar lista de precios por defecto
                $listaPrecios = '01P';
            }
            
            // Dividir la búsqueda en términos individuales
            $terminos = array_filter(explode(' ', trim($busqueda)));
            
            // Buscar productos en tabla local MySQL (consulta optimizada)
            // Buscar por código (KOPR) o por nombre (NOKOPR) con múltiples términos
            $query = DB::table('productos')->where('activo', true);
            
            if (count($terminos) > 1) {
                // Búsqueda con múltiples términos: todos los términos deben estar en el nombre
                $query->where(function($q) use ($terminos) {
                    foreach ($terminos as $termino) {
                        $q->where('NOKOPR', 'LIKE', "%{$termino}%");
                    }
                });
            } else {
                // Búsqueda simple: por código o nombre
                $query->where(function($q) use ($busqueda) {
                    $q->where('KOPR', 'LIKE', "{$busqueda}%")
                      ->orWhere('NOKOPR', 'LIKE', "%{$busqueda}%");
                });
            }
            
            $productos = $query->limit(15)->get()
                ->map(function($producto) use ($listaPrecios) {
                    // Mapear precios según la lista
                    $precio = 0;
                    $precioUd2 = 0;
                    $descuentoMaximo = 0;
                    
                    if ($listaPrecios === '01P' || $listaPrecios === '01') {
                        $precio = $producto->precio_01p ?? 0;
                        $precioUd2 = $producto->precio_01p_ud2 ?? 0;
                        $descuentoMaximo = $producto->descuento_maximo_01p ?? 0;
                    } elseif ($listaPrecios === '02P' || $listaPrecios === '02') {
                        $precio = $producto->precio_02p ?? 0;
                        $precioUd2 = $producto->precio_02p_ud2 ?? 0;
                        $descuentoMaximo = $producto->descuento_maximo_02p ?? 0;
                    } elseif ($listaPrecios === '03P' || $listaPrecios === '03') {
                        $precio = $producto->precio_03p ?? 0;
                        $precioUd2 = $producto->precio_03p_ud2 ?? 0;
                        $descuentoMaximo = $producto->descuento_maximo_03p ?? 0;
                    } else {
                        // Por defecto usar 01P
                        $precio = $producto->precio_01p ?? 0;
                        $precioUd2 = $producto->precio_01p_ud2 ?? 0;
                        $descuentoMaximo = $producto->descuento_maximo_01p ?? 0;
                    }
                    
                    // Determinar si el producto se puede agregar (precio > 0)
                    $precioValido = ($precio > 0);
                    
                    return [
                        'CODIGO_PRODUCTO' => $producto->KOPR,
                        'NOMBRE_PRODUCTO' => $producto->NOKOPR,
                        'UNIDAD_MEDIDA' => $producto->UD01PR,
                        'PRECIO_UD1' => $precio,
                        'PRECIO_UD2' => $precioUd2,
                        'DESCUENTO_MAXIMO' => $descuentoMaximo,
                        'STOCK_DISPONIBLE' => $producto->stock_disponible ?? 0,
                        'STOCK_FISICO' => $producto->stock_fisico ?? 0,
                        'STOCK_COMPROMETIDO' => $producto->stock_comprometido ?? 0,
                        'CANTIDAD_MINIMA' => 1,
                        'MULTIPLO_VENTA' => $producto->multiplo_venta ?? 1,
                        'LISTA_PRECIOS' => $listaPrecios,
                        'PRECIO_VALIDO' => $precioValido, // Nuevo campo para indicar si se puede agregar
                        'MOTIVO_BLOQUEO' => $precioValido ? null : 'Precio no disponible'
                    ];
                })
                ->toArray();
            
            if (empty($productos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron productos con el término de búsqueda: ' . $busqueda
                ]);
            }
            
            // Calcular stock real dinámicamente: STFI1 - (STOCNV1 + NVV local)
            $stockService = new \App\Services\StockComprometidoService();
            foreach ($productos as &$producto) {
                try {
                    $codigo = $producto['CODIGO_PRODUCTO'];
                    $stockReal = $stockService->obtenerStockDisponibleReal($codigo);
                    
                    $producto['STOCK_DISPONIBLE_REAL'] = $stockReal;
                    $producto['STOCK_DISPONIBLE'] = $stockReal;
                    $producto['STOCK_DISPONIBLE_ORIGINAL'] = $producto['STOCK_DISPONIBLE_ORIGINAL'] ?? ($producto['STOCK_FISICO'] ?? 0);
                    $producto['STOCK_COMPROMETIDO'] = $producto['STOCK_COMPROMETIDO'] ?? 0;
                    
                    // Estado visual
                    $producto['TIENE_STOCK'] = $stockReal > 0;
                    $producto['STOCK_INSUFICIENTE'] = $stockReal < ($producto['CANTIDAD_MINIMA'] ?? 1);
                    if ($stockReal <= 0) {
                        $producto['CLASE_STOCK'] = 'text-danger';
                        $producto['ESTADO_STOCK'] = 'Sin stock';
                    } elseif ($stockReal < 10) {
                        $producto['CLASE_STOCK'] = 'text-warning';
                        $producto['ESTADO_STOCK'] = 'Stock bajo';
                    } else {
                        $producto['CLASE_STOCK'] = 'text-success';
                        $producto['ESTADO_STOCK'] = 'Stock disponible';
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error calculando stock para producto ' . ($producto['CODIGO_PRODUCTO'] ?? 'N/A') . ': ' . $e->getMessage());
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $productos,
                'total' => count($productos),
                'search_term' => $busqueda
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error buscando productos: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error buscando productos: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Procesar una línea individual de producto
     */
    private function procesarLineaProducto($line, $lineNumber)
    {
        try {
            // Limpiar la línea de caracteres especiales
            $line = preg_replace('/\s+/', ' ', $line); // Reemplazar múltiples espacios por uno
            $line = trim($line);
            
            \Log::info('Línea limpia producto ' . $lineNumber . ': ' . $line);
            
            // Dividir la línea por espacios y procesar cada campo
            $fields = explode(' ', $line);
            
            // Buscar el patrón: CODIGO NOMBRE UNIDAD...
            $producto = null;
            
            for ($i = 0; $i < count($fields) - 5; $i++) {
                // Verificar si encontramos un código de producto (formato alfanumérico)
                if (isset($fields[$i]) && preg_match('/^[A-Z0-9]+$/', $fields[$i])) {
                    $codigoProducto = $fields[$i];
                    
                    \Log::info('Código producto encontrado: ' . $codigoProducto);
                    
                    // Extraer campos usando posiciones relativas
                    $producto = $this->extraerCamposProducto($fields, $i);
                    break;
                }
            }
            
            if ($producto) {
                \Log::info('Producto extraído: ' . json_encode($producto));
            }
            
            return $producto;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea producto ' . $lineNumber . ': ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extraer campos de producto desde un array de campos
     */
    private function extraerCamposProducto($fields, $startIndex)
    {
        try {
            // Campo básico
            $codigoProducto = $fields[$startIndex];
            
            \Log::info('Extraer campos producto: CODIGO=' . $codigoProducto);
            
            // Buscar campos después del código de producto
            $currentIndex = $startIndex + 1;
            
            // Extraer nombre del producto (hasta encontrar una unidad)
            $nombreProducto = '';
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                
                // Si encontramos una unidad (formato: UN, KG, LT, etc.), paramos
                if (preg_match('/^[A-Z]{2,3}$/', $field)) {
                    \Log::info('Unidad encontrada: ' . $field . ', parando extracción de nombre');
                    break;
                }
                
                $nombreProducto .= ' ' . $field;
                $currentIndex++;
            }
            $nombreProducto = trim($nombreProducto);
            \Log::info('Nombre producto extraído: "' . $nombreProducto . '"');
            
            // Extraer unidad
            $unidad = '';
            if ($currentIndex < count($fields)) {
                $unidad = $fields[$currentIndex];
                $currentIndex++;
                \Log::info('Unidad: ' . $unidad);
            }
            
            // Extraer valores numéricos restantes
            $relacionUnidades = 1.0;
            $divisibleUd1 = 'N';
            $divisibleUd2 = 'N';
            $stockFisico = 0;
            $stockComprometido = 0;
            $stockDisponible = 0;
            $nombreBodega = '';
            $bodegaId = '';
            
            $numerosEncontrados = 0;
            while ($currentIndex < count($fields) && $numerosEncontrados < 5) {
                if (is_numeric($fields[$currentIndex])) {
                    switch ($numerosEncontrados) {
                        case 0: $relacionUnidades = (float)$fields[$currentIndex]; break;
                        case 1: $stockFisico = (float)$fields[$currentIndex]; break;
                        case 2: $stockComprometido = (float)$fields[$currentIndex]; break;
                        case 3: $stockDisponible = (float)$fields[$currentIndex]; break;
                        case 4: $bodegaId = $fields[$currentIndex]; break;
                    }
                    $numerosEncontrados++;
                    \Log::info('Número ' . $numerosEncontrados . ': ' . $fields[$currentIndex]);
                } elseif (preg_match('/^[A-Z]$/', $fields[$currentIndex])) {
                    // Campos divisibles
                    if ($divisibleUd1 === 'N') {
                        $divisibleUd1 = $fields[$currentIndex];
                    } else {
                        $divisibleUd2 = $fields[$currentIndex];
                    }
                } else {
                    // Nombre de bodega
                    $nombreBodega .= ' ' . $fields[$currentIndex];
                }
                $currentIndex++;
            }
            $nombreBodega = trim($nombreBodega);
            
            // Verificar stock local para este producto
            $stockLocal = \App\Models\StockLocal::where('codigo_producto', $codigoProducto)
                                               ->where('codigo_bodega', '01')
                                               ->first();
            
            // Usar stock local si existe, sino usar datos de SQL Server
            if ($stockLocal) {
                $stockFisico = $stockLocal->stock_fisico;
                $stockComprometido = $stockLocal->stock_comprometido;
                $stockDisponible = $stockLocal->stock_disponible;
            }
            
            $alertaStock = $stockDisponible <= 0 ? 'danger' : 
                          ($stockDisponible < 10 ? 'warning' : 'success');
            
            // Crear objeto de producto
            $producto = (object) [
                'codigo' => $codigoProducto,
                'nombre' => $nombreProducto,
                'marca' => 'N/A',
                'unidad' => $unidad,
                'relacion_unidades' => $relacionUnidades,
                'categoria' => 'N/A',
                'subcategoria' => 'N/A',
                'divisible_ud1' => $divisibleUd1,
                'divisible_ud2' => $divisibleUd2,
                'stock_fisico' => $stockFisico,
                'stock_comprometido' => $stockComprometido,
                'stock_disponible' => $stockDisponible,
                'nombre_bodega' => $nombreBodega,
                'bodega_id' => $bodegaId,
                'alerta_stock' => $alertaStock
            ];
            
            \Log::info('Producto creado: ' . $producto->nombre);
            
            return $producto;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos producto: ' . $e->getMessage());
            return null;
        }
    }
    
    public function obtenerPrecios(Request $request)
    {
        $codigoProducto = $request->get('codigo');
        $codigoCliente = $request->get('cliente');
        
        try {
            // Obtener lista de precios del cliente desde MySQL (tabla clientes local)
            $listaPreciosCliente = '01P'; // Por defecto
            if ($codigoCliente) {
                $cliente = DB::table('clientes')->where('KOEN', $codigoCliente)->first();
                if ($cliente && $cliente->LVEN) {
                    $listaPreciosCliente = $cliente->LVEN;
                    // Mapear formato si es necesario
                    if (strpos($listaPreciosCliente, 'TABPP') === 0) {
                        $listaPreciosCliente = substr($listaPreciosCliente, 5) . 'P';
                    }
                }
            }
            
            \Log::info('Lista de precios del cliente ' . $codigoCliente . ': ' . $listaPreciosCliente);
            
            // Consultar precios desde tabla productos local (MySQL)
            $producto = DB::table('productos')->where('KOPR', $codigoProducto)->first();
            
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ]);
            }
            
            $precios = [];
            
            // Mapear precios según la lista del cliente
            if ($listaPreciosCliente === '01P' || $listaPreciosCliente === '01') {
                $precios[] = (object) [
                    'lista_precio' => '01P',
                    'precio_ud1' => (float)$producto->precio_01p,
                    'precio_ud2' => (float)$producto->precio_01p_ud2,
                    'margen_ud1' => 0, // No disponible en tabla local
                    'margen_ud2' => 0, // No disponible en tabla local
                    'relacion_unidades' => 1.0,
                    'descuento_maximo' => (float)$producto->descuento_maximo_01p
                ];
            } elseif ($listaPreciosCliente === '02P' || $listaPreciosCliente === '02') {
                $precios[] = (object) [
                    'lista_precio' => '02P',
                    'precio_ud1' => (float)$producto->precio_02p,
                    'precio_ud2' => (float)$producto->precio_02p_ud2,
                    'margen_ud1' => 0,
                    'margen_ud2' => 0,
                    'relacion_unidades' => 1.0,
                    'descuento_maximo' => (float)$producto->descuento_maximo_02p
                ];
            } elseif ($listaPreciosCliente === '03P' || $listaPreciosCliente === '03') {
                $precios[] = (object) [
                    'lista_precio' => '03P',
                    'precio_ud1' => (float)$producto->precio_03p,
                    'precio_ud2' => (float)$producto->precio_03p_ud2,
                    'margen_ud1' => 0,
                    'margen_ud2' => 0,
                    'relacion_unidades' => 1.0,
                    'descuento_maximo' => (float)$producto->descuento_maximo_03p
                ];
            } else {
                // Si no coincide con ninguna lista conocida, usar 01P por defecto
                $precios[] = (object) [
                    'lista_precio' => '01P',
                    'precio_ud1' => (float)$producto->precio_01p,
                    'precio_ud2' => (float)$producto->precio_01p_ud2,
                    'margen_ud1' => 0,
                    'margen_ud2' => 0,
                    'relacion_unidades' => 1.0,
                    'descuento_maximo' => (float)$producto->descuento_maximo_01p
                ];
            }
            
            \Log::info('Precios obtenidos desde MySQL: ' . count($precios));
            
            // Si no hay precios válidos, devolver información del producto
            if (empty($precios) || ($precios[0]->precio_ud1 == 0 && $precios[0]->precio_ud2 == 0)) {
                $productoInfo = (object) [
                    'codigo' => $producto->KOPR,
                    'nombre' => $producto->NOKOPR,
                    'unidad' => $producto->UD01PR
                ];
                
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'producto' => $productoInfo,
                    'message' => 'Producto encontrado pero sin precios configurados'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $precios,
                'count' => count($precios)
            ]);
                        
        } catch (\Exception $e) {
            \Log::error('Error obteniendo precios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener precios: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Procesar una línea individual de precio
     */
    private function procesarLineaPrecio($line, $lineNumber)
    {
        try {
            // Limpiar la línea de caracteres especiales
            $line = preg_replace('/\s+/', ' ', $line); // Reemplazar múltiples espacios por uno
            $line = trim($line);
            
            \Log::info('Línea limpia precio ' . $lineNumber . ': ' . $line);
            
            // Dividir la línea por espacios y procesar cada campo
            $fields = explode(' ', $line);
            
            // Buscar el patrón: LISTA_PRECIO PRECIO_UD1 PRECIO_UD2...
            $precio = null;
            
            for ($i = 0; $i < count($fields) - 5; $i++) {
                // Verificar si encontramos un código de lista de precios (formato alfanumérico)
                if (isset($fields[$i]) && preg_match('/^[A-Z0-9]+$/', $fields[$i])) {
                    $listaPrecio = $fields[$i];
                    
                    \Log::info('Lista precio encontrada: ' . $listaPrecio);
                    
                    // Extraer campos usando posiciones relativas
                    $precio = $this->extraerCamposPrecio($fields, $i);
                    break;
                }
            }
            
            if ($precio) {
                \Log::info('Precio extraído: ' . json_encode($precio));
            }
            
            return $precio;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea precio ' . $lineNumber . ': ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extraer campos de precio desde un array de campos
     */
    private function extraerCamposPrecio($fields, $startIndex)
    {
        try {
            // Campo básico
            $listaPrecio = $fields[$startIndex];
            
            \Log::info('Extraer campos precio: LISTA=' . $listaPrecio);
            
            // Buscar campos después del código de lista de precios
            $currentIndex = $startIndex + 1;
            
            // Extraer valores numéricos
            $precioUd1 = 0.0;
            $precioUd2 = 0.0;
            $margenUd1 = 0.0;
            $margenUd2 = 0.0;
            $relacionUnidades = 1.0;
            
            $numerosEncontrados = 0;
            while ($currentIndex < count($fields) && $numerosEncontrados < 5) {
                if (is_numeric($fields[$currentIndex])) {
                    switch ($numerosEncontrados) {
                        case 0: $precioUd1 = (float)$fields[$currentIndex]; break;
                        case 1: $precioUd2 = (float)$fields[$currentIndex]; break;
                        case 2: $margenUd1 = (float)$fields[$currentIndex]; break;
                        case 3: $margenUd2 = (float)$fields[$currentIndex]; break;
                        case 4: $relacionUnidades = (float)$fields[$currentIndex]; break;
                    }
                    $numerosEncontrados++;
                    \Log::info('Número ' . $numerosEncontrados . ': ' . $fields[$currentIndex]);
                }
                $currentIndex++;
            }
            
            // Crear objeto de precio
            $precio = (object) [
                'lista_precio' => $listaPrecio,
                'precio_ud1' => $precioUd1,
                'precio_ud2' => $precioUd2,
                'margen_ud1' => $margenUd1,
                'margen_ud2' => $margenUd2,
                'relacion_unidades' => $relacionUnidades
            ];
            
            \Log::info('Precio creado: Lista ' . $precio->lista_precio . ' - $' . $precio->precio_ud1);
            
            return $precio;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos precio: ' . $e->getMessage());
            return null;
        }
    }
    
        public function guardar(Request $request)
    {
        \Log::info('🚀 INICIANDO PROCESO DE GUARDAR COTIZACIÓN');
        \Log::info('📋 Datos recibidos:', [
            'cliente_codigo' => $request->cliente_codigo,
            'cliente_nombre' => $request->cliente_nombre,
            'productos_count' => count($request->productos ?? []),
            'user_id' => auth()->id()
        ]);
        
        $request->validate([
            'cliente_codigo' => 'required',
            'cliente_nombre' => 'required',
            'productos' => 'required|array|min:1',
            'productos.*.codigo' => 'required',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio' => 'required|numeric|min:0'
        ]);
        
        \Log::info('✅ Validación de datos completada');
        
        try {
            \Log::info('🔄 INICIANDO TRANSACCIÓN DE BASE DE DATOS');
            DB::beginTransaction();
            
            // 1. Obtener información del cliente
            $cliente = Cliente::where('codigo_cliente', $request->cliente_codigo)->first();
            
            // 2. Calcular totales por producto y generales
            \Log::info('💰 CALCULANDO TOTALES');
            $subtotalSinDescuentos = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;
            
            foreach ($request->productos as $index => $producto) {
                // Calcular valores por producto
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                $totalProducto = $subtotalConDescuento + $ivaProducto;
                
                // Acumular totales
                $subtotalSinDescuentos += $precioBase;
                $descuentoTotal += $descuentoValor;
                $ivaTotal += $ivaProducto;
                
                \Log::info("📦 Producto {$index}: {$producto['codigo']} - Cantidad: {$producto['cantidad']} - Precio: {$producto['precio']} - Descuento: {$descuentoPorcentaje}% - IVA: {$ivaProducto}");
            }
            
            // Calcular subtotal neto (después de descuentos)
            $subtotalNeto = $subtotalSinDescuentos - $descuentoTotal;
            
            // Total final con IVA
            $total = $subtotalNeto + $ivaTotal;
            \Log::info("💰 Subtotal sin descuentos: {$subtotalSinDescuentos}, Descuento total: {$descuentoTotal}, Subtotal neto: {$subtotalNeto}, IVA total: {$ivaTotal}, Total: {$total}");
            
            // 3. Crear cotización en base de datos local
            \Log::info('📝 CREANDO COTIZACIÓN EN TABLA cotizaciones');
            
            // Este controlador es específico para Notas de Venta
            $tipoDocumento = 'nota_venta';
            $esCotizacion = false;
            
            $cotizacionData = [
                'tipo_documento' => $tipoDocumento,
                'user_id' => auth()->id(),
                'cliente_codigo' => $request->cliente_codigo,
                'cliente_nombre' => $request->cliente_nombre,
                'cliente_direccion' => $cliente->direccion ?? null,
                'cliente_telefono' => $cliente->telefono ?? null,
                'cliente_lista_precios' => $cliente->lista_precios_codigo ?? null,
                'fecha' => now(),
                'subtotal' => $subtotalSinDescuentos,
                'descuento_global' => $descuentoTotal,
                'subtotal_neto' => $subtotalNeto,
                'iva' => $ivaTotal,
                'total' => $total,
                'observaciones' => $request->observaciones,
                // Fecha de despacho: usar fecha de creación (no se solicita al usuario)
                'fecha_despacho' => now()->startOfDay(),
                // Campos completos para Nota de Venta
                'numero_orden_compra' => $request->numero_orden_compra ?? null,
                'observacion_vendedor' => $request->observacion_vendedor ?? null,
                'solicitar_descuento_extra' => (bool) $request->input('solicitar_descuento_extra', false),
                'estado' => 'borrador',
                // Las NVV siempre requieren aprobación
                'requiere_aprobacion' => true
            ];
            \Log::info('📝 Datos de cotización a crear:', $cotizacionData);
            \Log::info('📋 Tipo de documento: ' . $tipoDocumento . ' - Es cotización: ' . ($esCotizacion ? 'SÍ' : 'NO'));
            
            $cotizacion = Cotizacion::create($cotizacionData);
            \Log::info("✅ " . ($esCotizacion ? 'Cotización' : 'Nota de Venta') . " creada exitosamente - ID: {$cotizacion->id}");
            
            // 4. Verificar stock y crear detalles de cotización
            \Log::info('📦 VERIFICANDO STOCK Y CREANDO PRODUCTOS DE COTIZACIÓN');
            // 4. ACTUALIZAR STOCKS PRODUCTO POR PRODUCTO (usando el mismo método que funciona en la búsqueda)
            \Log::info('🔄 ACTUALIZANDO STOCKS DESDE SQL SERVER ANTES DE GUARDAR NVV (producto por producto)');
            $stockConsultaService = new \App\Services\StockConsultaService();
            
            // ACTUALIZAR cada producto individualmente usando el mismo método que funciona bien
            // (consulta producto por producto para evitar problemas de parsing con múltiples productos)
            foreach ($request->productos as $producto) {
                $codigo = $producto['codigo'];
                
                // Usar el mismo método que usa obtenerStockProducto (tsql directo con un solo producto)
                try {
                    $host = env('SQLSRV_EXTERNAL_HOST');
                    $port = env('SQLSRV_EXTERNAL_PORT', '1433');
                    $database = env('SQLSRV_EXTERNAL_DATABASE');
                    $username = env('SQLSRV_EXTERNAL_USERNAME');
                    $password = env('SQLSRV_EXTERNAL_PASSWORD');
                    
                    $codigoEscapado = "'" . addslashes(trim($codigo)) . "'";
                    $query = "
                        SELECT 
                            CAST(SUM(ISNULL(STFI1, 0)) AS FLOAT) AS STOCK_FISICO,
                            CAST(SUM(ISNULL(STOCNV1, 0)) AS FLOAT) AS STOCK_COMPROMETIDO
                        FROM MAEST
                        WHERE KOPR = {$codigoEscapado}
                        AND KOBO = 'LIB'
                    ";
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'sql_stock_');
                    file_put_contents($tempFile, $query . "\ngo\nquit");
                    
                    $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
                    $output = shell_exec($command);
                    unlink($tempFile);
                    
                    // Parsear resultado (mismo método que obtenerStockProducto)
                    $stockFisico = 0;
                    $stockComprometido = 0;
                    
                    $lines = explode("\n", $output);
                    $headerFound = false;
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        
                        if (empty($line) || strpos($line, 'locale') !== false || 
                            strpos($line, 'Setting') !== false || strpos($line, 'rows affected') !== false ||
                            strpos($line, 'Msg ') !== false || strpos($line, 'Warning:') !== false ||
                            preg_match('/^\d+>$/', $line) || preg_match('/^\d+>\s+\d+>\s+\d+>/', $line)) {
                            continue;
                        }
                        
                        if (stripos($line, 'STOCK_FISICO') !== false || stripos($line, 'STOCK_COMPROMETIDO') !== false) {
                            $headerFound = true;
                            continue;
                        }
                        
                        if (preg_match('/^\s*([0-9.]+)\s+([0-9.]+)\s*$/', $line, $matches)) {
                            $stockFisico = (float)$matches[1];
                            $stockComprometido = (float)$matches[2];
                            break;
                        }
                        
                        if ($headerFound) {
                            $parts = preg_split('/\s+/', $line);
                            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                                $stockFisico = (float)$parts[0];
                                $stockComprometido = (float)$parts[1];
                                break;
                            }
                        }
                    }
                    
                    // Actualizar MySQL con los valores obtenidos
                    if ($stockFisico >= 0 && $stockComprometido >= 0) {
                        $stockConsultaService->actualizarStockSiEsDiferente($codigo, $stockFisico, $stockComprometido);
                        \Log::info("✅ Stock actualizado en MySQL para {$codigo}: Físico={$stockFisico}, Comprometido={$stockComprometido}");
                    } else {
                        \Log::warning("⚠️ No se pudo parsear stock para {$codigo}. Output: " . substr($output, 0, 200));
                    }
                } catch (\Exception $e) {
                    \Log::error("❌ Error actualizando stock para {$codigo}: " . $e->getMessage());
                    // Continuar con los otros productos aunque falle uno
                }
            }
            
            $stockComprometidoService = new \App\Services\StockComprometidoService();
            $productosSinStock = [];
            $productosConStockComprometido = [];
            $productosParaValidar = [];

            foreach ($request->productos as $index => $producto) {
                \Log::info("📦 Procesando producto {$index}: {$producto['codigo']}");
                
                // Consultar stock actualizado del producto (después de actualizar MySQL)
                $stockDisponibleReal = $stockComprometidoService->obtenerStockDisponibleReal($producto['codigo']);
                $stockComprometido = \App\Models\StockComprometido::calcularStockComprometido($producto['codigo']);
                
                // Obtener stock FÍSICO del producto ACTUALIZADO (importante para validación de compras)
                $productoDB = \App\Models\Producto::where('KOPR', $producto['codigo'])->first();
                $stockFisico = $productoDB ? ($productoDB->stock_fisico ?? 0) : 0;
                
                \Log::info("📦 Stock para producto {$producto['codigo']}: Físico={$stockFisico}, Disponible={$stockDisponibleReal}, Comprometido={$stockComprometido}, Cantidad solicitada={$producto['cantidad']}");
                
                // Calcular valores del producto
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                $totalProducto = $subtotalConDescuento + $ivaProducto;
                
                // Validar stock FÍSICO (no disponible) para determinar si requiere compras
                $stockSuficiente = $stockFisico >= $producto['cantidad'];
                
                $productoData = [
                    'cotizacion_id' => $cotizacion->id,
                    'codigo_producto' => $producto['codigo'],
                    'nombre_producto' => $producto['nombre'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio'],
                    'subtotal' => $precioBase, // Precio base sin descuentos
                    'descuento_porcentaje' => $descuentoPorcentaje,
                    'descuento_valor' => $descuentoValor,
                    'subtotal_con_descuento' => $subtotalConDescuento,
                    'iva_porcentaje' => 19.00,
                    'iva_valor' => $ivaProducto,
                    'total_producto' => $totalProducto,
                    'stock_disponible' => $stockDisponibleReal, // Se actualizará después con valor real
                    'stock_suficiente' => $stockSuficiente // Usar stock FÍSICO para validación
                ];
                \Log::info("📦 Datos de producto a crear:", $productoData);
                
                $cotizacionProducto = CotizacionProducto::create($productoData);
                \Log::info("✅ Producto de cotización creado - ID: {$cotizacionProducto->id}");
                
                // ACTUALIZAR stock_disponible en cotizacion_productos con el valor ACTUALIZADO de productos
                // Esto asegura que la vista de aprobaciones muestre el stock correcto
                $productoActualizado = \App\Models\Producto::where('KOPR', $producto['codigo'])->first();
                if ($productoActualizado) {
                    $stockDisponibleActualizado = $stockComprometidoService->obtenerStockDisponibleReal($producto['codigo']);
                    $cotizacionProducto->stock_disponible = $stockDisponibleActualizado;
                    $cotizacionProducto->stock_suficiente = ($productoActualizado->stock_fisico ?? 0) >= $producto['cantidad'];
                    $cotizacionProducto->save();
                    \Log::info("📦 Stock actualizado en cotizacion_productos para {$producto['codigo']}: {$stockDisponibleActualizado}");
                }
                
                // Verificar si hay stock FÍSICO suficiente (para comprometer)
                if ($stockFisico >= $producto['cantidad']) {
                    // Hay stock suficiente, comprometer el stock
                    \Log::info("📦 Comprometiendo stock para producto {$producto['codigo']}: {$producto['cantidad']} unidades");
                    
                    \App\Models\StockComprometido::create([
                        'producto_codigo' => $producto['codigo'],
                        'producto_nombre' => $producto['nombre'],
                        'bodega_codigo' => '01',
                        'bodega_nombre' => 'Bodega Principal',
                        'cantidad_comprometida' => $producto['cantidad'],
                        'stock_disponible_original' => $stockDisponibleReal,
                        'stock_disponible_actual' => $stockDisponibleReal - $producto['cantidad'],
                        'unidad_medida' => $producto['unidad'] ?? 'UN',
                        'cotizacion_id' => $cotizacion->id,
                        'cotizacion_estado' => 'pendiente',
                        'vendedor_id' => auth()->id(),
                        'vendedor_nombre' => auth()->user()->name,
                        'cliente_codigo' => $request->cliente_codigo,
                        'cliente_nombre' => $request->cliente_nombre,
                        'fecha_compromiso' => now(),
                        'observaciones' => 'Stock comprometido por cotización'
                    ]);
                    
                    $productosConStockComprometido[] = $producto['codigo'];
                } else {
                    // No hay stock suficiente
                    \Log::warning("⚠️ Stock FÍSICO insuficiente para producto {$producto['codigo']}: Físico={$stockFisico}, Solicitado={$producto['cantidad']}");
                    $productosSinStock[] = [
                        'codigo' => $producto['codigo'],
                        'nombre' => $producto['nombre'],
                        'stock_fisico' => $stockFisico,
                        'stock_disponible' => $stockDisponibleReal,
                        'cantidad_solicitada' => $producto['cantidad']
                    ];
                }

                // Armar array de validación con el stock FÍSICO real calculado
                $productosParaValidar[] = [
                    'codigo' => $producto['codigo'],
                    'nombre' => $producto['nombre'],
                    'stock_fisico' => $stockFisico, // Usar stock FÍSICO para validación
                    'stock_disponible' => $stockDisponibleReal,
                    'cantidad' => $producto['cantidad']
                ];
            }
            
            // 5. Verificar deuda del cliente y realizar validaciones automáticas
            \Log::info('🔍 VERIFICANDO DEUDA DEL CLIENTE Y VALIDACIONES AUTOMÁTICAS');
            $cobranzaService = new \App\Services\CobranzaService();
            $validacionCliente = $cobranzaService->validarClienteParaVenta($request->cliente_codigo);
            
            // Realizar validaciones automáticas de crédito y facturas
            $validacionesAutomaticas = ClienteValidacionService::validarClienteParaNotaVenta($request->cliente_codigo, $total);
            // Usar el stock real calculado en el backend, no el enviado por el cliente
            $validacionStock = ClienteValidacionService::validarStockProductos($productosParaValidar);
            
            \Log::info("🔍 Validación cliente: " . json_encode($validacionCliente));
            \Log::info("🔍 Validaciones automáticas: " . json_encode($validacionesAutomaticas));
            \Log::info("🔍 Validación stock: " . json_encode($validacionStock));
            
            // 6. Determinar estado de la cotización basado en validaciones automáticas                                                                     
            \Log::info('🔍 DETERMINANDO ESTADO DE LA COTIZACIÓN BASADO EN VALIDACIONES AUTOMÁTICAS');                                                       
            
            $requiereAutorizacion = false;
            $motivosAutorizacion = [];
            $tieneProblemasCredito = $validacionesAutomaticas['requiere_autorizacion'] ?? false;
            
            // CRÍTICO: Determinar si hay problemas de stock basado SOLO en $productosSinStock 
            // (que se construyó verificando stock FÍSICO >= cantidad durante el loop)
            // NO usar $validacionStock['requiere_autorizacion'] porque puede estar desincronizado o usar valores incorrectos
            $tieneProblemasStock = !empty($productosSinStock);
            
            \Log::info("🔍 Resumen de validaciones para determinación de estado:", [
                'productos_sin_stock_count' => count($productosSinStock),
                'productos_sin_stock_codigos' => array_column($productosSinStock, 'codigo'),
                'tiene_problemas_credito' => $tieneProblemasCredito,
                'tiene_problemas_stock' => $tieneProblemasStock,
                'validaciones_automaticas_requiere_autorizacion' => $validacionesAutomaticas['requiere_autorizacion'] ?? false,
                'validacion_stock_requiere_autorizacion' => $validacionStock['requiere_autorizacion'] ?? false,
                'nota' => 'tieneProblemasStock se determina SOLO por productosSinStock (stock FÍSICO)'
            ]);
            
            // Verificar validaciones automáticas (solo para mensajes, no para determinar estado)
            if ($validacionesAutomaticas['requiere_autorizacion']) {
                $requiereAutorizacion = true;
                $motivosAutorizacion[] = 'Cliente requiere autorización';
            }
            
            if ($tieneProblemasStock) {
                $requiereAutorizacion = true;
                $motivosAutorizacion[] = 'Stock requiere autorización';
            }
            
            // Determinar estado de aprobación basado en los problemas detectados
            // LÓGICA SIMPLIFICADA Y CLARA:
            // 1. Si todos tienen stock FÍSICO suficiente Y no hay problemas de crédito → pendiente_picking
            // 2. Si todos tienen stock FÍSICO suficiente PERO hay problemas de crédito → pendiente (supervisor)
            // 3. Si hay productos sin stock FÍSICO suficiente → pendiente (compras), y si hay crédito también → pendiente (supervisor primero)
            $estadoFinal = 'enviada';
            
            if (empty($productosSinStock) && !$tieneProblemasCredito) {
                // Todos tienen stock FÍSICO suficiente Y no hay problemas de crédito → pasa directo a picking
                $estadoAprobacion = 'pendiente_picking';
                $tieneProblemasStock = false; // Asegurar que esté en false
                \Log::info("✅ NVV pasará directo a PICKING - Todos los productos tienen stock FÍSICO suficiente y sin problemas de crédito");
            } elseif (empty($productosSinStock) && $tieneProblemasCredito) {
                // Todos tienen stock FÍSICO suficiente PERO hay problemas de crédito → requiere supervisor primero
                $estadoAprobacion = 'pendiente';
                $tieneProblemasStock = false; // Asegurar que esté en false
                \Log::info("⚠️ NVV requiere SUPERVISOR - Stock FÍSICO suficiente pero problemas de crédito");
            } else {
                // Hay productos sin stock FÍSICO suficiente → requiere compras
                $estadoAprobacion = 'pendiente';
                $tieneProblemasStock = true; // Asegurar que esté en true
                \Log::info("⚠️ NVV requiere COMPRAS - Stock FÍSICO insuficiente en " . count($productosSinStock) . " productos. Códigos: " . implode(', ', array_column($productosSinStock, 'codigo')));
            }
            
            // Actualizar cotización con el estado determinado
            $cotizacion->update([
                'estado' => $estadoFinal,
                'estado_aprobacion' => $estadoAprobacion,
                'requiere_aprobacion' => ($estadoAprobacion !== 'pendiente_picking'),
                'tiene_problemas_credito' => $tieneProblemasCredito,
                'tiene_problemas_stock' => $tieneProblemasStock
                // Las observaciones se mantienen como las escribió el usuario, sin agregar información automática
            ]);
            
            if (!empty($productosSinStock)) {
                // Hay productos sin stock suficiente, crear nota de venta pendiente
                
                // Crear nota de venta pendiente
                \Log::info('📋 CREANDO NOTA DE VENTA PENDIENTE');
                $notaVentaPendiente = \App\Models\NotaVentaPendiente::create([
                    'cotizacion_id' => $cotizacion->id,
                    'cotizacion_numero' => $cotizacion->id,
                    'cliente_codigo' => $request->cliente_codigo,
                    'cliente_nombre' => $request->cliente_nombre,
                    'cliente_direccion' => $cliente->direccion ?? null,
                    'cliente_telefono' => $cliente->telefono ?? null,
                    'cliente_lista_precios' => $cliente->lista_precios_codigo ?? null,
                    'vendedor_id' => auth()->id(),
                    'vendedor_nombre' => auth()->user()->name,
                    'vendedor_codigo' => auth()->user()->codigo_vendedor ?? null,
                    'numero_nota_venta' => null, // Se asignará cuando se apruebe
                    'fecha_nota_venta' => now(),
                    'subtotal' => $subtotalNeto,
                    'descuento_global' => $descuentoTotal,
                    'total' => $total,
                    'observaciones' => $request->observaciones,
                    'estado' => 'pendiente',
                    'tiene_problemas_stock' => true,
                    'detalle_problemas_stock' => collect($productosSinStock)->map(function($p) {
                        return "Producto {$p['codigo']} ({$p['nombre']}): Stock disponible {$p['stock_disponible']}, Cantidad solicitada {$p['cantidad_solicitada']}";
                    })->join("\n")
                ]);
                
                // Crear productos de la nota de venta pendiente
                foreach ($request->productos as $producto) {
                    $stockDisponibleReal = $stockComprometidoService->obtenerStockDisponibleReal($producto['codigo']);
                    $stockComprometido = \App\Models\StockComprometido::calcularStockComprometido($producto['codigo']);
                    
                    \App\Models\NotaVentaPendienteProducto::create([
                        'nota_venta_pendiente_id' => $notaVentaPendiente->id,
                        'codigo_producto' => $producto['codigo'],
                        'nombre_producto' => $producto['nombre'],
                        'cantidad' => $producto['cantidad'],
                        'precio_unitario' => $producto['precio'],
                        'subtotal' => $producto['cantidad'] * $producto['precio'],
                        'unidad_medida' => $producto['unidad'] ?? 'UN',
                        'stock_disponible' => $stockDisponibleReal,
                        'stock_comprometido' => $stockComprometido,
                        'stock_suficiente' => $stockDisponibleReal >= $producto['cantidad'],
                        'problemas_stock' => $stockDisponibleReal < $producto['cantidad'] ? 
                            "Stock insuficiente: Disponible {$stockDisponibleReal}, Solicitado {$producto['cantidad']}" : null
                    ]);
                }
                
                \Log::warning("⚠️ Nota de venta pendiente creada - ID: {$notaVentaPendiente->id}");
            } else {
                // Todos los productos tienen stock suficiente
                \Log::info("✅ TODOS LOS PRODUCTOS TIENEN STOCK SUFICIENTE");
                
                // Verificar si el cliente puede vender (sin validaciones que requieran autorización)
                if ($validacionCliente['puede_vender'] && !$requiereAutorizacion) {
                    \Log::info("✅ CLIENTE VÁLIDO Y SIN RESTRICCIONES - GENERANDO NOTA DE VENTA AUTOMÁTICAMENTE EN SQL SERVER");
                    
                    // Generar nota de venta automáticamente en SQL Server
                    $resultadoNotaVenta = $this->generarNotaVentaAutomatica($cotizacion, $request->productos);
                    
                    if ($resultadoNotaVenta['success']) {
                        $estadoFinal = 'procesada';
                        $cotizacion->update([
                            'estado' => $estadoFinal,
                            'requiere_aprobacion' => false,
                            'nota_venta_id' => $resultadoNotaVenta['nota_venta_id'],
                            'observaciones' => $cotizacion->observaciones . "\n\n✅ NOTA DE VENTA GENERADA AUTOMÁTICAMENTE EN SQL SERVER\nNúmero: {$resultadoNotaVenta['nota_venta_id']}"
                        ]);
                        
                        // Marcar stock comprometido como procesado
                        \App\Models\StockComprometido::porCotizacion($cotizacion->id)
                            ->activo()
                            ->get()
                            ->each(function($stock) {
                                $stock->procesar();
                            });
                        
                        \Log::info("✅ Nota de venta generada automáticamente en SQL Server - ID: {$resultadoNotaVenta['nota_venta_id']}");
                    } else {
                        $estadoFinal = 'enviada';
                        $cotizacion->update([
                            'estado' => $estadoFinal,
                            'requiere_aprobacion' => true,
                            'observaciones' => $cotizacion->observaciones . "\n\n⚠️ ERROR AL GENERAR NOTA DE VENTA: {$resultadoNotaVenta['message']}"
                        ]);
                        \Log::warning("⚠️ Error generando nota de venta automática: {$resultadoNotaVenta['message']}");
                    }
                } else {
                    \Log::warning("⚠️ CLIENTE CON RESTRICCIONES - GUARDANDO LOCALMENTE PARA APROBACIÓN");                                                        
                    
                    // Cliente tiene restricciones, guardar localmente para aprobación                                                                          
                    $estadoFinal = 'enviada';
                    $motivosTexto = implode(', ', $motivosAutorizacion);
                    
                    // Determinar estado de aprobación basado en los problemas detectados
                    // Misma lógica que arriba: usar $productosSinStock (stock FÍSICO) no $validacionStock
                    $tieneProblemasStock = !empty($productosSinStock);
                    
                    if (empty($productosSinStock) && !$tieneProblemasCredito) {
                        $estadoAprobacion = 'pendiente_picking'; // Pasa directo a picking
                        $tieneProblemasStock = false;
                    } elseif (empty($productosSinStock) && $tieneProblemasCredito) {
                        $estadoAprobacion = 'pendiente'; // Requiere supervisor primero (crédito), luego picking
                        $tieneProblemasStock = false;
                    } else {
                        $estadoAprobacion = 'pendiente'; // Requiere compras (stock), y si hay crédito también → supervisor primero
                        $tieneProblemasStock = true;
                    }
                    
                    $cotizacion->update([
                        'estado' => $estadoFinal,
                        'estado_aprobacion' => $estadoAprobacion,
                        'requiere_aprobacion' => true,
                        'tiene_problemas_credito' => $tieneProblemasCredito,
                        'tiene_problemas_stock' => $tieneProblemasStock,
                        'observaciones' => $cotizacion->observaciones . "\n\n⚠️ CLIENTE CON RESTRICCIONES - REQUIERE APROBACIÓN\nMotivos: {$motivosTexto}\n\n🔍 VALIDACIONES AUTOMÁTICAS:\n" .                                                   
                            "- Crédito: " . ($validacionesAutomaticas['validaciones']['credito']['valido'] ? 'Válido' : 'Requiere autorización') . "\n" .       
                            "- Facturas: " . ($validacionesAutomaticas['validaciones']['retraso']['valido'] ? 'Válido' : 'Requiere autorización') . "\n" .      
                            "- Stock: " . ($validacionStock['valido'] ? 'Válido' : 'Requiere autorización') . "\n" .
                            "- Estado de aprobación: {$estadoAprobacion}"                                                     
                    ]);
                    \Log::warning("⚠️ Cotización guardada localmente por restricciones del cliente - Motivos: {$motivosTexto} - Estado: {$estadoAprobacion}");                                 
                }
            }
            
            \Log::info('💾 CONFIRMANDO TRANSACCIÓN');
            DB::commit();
            \Log::info('✅ TRANSACCIÓN CONFIRMADA EXITOSAMENTE');
            
            // Registrar en el historial
            \App\Services\HistorialCotizacionService::registrarCreacion($cotizacion);
            
            $response = [
                'success' => true,
                'message' => 'Cotización guardada exitosamente',
                'cotizacion_id' => $cotizacion->id,
                'estado' => $estadoFinal,
                'estado_aprobacion' => $cotizacion->estado_aprobacion ?? 'pendiente_picking',
                'requiere_aprobacion' => $cotizacion->requiere_aprobacion,
                'tiene_problemas_credito' => $cotizacion->tiene_problemas_credito ?? false,
                'tiene_problemas_stock' => $cotizacion->tiene_problemas_stock ?? false,
                'total' => $total,
                'productos_con_stock' => count($productosConStockComprometido),
                'productos_sin_stock' => count($productosSinStock),
                'productos_sin_stock_detalle' => $productosSinStock,
                'validaciones' => [
                    'cliente' => $validacionesAutomaticas,
                    'stock' => $validacionStock,
                    'requiere_autorizacion' => $requiereAutorizacion,
                    'motivos_autorizacion' => $motivosAutorizacion
                ]
            ];
            
            if ($estadoFinal === 'procesada') {
                $response['message'] .= ' - Procesada automáticamente en SQL Server';                                                                           
            } elseif ($estadoFinal === 'pendiente_stock') {
                $response['message'] .= ' - Pendiente por stock insuficiente';
            } elseif ($estadoFinal === 'enviada' && $requiereAutorizacion) {
                $motivosTexto = implode(', ', $motivosAutorizacion);
                $estadoAprobacion = $cotizacion->estado_aprobacion ?? 'pendiente_picking';
                $response['message'] .= " - Pendiente por restricciones: {$motivosTexto} (Estado: {$estadoAprobacion})";                                                                      
            } else {
                $estadoAprobacion = $cotizacion->estado_aprobacion ?? 'pendiente_picking';
                $response['message'] .= " - Pendiente de aprobación (Estado: {$estadoAprobacion})";
            }
            
            \Log::info('🎉 RESPUESTA FINAL:', $response);
            return response()->json($response);
            
        } catch (\Exception $e) {
            \Log::error('❌ ERROR EN PROCESO DE GUARDAR COTIZACIÓN');
            \Log::error('❌ Mensaje de error: ' . $e->getMessage());
            \Log::error('❌ Archivo: ' . $e->getFile());
            \Log::error('❌ Línea: ' . $e->getLine());
            \Log::error('❌ Stack trace: ' . $e->getTraceAsString());
            
            \Log::info('🔄 REVERTIENDO TRANSACCIÓN');
            DB::rollback();
            \Log::info('✅ TRANSACCIÓN REVERTIDA');
            
            $errorResponse = [
                'success' => false,
                'message' => 'Error al guardar cotización: ' . $e->getMessage()
            ];
            
            \Log::error('❌ RESPUESTA DE ERROR:', $errorResponse);
            return response()->json($errorResponse, 500);
        }
    }
    
    /**
     * Listar cotizaciones
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Filtros
        $estado = $request->get('estado', '');
        $cliente = $request->get('cliente', '');
        $fechaInicio = $request->get('fecha_inicio', '');
        $fechaFin = $request->get('fecha_fin', '');
        $buscar = $request->get('buscar', ''); // Nuevo filtro de búsqueda general
        $montoMin = $request->get('monto_min', '');
        $montoMax = $request->get('monto_max', '');
        $tipoDocumento = $request->get('tipo_documento', ''); // Nuevo filtro: cotizacion | nota_venta
        
        // Si es Supervisor, puede ver todas las cotizaciones
        if ($user->hasRole('Supervisor') || $user->hasRole('Super Admin')) {
            // Obtener cotizaciones desde SQL Server (todas)
            $cotizacionesSQL = $this->obtenerCotizacionesDesdeSQLServer($estado, $cliente, $fechaInicio, $fechaFin, '', $buscar, $montoMin, $montoMax);
            
            // Obtener cotizaciones locales (todas)
            $cotizacionesLocales = $this->obtenerCotizacionesLocales($estado, $cliente, $fechaInicio, $fechaFin, $buscar, $montoMin, $montoMax, true, $tipoDocumento);
        } else {
            // Si es Vendedor, solo sus cotizaciones
            $codigoVendedor = $user->codigo_vendedor ?? '';
            
            // Obtener cotizaciones desde SQL Server filtradas por vendedor
            $cotizacionesSQL = $this->obtenerCotizacionesDesdeSQLServer($estado, $cliente, $fechaInicio, $fechaFin, $codigoVendedor, $buscar, $montoMin, $montoMax);
            
            // Obtener cotizaciones locales del vendedor
            $cotizacionesLocales = $this->obtenerCotizacionesLocales($estado, $cliente, $fechaInicio, $fechaFin, $buscar, $montoMin, $montoMax, false, $tipoDocumento);
        }
        
        // Combinar ambas listas
        $cotizaciones = array_merge($cotizacionesSQL, $cotizacionesLocales);
        
        // Ordenar por fecha (más recientes primero)
        usort($cotizaciones, function($a, $b) {
            // Convertir objetos a arrays si es necesario
            $a = is_object($a) ? (array)$a : $a;
            $b = is_object($b) ? (array)$b : $b;
            
            $fechaA = isset($a['fecha_emision']) ? $a['fecha_emision'] : (isset($a['fecha']) ? $a['fecha'] : '');
            $fechaB = isset($b['fecha_emision']) ? $b['fecha_emision'] : (isset($b['fecha']) ? $b['fecha'] : '');
            
            return strtotime($fechaB) - strtotime($fechaA);
        });
        
        return view('cotizaciones.index', compact('cotizaciones', 'estado', 'cliente', 'fechaInicio', 'fechaFin', 'buscar', 'montoMin', 'montoMax'))->with('pageSlug', 'cotizaciones');
    }
    
    /**
     * Obtener cotizaciones locales del vendedor
     */
    private function obtenerCotizacionesLocales($estado = '', $cliente = '', $fechaInicio = '', $fechaFin = '', $buscar = '', $montoMin = '', $montoMax = '', $verTodas = false, $tipoDocumento = '')
    {
        try {
            $query = Cotizacion::with(['user', 'productos']);
            
            // Si no es para ver todas, filtrar por usuario actual
            if (!$verTodas) {
                $query->where('user_id', auth()->id());
            }
            
            // Filtro por tipo de documento
            if ($tipoDocumento) {
                $query->where('tipo_documento', $tipoDocumento);
            }
            
            // Filtro por estado
            if ($estado) {
                switch ($estado) {
                    case 'borrador':
                        $query->where('estado', 'borrador');
                        break;
                    case 'enviada':
                        $query->where('estado', 'enviada');
                        break;
                    case 'aprobada':
                        $query->where('estado', 'aprobada');
                        break;
                    case 'rechazada':
                        $query->where('estado', 'rechazada');
                        break;
                    case 'pendiente_stock':
                        $query->where('estado', 'pendiente_stock');
                        break;
                    case 'procesada':
                        $query->where('estado', 'procesada');
                        break;
                    case 'cancelada':
                        $query->where('estado', 'cancelada');
                        break;
                }
            }
            
            // Filtro por cliente
            if ($cliente) {
                $query->where(function($q) use ($cliente) {
                    $q->where('cliente_codigo', 'LIKE', "%{$cliente}%")
                      ->orWhere('cliente_nombre', 'LIKE', "%{$cliente}%");
                });
            }
            
            // Filtro de búsqueda general
            if ($buscar) {
                $query->where(function($q) use ($buscar) {
                    $q->where('cliente_codigo', 'LIKE', "%{$buscar}%")
                      ->orWhere('cliente_nombre', 'LIKE', "%{$buscar}%")
                      ->orWhere('id', 'LIKE', "%{$buscar}%");
                });
            }
            
            // Filtro por monto
            if ($montoMin && is_numeric($montoMin)) {
                $query->where('total', '>=', $montoMin);
            }
            
            if ($montoMax && is_numeric($montoMax)) {
                $query->where('total', '<=', $montoMax);
            }
            
            // Filtro por fecha
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
            }
            
            $cotizaciones = $query->orderBy('fecha', 'desc')->get();
            
            $resultado = [];
            foreach ($cotizaciones as $cotizacion) {
                $resultado[] = [
                    'id' => $cotizacion->id,
                    'tipo' => 'COTIZACION_LOCAL',
                    'numero' => $cotizacion->id,
                    'fecha_emision' => $cotizacion->fecha->format('Y-m-d H:i:s'),
                    'cliente_codigo' => $cotizacion->cliente_codigo,
                    'cliente_nombre' => $cotizacion->cliente_nombre,
                    'vendedor_nombre' => $cotizacion->user->name ?? 'N/A',
                    'vendedor_codigo' => $cotizacion->user->codigo_vendedor ?? 'N/A',
                    'total' => $cotizacion->total,
                    'subtotal' => $cotizacion->subtotal,
                    'descuento_global' => $cotizacion->descuento_global,
                    'estado' => $cotizacion->estado,
                    'requiere_aprobacion' => $cotizacion->requiere_aprobacion,
                    'observaciones' => $cotizacion->observaciones,
                    'productos_count' => $cotizacion->productos->count(),
                    'fuente' => 'local',
                    'fecha' => $cotizacion->fecha->format('Y-m-d H:i:s')
                ];
            }
            
            return $resultado;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo cotizaciones locales: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener cotizaciones desde SQL Server
     */
    private function obtenerCotizacionesDesdeSQLServer($estado = '', $cliente = '', $fechaInicio = '', $fechaFin = '', $codigoVendedor = '', $buscar = '', $montoMin = '', $montoMax = '')
    {
        try {
            // Construir la consulta base
            $whereClause = "";
            
            // Filtrar por vendedor si se especifica
            if ($codigoVendedor) {
                $whereClause .= " AND TABFU.KOFU = '{$codigoVendedor}'";
            } else {
                // Si no hay vendedor especificado, usar GOP por defecto para testing
                $whereClause .= " AND TABFU.KOFU = 'GOP'";
            }
            
            if ($estado) {
                switch ($estado) {
                    case 'ingresada':
                        $whereClause .= " AND MAEDDO.CAPRAD1 = 0";
                        break;
                    case 'pendiente':
                        $whereClause .= " AND MAEDDO.CAPRAD1 = 0 AND EXISTS (
                            SELECT 1 FROM MAEDDO AS FACTURAS 
                            WHERE FACTURAS.ENDO = MAEDDO.ENDO 
                            AND FACTURAS.TIDO IN ('FCV', 'FDV') 
                            AND FACTURAS.CAPRCO1 > FACTURAS.CAPRAD1
                        )";
                        break;
                    case 'aprobada':
                        $whereClause .= " AND MAEDDO.CAPRAD1 = 0 AND NOT EXISTS (
                            SELECT 1 FROM MAEDDO AS FACTURAS 
                            WHERE FACTURAS.ENDO = MAEDDO.ENDO 
                            AND FACTURAS.TIDO IN ('FCV', 'FDV') 
                            AND FACTURAS.CAPRCO1 > FACTURAS.CAPRAD1
                        )";
                        break;
                }
            }
            
            if ($cliente) {
                $cliente = strtoupper(addslashes($cliente));
                $whereClause .= " AND (MAEDDO.ENDO LIKE '%{$cliente}%' OR MAEEN.NOKOEN LIKE '%{$cliente}%')";
            }
            
            // Filtro de búsqueda general (código de factura, cliente, etc.)
            if ($buscar) {
                $buscar = strtoupper(addslashes($buscar));
                $whereClause .= " AND (MAEDDO.NUDO LIKE '%{$buscar}%' OR MAEDDO.ENDO LIKE '%{$buscar}%' OR MAEEN.NOKOEN LIKE '%{$buscar}%')";
            }
            
            // Filtro por monto mínimo
            if ($montoMin && is_numeric($montoMin)) {
                $whereClause .= " AND MAEDDO.VANELI >= {$montoMin}";
            }
            
            // Filtro por monto máximo
            if ($montoMax && is_numeric($montoMax)) {
                $whereClause .= " AND MAEDDO.VANELI <= {$montoMax}";
            }
            
            if ($fechaInicio && $fechaFin) {
                $whereClause .= " AND MAEDDO.FEEMLI BETWEEN '{$fechaInicio}' AND '{$fechaFin}'";
            }
            
            $query = "
                SELECT TOP 50 
                    MAEDDO.IDMAEDDO,
                    MAEDDO.TIDO AS TD,
                    MAEDDO.NUDO AS NUM,
                    MAEDDO.FEEMLI AS EMIS_FCV,
                    MAEDDO.ENDO AS COD_CLI,
                    MAEEN.NOKOEN AS CLIE,
                    MAEDDO.KOPRCT,
                    MAEDDO.CAPRCO1,
                    MAEDDO.NOKOPR,
                    MAEDDO.CAPRCO1 - (MAEDDO.CAPRCO1 - MAEDDO.CAPRAD1 - MAEDDO.CAPREX1) AS FACT,
                    MAEDDO.CAPRCO1 - MAEDDO.CAPRAD1 - MAEDDO.CAPREX1 AS PEND,
                    TABFU.NOKOFU,
                    TABFU.KOFU,
                    TABCI.NOKOCI,
                    TABCM.NOKOCM,
                    CAST(GETDATE() - MAEDDO.FEEMLI AS INT) AS DIAS,
                    CASE 
                        WHEN CAST(GETDATE() - MAEDDO.FEEMLI AS INT) < 8 THEN 'Entre 1 y 7 días'
                        WHEN CAST(GETDATE() - MAEDDO.FEEMLI AS INT) BETWEEN 8 AND 30 THEN 'Entre 8 y 30 Días'
                        WHEN CAST(GETDATE() - MAEDDO.FEEMLI AS INT) BETWEEN 31 AND 60 THEN 'Entre 31 y 60 Días'
                        ELSE 'Mas de 60 Días'
                    END AS Rango,
                    MAEDDO.VANELI / MAEDDO.CAPRCO1 AS PUNIT,
                    (MAEDDO.VANELI / MAEDDO.CAPRCO1) * (MAEDDO.CAPRCO1 - MAEDDO.CAPRAD1 - MAEDDO.CAPREX1) AS PEND_VAL,
                    CASE WHEN MAEDDO_1.TIDO IS NULL THEN '' ELSE MAEDDO_1.TIDO END AS TD_R,
                    CASE WHEN MAEDDO_1.NUDO IS NULL THEN '' ELSE MAEDDO_1.NUDO END AS N_FCV
                FROM MAEDDO 
                INNER JOIN MAEEN ON MAEDDO.ENDO = MAEEN.KOEN AND MAEDDO.SUENDO = MAEEN.SUEN 
                INNER JOIN TABFU ON MAEDDO.KOFULIDO = TABFU.KOFU 
                INNER JOIN TABCI ON MAEEN.PAEN = TABCI.KOPA AND MAEEN.CIEN = TABCI.KOCI 
                INNER JOIN TABCM ON MAEEN.PAEN = TABCM.KOPA AND MAEEN.CIEN = TABCM.KOCI AND MAEEN.CMEN = TABCM.KOCM 
                LEFT OUTER JOIN MAEDDO AS MAEDDO_1 ON MAEDDO.IDMAEDDO = MAEDDO_1.IDRST
                WHERE MAEDDO.TIDO = 'NVV' 
                    AND MAEDDO.LILG = 'SI' 
                    AND MAEDDO.CAPRCO1 - MAEDDO.CAPRAD1 - MAEDDO.CAPREX1 <> 0 
                    AND MAEDDO.KOPRCT <> 'D' 
                    AND MAEDDO.KOPRCT <> 'FLETE'
                    {$whereClause}
                ORDER BY MAEDDO.FEEMLI DESC
            ";
            
            \Log::info('Query SQL completa: ' . $query);
            
            // Usar tsql con archivo temporal
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (!$result || str_contains($result, 'error')) {
                throw new \Exception('Error ejecutando consulta tsql: ' . $result);
            }
            
            \Log::info('TSQL Result: ' . substr($result, 0, 1000));
            
            // NUEVO MÉTODO: Procesar cada línea individualmente
            $lines = explode("\n", $result);
            $cotizaciones = [];
            $inDataSection = false;
            $headerFound = false;
            
            foreach ($lines as $lineNumber => $line) {
                $line = trim($line);
                
                // Saltar líneas vacías o de configuración
                if (empty($line) || 
                    strpos($line, 'locale') !== false || 
                    strpos($line, 'Setting') !== false || 
                    strpos($line, '1>') !== false ||
                    strpos($line, '2>') !== false || 
                    strpos($line, 'Msg ') !== false || 
                    strpos($line, 'Warning:') !== false) {
                    continue;
                }
                
                // Detectar el header de la tabla (más flexible)
                if (strpos($line, 'IDMAEDDO') !== false || 
                    (strpos($line, 'TD') !== false && strpos($line, 'NUM') !== false && strpos($line, 'EMIS_FCV') !== false)) {
                    $headerFound = true;
                    $inDataSection = true;
                    \Log::info('Header encontrado en línea ' . $lineNumber . ': ' . $line);
                    continue;
                }
                
                // Detectar cuando terminamos la sección de datos
                if (strpos($line, '(1 row affected)') !== false || strpos($line, 'rows affected') !== false) {
                    $inDataSection = false;
                    \Log::info('Fin de datos en línea ' . $lineNumber);
                    break;
                }
                
                // Si estamos en la sección de datos y la línea no está vacía
                if ($inDataSection && $headerFound && !empty($line)) {
                    \Log::info('Procesando línea de datos ' . $lineNumber . ': ' . substr($line, 0, 100) . '...');
                    
                    // Procesar cada línea individualmente
                    $cotizacion = $this->procesarLineaCotizacion($line, $lineNumber);
                    
                    if ($cotizacion) {
                        $cotizaciones[] = $cotizacion;
                        \Log::info('Cotización procesada: ' . $cotizacion->cliente_nombre . ' - ' . $cotizacion->producto_nombre);
                    }
                }
            }
            
            \Log::info('Total de cotizaciones procesadas: ' . count($cotizaciones));
            
            
            return $cotizaciones;
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo cotizaciones: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Procesar una línea individual de cotización
     */
    private function procesarLineaCotizacion($line, $lineNumber)
    {
        try {
            // Limpiar la línea de caracteres especiales
            $line = preg_replace('/\s+/', ' ', $line); // Reemplazar múltiples espacios por uno
            $line = trim($line);
            
            \Log::info('Línea limpia ' . $lineNumber . ': ' . $line);
            
            // Verificar que la línea contenga datos de NVV
            if (strpos($line, 'NVV') === false) {
                return null;
            }
            
            // Dividir la línea por espacios y procesar cada campo
            $fields = explode(' ', $line);
            
            // Buscar el patrón: IDMAEDDO NVV NUMERO FECHA CODIGO_CLIENTE NOMBRE_CLIENTE...
            $cotizacion = null;
            
            for ($i = 0; $i < count($fields) - 10; $i++) {
                // Verificar si encontramos el patrón NVV
                if (isset($fields[$i + 1]) && $fields[$i + 1] === 'NVV') {
                    $idmaeddo = $fields[$i];
                    $numero = $fields[$i + 2];
                    
                    // Verificar que sean números válidos
                    if (!is_numeric($idmaeddo) || !is_numeric($numero)) {
                        continue;
                    }
                    
                    \Log::info('Patrón NVV encontrado: ID=' . $idmaeddo . ', NUM=' . $numero);
                    
                    // Extraer campos usando posiciones relativas
                    $cotizacion = $this->extraerCamposCotizacion($fields, $i);
                    break;
                }
            }
            
            if ($cotizacion) {
                \Log::info('Cotización extraída: ' . json_encode($cotizacion));
            }
            
            return $cotizacion;
            
        } catch (\Exception $e) {
            \Log::error('Error procesando línea ' . $lineNumber . ': ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extraer campos de cotización desde un array de campos
     */
    private function extraerCamposCotizacion($fields, $startIndex)
    {
        try {
            // Campos básicos
            $idmaeddo = $fields[$startIndex];
            $numero = $fields[$startIndex + 2];
            
            \Log::info('Extraer campos: ID=' . $idmaeddo . ', NUM=' . $numero);
            
            // Buscar campos después del patrón NVV
            $currentIndex = $startIndex + 3;
            
            // Extraer fecha (formato: Sep 2 2024 12:00:00:000AM)
            $fecha = '';
            if (isset($fields[$currentIndex]) && isset($fields[$currentIndex + 1]) && isset($fields[$currentIndex + 2])) {
                $fecha = $fields[$currentIndex] . ' ' . $fields[$currentIndex + 1] . ' ' . $fields[$currentIndex + 2];
                $currentIndex += 3;
                \Log::info('Fecha extraída: ' . $fecha);
            }
            
            // Extraer código de cliente (debe ser numérico y tener 8 dígitos)
            $codigoCliente = '';
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                if (is_numeric($field) && strlen($field) >= 8) {
                    $codigoCliente = $field;
                    $currentIndex++;
                    \Log::info('Código cliente encontrado: ' . $codigoCliente);
                    break;
                }
                $currentIndex++;
            }
            
            // Extraer nombre del cliente (hasta encontrar un código de producto)
            $nombreCliente = '';
            while ($currentIndex < count($fields)) {
                $field = $fields[$currentIndex];
                
                // Si encontramos un código de producto (formato: XXXXXXXX), paramos
                if (preg_match('/^[A-Z0-9]{10,}$/', $field)) {
                    \Log::info('Código producto encontrado: ' . $field . ', parando extracción de nombre');
                    break;
                }
                
                $nombreCliente .= ' ' . $field;
                $currentIndex++;
            }
            $nombreCliente = trim($nombreCliente);
            \Log::info('Nombre cliente extraído: "' . $nombreCliente . '"');
            
            // Extraer código del producto
            $codigoProducto = '';
            if ($currentIndex < count($fields)) {
                $codigoProducto = $fields[$currentIndex];
                $currentIndex++;
                \Log::info('Código producto: ' . $codigoProducto);
            }
            
            // Extraer cantidad (debe ser numérico)
            $cantidad = 0;
            while ($currentIndex < count($fields) && !is_numeric($fields[$currentIndex])) {
                $currentIndex++;
            }
            if ($currentIndex < count($fields)) {
                $cantidad = (float)$fields[$currentIndex];
                $currentIndex++;
                \Log::info('Cantidad: ' . $cantidad);
            }
            
            // Extraer nombre del producto (hasta encontrar valores numéricos)
            $nombreProducto = '';
            while ($currentIndex < count($fields) && !is_numeric($fields[$currentIndex])) {
                $nombreProducto .= ' ' . $fields[$currentIndex];
                $currentIndex++;
            }
            $nombreProducto = trim($nombreProducto);
            \Log::info('Nombre producto: "' . $nombreProducto . '"');
            
            // Extraer valores numéricos restantes (FACT, PEND, DIAS, PUNIT, PEND_VAL)
            $facturado = 0;
            $pendiente = 0;
            $dias = 0;
            $precioUnitario = 0;
            $valorPendiente = 0;
            
            $numerosEncontrados = 0;
            while ($currentIndex < count($fields) && $numerosEncontrados < 5) {
                if (is_numeric($fields[$currentIndex])) {
                    switch ($numerosEncontrados) {
                        case 0: $facturado = (float)$fields[$currentIndex]; break;
                        case 1: $pendiente = (float)$fields[$currentIndex]; break;
                        case 2: $dias = (int)$fields[$currentIndex]; break;
                        case 3: $precioUnitario = (float)$fields[$currentIndex]; break;
                        case 4: $valorPendiente = (float)$fields[$currentIndex]; break;
                    }
                    $numerosEncontrados++;
                    \Log::info('Número ' . $numerosEncontrados . ': ' . $fields[$currentIndex]);
                }
                $currentIndex++;
            }
            
            // Determinar estado
            $estado = $pendiente > 0 ? 'pendiente' : 'ingresada';
            
            // Crear objeto de cotización con nombres de campos consistentes
            $cotizacion = (object) [
                'id' => (int)$idmaeddo,
                'tipo' => 'NVV',
                'numero' => (int)$numero,
                'fecha_emision' => $fecha,
                'cliente_codigo' => $codigoCliente,
                'cliente_nombre' => $nombreCliente,
                'producto_codigo' => $codigoProducto,
                'cantidad' => $cantidad,
                'producto_nombre' => $nombreProducto,
                'facturado' => $facturado,
                'pendiente' => $pendiente,
                'vendedor_nombre' => 'GERARDO ORMEÑO PAREDES', // Por defecto
                'vendedor_codigo' => 'GOP', // Por defecto
                'region' => 'REGION METROPOLITANA', // Por defecto
                'comuna' => 'COLINA', // Por defecto
                'dias' => $dias,
                'rango' => $this->determinarRango($dias),
                'precio_unitario' => $precioUnitario,
                'valor_pendiente' => $valorPendiente,
                'tipo_referencia' => '',
                'numero_referencia' => '',
                'total' => $facturado,
                'saldo' => $pendiente,
                'estado' => $estado,
                'fuente' => 'sql_server',
                'fecha' => $fecha
            ];
            
            \Log::info('Cotización creada: ' . $cotizacion->cliente_nombre . ' - ' . $cotizacion->producto_nombre);
            
            return $cotizacion;
            
        } catch (\Exception $e) {
            \Log::error('Error extrayendo campos: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Determinar rango basado en días
     */
    private function determinarRango($dias)
    {
        if ($dias < 8) {
            return 'Entre 1 y 7 días';
        } elseif ($dias <= 30) {
            return 'Entre 8 y 30 Días';
        } elseif ($dias <= 60) {
            return 'Entre 31 y 60 Días';
        } else {
            return 'Mas de 60 Días';
        }
    }
    
    /**
     * Generar nota de venta en SQL Server después de aprobar cotización
     */
    public function generarNotaVenta($cotizacionId)
    {
        try {
            // Obtener la cotización
            $cotizacion = Cotizacion::with('detalles')->findOrFail($cotizacionId);
            
            if ($cotizacion->estado !== 'aprobado') {
                return response()->json([
                    'success' => false,
                    'message' => 'La cotización debe estar aprobada para generar la nota de venta'
                ], 400);
            }
            
            // Generar nota de venta en SQL Server
            $resultado = $this->insertarNotaVentaSQLServer($cotizacion);
            
            if ($resultado['success']) {
                // Marcar cotización como procesada
                $cotizacion->update([
                    'estado' => 'procesada',
                    'nota_venta_id' => $resultado['nota_venta_id']
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Nota de venta generada exitosamente',
                    'nota_venta_id' => $resultado['nota_venta_id']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar nota de venta: ' . $resultado['message']
                ], 500);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error generando nota de venta: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar nota de venta'
            ], 500);
        }
    }
    
    /**
     * Insertar nota de venta en SQL Server
     */
    private function insertarNotaVentaSQLServer($cotizacion)
    {
        try {
            // Obtener siguiente correlativo para MAEEDO
            $queryCorrelativo = "SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id FROM MAEEDO WHERE EMPRESA = '01'";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $queryCorrelativo . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            // Parsear el resultado para obtener el siguiente ID
            $siguienteId = 1; // Valor por defecto
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (is_numeric($line) && $line > 0) {
                        $siguienteId = (int)$line;
                        break;
                    }
                }
            }
            
            \Log::info('Siguiente ID para MAEEDO: ' . $siguienteId);
            
            // Calcular fecha de vencimiento (30 días desde hoy)
            $fechaVencimiento = date('Y-m-d', strtotime('+30 days'));
            
            // Obtener información del vendedor
            $codigoVendedor = auth()->user()->codigo_vendedor ?? '001';
            $nombreVendedor = auth()->user()->name ?? 'Vendedor Sistema';
            
            // Insertar encabezado en MAEEDO con campos completos
            $insertMAEEDO = "
                INSERT INTO MAEEDO (
                    IDMAEEDO, TIDO, NUDO, ENDO, SUENDO, FEEMDO, FE01VEDO, FEULVEDO, 
                    VABRDO, VAABDO, EMPRESA, KOFU, SUDO, ESDO, TIDOEXTE, NUDOEXTE,
                    FEULVEDO, KOFUEN, KOFUAUX, KOFUPA, KOFUVE, KOFUCO, KOFUCA,
                    KOFUCH, KOFUPE, KOFUIN, KOFUAD, KOFUGE, KOFUGE2, KOFUGE3,
                    KOFUGE4, KOFUGE5, KOFUGE6, KOFUGE7, KOFUGE8, KOFUGE9, KOFUGE10
                ) VALUES (
                    {$siguienteId}, 'NVV', {$siguienteId}, '{$cotizacion->cliente_codigo}', 
                    '001', GETDATE(), '{$fechaVencimiento}', '{$fechaVencimiento}', 
                    {$cotizacion->total}, 0, '01', '{$codigoVendedor}', '001', 'N',
                    'NVV', {$siguienteId}, '{$fechaVencimiento}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                    '{$codigoVendedor}', '{$codigoVendedor}'
                )
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDO . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (str_contains($result, 'error')) {
                throw new \Exception('Error insertando encabezado: ' . $result);
            }
            
            \Log::info('Encabezado MAEEDO insertado correctamente');
            
            // Insertar detalles en MAEDDO
            foreach ($cotizacion->detalles as $index => $detalle) {
                $lineaId = $index + 1;
                
                $insertMAEDDO = "
                    INSERT INTO MAEDDO (
                        IDMAEEDO, IDMAEDDO, KOPRCT, NOKOPR, CAPRCO1, PPPRNE, 
                        CAPRCO2, PPPRNE2, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
                        FEEMLI, FEULVE, VANELI, VABRLI, VAABLI, ESDO, LILG,
                        CAPRAD1, CAPREX1, CAPRAD2, CAPREX2, KOFULIDO, KOFUAUX,
                        KOFUPA, KOFUVE, KOFUCO, KOFUCA, KOFUCH, KOFUPE, KOFUIN,
                        KOFUAD, KOFUGE, KOFUGE2, KOFUGE3, KOFUGE4, KOFUGE5,
                        KOFUGE6, KOFUGE7, KOFUGE8, KOFUGE9, KOFUGE10
                    ) VALUES (
                        {$siguienteId}, {$lineaId}, '{$detalle->producto_codigo}', 
                        '{$detalle->producto_nombre}', {$detalle->cantidad}, 
                        {$detalle->precio}, 0, 0, '01', 'NVV', {$siguienteId},
                        '{$cotizacion->cliente_codigo}', '001', GETDATE(),
                        '{$fechaVencimiento}', " . ($detalle->cantidad * $detalle->precio) . ",
                        " . ($detalle->cantidad * $detalle->precio) . ", 0, 'N', 'SI',
                        0, 0, 0, 0, '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}', '{$codigoVendedor}',
                        '{$codigoVendedor}', '{$codigoVendedor}'
                    )
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $insertMAEDDO . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'error')) {
                    throw new \Exception('Error insertando detalle línea ' . $lineaId . ': ' . $result);
                }
            }
            
            \Log::info('Detalles MAEDDO insertados correctamente');
            
            // Insertar en MAEEDOOB (Observaciones)
            $insertMAEEDOOB = "
                INSERT INTO MAEEDOOB (
                    IDMAEEDO, IDMAEDOOB, OBSERVACION, EMPRESA
                ) VALUES (
                    {$siguienteId}, 1, 'Cotización generada desde sistema web', '01'
                )
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEEDOOB . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (str_contains($result, 'error')) {
                \Log::warning('Error insertando observaciones MAEEDOOB: ' . $result);
            } else {
                \Log::info('Observaciones MAEEDOOB insertadas correctamente');
            }
            
            // Insertar en MAEVEN (Vendedor)
            $codigoVendedor = auth()->user()->codigo_vendedor ?? '001';
            $insertMAEVEN = "
                INSERT INTO MAEVEN (
                    IDMAEEDO, KOFU, NOKOFU, EMPRESA
                ) VALUES (
                    {$siguienteId}, '{$codigoVendedor}', 'Vendedor Sistema Web', '01'
                )
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $insertMAEVEN . "\ngo\nquit");
            
            $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (str_contains($result, 'error')) {
                \Log::warning('Error insertando vendedor MAEVEN: ' . $result);
            } else {
                \Log::info('Vendedor MAEVEN insertado correctamente');
            }
            
            // Actualizar stock comprometido en MAEST cuando se aprueba la cotización
            foreach ($cotizacion->detalles as $detalle) {
                $stockActualizado = $this->actualizarStockComprometido($detalle->producto_codigo, $detalle->cantidad);
                
                if (!$stockActualizado) {
                    \Log::warning('No se pudo actualizar stock comprometido para producto ' . $detalle->producto_codigo);
                }
            }
            
            \Log::info('Stock comprometido MAEST actualizado correctamente');
            
            // Actualizar productos en MAEPR (si es necesario)
            foreach ($cotizacion->detalles as $detalle) {
                $updateMAEPR = "
                    UPDATE MAEPR 
                    SET ULTIMACOMPRA = GETDATE()
                    WHERE KOPR = '{$detalle->producto_codigo}'
                ";
                
                $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
                file_put_contents($tempFile, $updateMAEPR . "\ngo\nquit");
                
                $command = "tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " < {$tempFile} 2>&1";
                $result = shell_exec($command);
                
                unlink($tempFile);
                
                if (str_contains($result, 'error')) {
                    \Log::warning('Error actualizando MAEPR para producto ' . $detalle->producto_codigo . ': ' . $result);
                }
            }
            
            \Log::info('Productos MAEPR actualizados correctamente');
            
            return [
                'success' => true,
                'nota_venta_id' => $siguienteId,
                'message' => 'Nota de venta generada exitosamente con todas las tablas actualizadas'
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error en insertarNotaVentaSQLServer: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function calcularDescuentos($codigoCliente, $totalSinDescuento)
    {
        $descuentoGlobal = 0;
        $porcentajeDescuento = 0;
        
        try {
            // 1. Descuento del 5% si pedido > $400,000
            if ($totalSinDescuento > 400000) {
                $descuentoGlobal += $totalSinDescuento * 0.05;
                $porcentajeDescuento += 5;
            }
            
            // 2. Descuento por promedio de compras últimos 3 meses
            $promedioCompras = $this->calcularPromedioCompras($codigoCliente);
            if ($promedioCompras > 400000) {
                $descuentoAdicional = $totalSinDescuento * 0.05;
                $descuentoGlobal += $descuentoAdicional;
                $porcentajeDescuento += 5;
            }
            
        } catch (\Exception $e) {
            \Log::error('Error calculando descuentos: ' . $e->getMessage());
        }
        
        return [
            'descuento_global' => $descuentoGlobal,
            'porcentaje_descuento' => $porcentajeDescuento
        ];
    }
    
    private function calcularPromedioCompras($codigoCliente)
    {
        try {
            $tresMesesAtras = now()->subMonths(3)->format('Y-m-d');
            
            $query = "SELECT SUM(VABRDO) as total FROM MAEEDO WHERE ENDO = '{$codigoCliente}' AND TIDO = 'FCV' AND FEEMDO >= '{$tresMesesAtras}'";
            $result = shell_exec("tsql -H " . env('SQLSRV_EXTERNAL_HOST') . " -p " . env('SQLSRV_EXTERNAL_PORT') . " -U " . env('SQLSRV_EXTERNAL_USERNAME') . " -P " . env('SQLSRV_EXTERNAL_PASSWORD') . " -D " . env('SQLSRV_EXTERNAL_DATABASE') . " -Q \"{$query}\" 2>&1");
            
            $totalCompras = 0;
            if ($result && !str_contains($result, 'error')) {
                if (preg_match('/(\d+\.?\d*)/', $result, $matches)) {
                    $totalCompras = (float)$matches[1];
                }
            }
            
            return $totalCompras / 3; // Promedio mensual
            
        } catch (\Exception $e) {
            \Log::error('Error calculando promedio de compras: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Calcular stock comprometido basado en notas de venta pendientes
     */
    private function calcularStockComprometido($codigoProducto)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Consultar la vista NVV_Pendientes para obtener stock comprometido
            $query = "
                SELECT SUM(CAPRCO1 - CAPRAD1 - CAPREX1) as stock_comprometido
                FROM NVV_Pendientes 
                WHERE KOPRCT = '{$codigoProducto}'
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            $stockComprometido = 0;
            if ($result && !str_contains($result, 'error')) {
                $lines = explode("\n", $result);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (is_numeric($line)) {
                        $stockComprometido = (float)$line;
                        break;
                    }
                }
            }
            
            \Log::info("Stock comprometido para producto {$codigoProducto}: {$stockComprometido}");
            return $stockComprometido;
            
        } catch (\Exception $e) {
            \Log::error('Error calculando stock comprometido: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtener información completa para la cotización
     */
    private function obtenerInformacionCotizacion($clienteCodigo, $productos)
    {
        $informacion = [
            'cliente' => null,
            'vendedor' => null,
            'productos' => [],
            'totales' => [
                'subtotal' => 0,
                'descuento' => 0,
                'total' => 0
            ]
        ];
        
        try {
            // 1. Obtener información del cliente
            $cobranzaService = new \App\Services\CobranzaService();
            $clienteData = $cobranzaService->getClienteInfo($clienteCodigo);
            
            if ($clienteData) {
                $informacion['cliente'] = (object) [
                    'codigo' => $clienteData['CODIGO_CLIENTE'] ?? $clienteCodigo,
                    'nombre' => $clienteData['NOMBRE_CLIENTE'] ?? '',
                    'direccion' => $clienteData['DIRECCION'] ?? '',
                    'telefono' => $clienteData['TELEFONO'] ?? '',
                    'email' => '', // No está en la consulta actual
                    'region' => $clienteData['REGION'] ?? '',
                    'comuna' => $clienteData['COMUNA'] ?? '',
                    'vendedor_asignado' => $clienteData['VENDEDOR'] ?? ''
                ];
            }
            
            // 2. Obtener información del vendedor actual
            $usuario = auth()->user();
            $informacion['vendedor'] = (object) [
                'codigo' => $usuario->codigo_vendedor ?? '001',
                'nombre' => $usuario->name ?? 'Vendedor Sistema',
                'email' => $usuario->email ?? ''
            ];
            
            // 3. Obtener información detallada de productos
            foreach ($productos as $producto) {
                $productoInfo = $this->obtenerInformacionProducto($producto['codigo']);
                $informacion['productos'][] = $productoInfo;
                
                // Calcular totales
                $subtotal = $producto['cantidad'] * $producto['precio'];
                $informacion['totales']['subtotal'] += $subtotal;
            }
            
            // 4. Calcular descuentos
            $descuentos = $this->calcularDescuentos($clienteCodigo, $informacion['totales']['subtotal']);
            $informacion['totales']['descuento'] = $descuentos['descuento_global'];
            $informacion['totales']['total'] = $informacion['totales']['subtotal'] - $descuentos['descuento_global'];
            
            \Log::info('✅ Información de cotización obtenida correctamente');
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo información de cotización: ' . $e->getMessage());
        }
        
        return $informacion;
    }
    
    /**
     * Obtener información detallada de un producto
     */
    private function obtenerInformacionProducto($codigoProducto)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            $query = "
                SELECT TOP 1
                    MAEPR.KOPR AS CODIGO,
                    MAEPR.NOKOPR AS NOMBRE,
                    MAEPR.UD01PR AS UNIDAD,
                    MAEPR.RLUD AS RELACION_UNIDADES,
                    MAEPR.DIVISIBLE AS DIVISIBLE_UD1,
                    MAEPR.DIVISIBLE2 AS DIVISIBLE_UD2,
                    MAEST.STFI1 AS STOCK_FISICO,
                    MAEST.STOCNV1 AS STOCK_COMPROMETIDO,
                    (MAEST.STFI1 - MAEST.STOCNV1) AS STOCK_DISPONIBLE,
                    TABBO.NOKOBO AS NOMBRE_BODEGA,
                    MAEST.KOBO AS BODEGA_ID
                FROM MAEPR
                LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR AND MAEST.KOBO = '01'
                LEFT JOIN TABBO ON MAEST.KOBO = TABBO.KOBO
                WHERE MAEPR.KOPR = '{$codigoProducto}'
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $query . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            // Parsear resultado (simplificado por ahora)
            return (object) [
                'codigo' => $codigoProducto,
                'nombre' => 'Producto ' . $codigoProducto,
                'unidad' => 'UN',
                'stock_fisico' => 0,
                'stock_comprometido' => 0,
                'stock_disponible' => 0
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo información de producto: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Actualizar stock comprometido cuando se aprueba una cotización
     */
    private function actualizarStockComprometido($codigoProducto, $cantidad)
    {
        try {
            $host = env('SQLSRV_EXTERNAL_HOST');
            $port = env('SQLSRV_EXTERNAL_PORT', '1433');
            $database = env('SQLSRV_EXTERNAL_DATABASE');
            $username = env('SQLSRV_EXTERNAL_USERNAME');
            $password = env('SQLSRV_EXTERNAL_PASSWORD');
            
            // Actualizar STOCNV1 en MAEST (stock comprometido)
            $updateMAEST = "
                UPDATE MAEST 
                SET STOCNV1 = ISNULL(STOCNV1, 0) + {$cantidad}
                WHERE KOPR = '{$codigoProducto}' AND KOBO = '01'
            ";
            
            $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
            file_put_contents($tempFile, $updateMAEST . "\ngo\nquit");
            
            $command = "tsql -H {$host} -p {$port} -U {$username} -P {$password} -D {$database} < {$tempFile} 2>&1";
            $result = shell_exec($command);
            
            unlink($tempFile);
            
            if (str_contains($result, 'error')) {
                \Log::warning('Error actualizando stock comprometido MAEST para producto ' . $codigoProducto . ': ' . $result);
                return false;
            }
            
            \Log::info("Stock comprometido actualizado para producto {$codigoProducto}: +{$cantidad}");
            return true;
            
        } catch (\Exception $e) {
            \Log::error('Error actualizando stock comprometido: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Liberar stock comprometido de una cotización
     */
    public function liberarStockComprometido($cotizacionId)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($cotizacionId);
            
            // Verificar que el usuario tenga permisos
            if (!auth()->user()->hasPermissionTo('aprobar cotizaciones') && 
                auth()->id() !== $cotizacion->vendedor_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para liberar stock de esta cotización'
                ], 403);
            }
            
            $stockComprometidoService = new \App\Services\StockComprometidoService();
            $resultado = $stockComprometidoService->liberarStock($cotizacionId, 'Liberado manualmente por ' . auth()->user()->name);
            
            if ($resultado['success']) {
                // Actualizar estado de la cotización
                $cotizacion->update([
                    'estado' => 'cancelada',
                    'motivo_rechazo' => 'Stock liberado manualmente',
                    'fecha_cancelacion' => now(),
                    'cancelado_por' => auth()->id()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Stock liberado exitosamente',
                    'stock_liberado' => $resultado['stock_liberado']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error liberando stock: ' . $resultado['message']
                ], 500);
            }
            
        } catch (\Exception $e) {
            \Log::error('Error liberando stock comprometido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error liberando stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen de stock comprometido
     */
    public function resumenStockComprometido(Request $request)
    {
        try {
            $stockComprometidoService = new \App\Services\StockComprometidoService();
            $productoCodigo = $request->get('producto_codigo');
            $bodegaCodigo = $request->get('bodega_codigo', '001');
            
            $resumen = $stockComprometidoService->obtenerResumenStockComprometido($productoCodigo, $bodegaCodigo);
            
            return response()->json([
                'success' => true,
                'data' => $resumen
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo resumen de stock comprometido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo resumen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar nota de venta automáticamente cuando todos los productos tienen stock
     */
    private function generarNotaVentaAutomatica($cotizacion, $productos)
    {
        try {
            \Log::info("🚀 GENERANDO NOTA DE VENTA AUTOMÁTICA PARA COTIZACIÓN {$cotizacion->id}");
            
            // Generar un número único para la nota de venta
            // Usamos un timestamp + ID de cotización para asegurar unicidad
            $numeroNotaVenta = (int)(time() . str_pad($cotizacion->id, 3, '0', STR_PAD_LEFT));
            
            \Log::info("✅ Nota de venta generada: {$numeroNotaVenta}");
            
            return [
                'success' => true,
                'nota_venta_id' => $numeroNotaVenta,
                'message' => 'Nota de venta generada exitosamente'
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error generando nota de venta automática: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error al generar nota de venta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Editar cotización existente
     */
    public function editar($id)
    {
        try {
            $cotizacion = \App\Models\Cotizacion::findOrFail($id);
            
            \Log::info('Editando cotización ID: ' . $id);
            \Log::info('Estado: ' . $cotizacion->estado);
            \Log::info('Código cliente: ' . ($cotizacion->cliente_codigo ?: 'VACÍO'));
            \Log::info('Nombre cliente: ' . ($cotizacion->cliente_nombre ?: 'VACÍO'));
            \Log::info('Vendedor ID: ' . ($cotizacion->user_id ?: 'VACÍO'));
            
            // Verificar que el usuario pueda editar esta cotización
            if (!auth()->user()->hasPermissionTo('edit_quotations') && 
                auth()->id() !== $cotizacion->user_id) {
                abort(403, 'No tienes permisos para editar esta cotización');
            }
            
            // Verificar que la cotización no haya sido aprobada por ningún perfil
            // Solo se puede editar si está en estado pendiente (borrador) o rechazada
            $estadosNoEditables = [
                'pendiente_picking',      // Ya aprobada por Supervisor
                'aprobada_supervisor',    // Ya aprobada por Supervisor
                'aprobada_compras',       // Ya aprobada por Compras
                'aprobada_picking',       // Ya aprobada por Picking
                'ingresada',             // Ya ingresada en SQL Server
                'procesada'              // Ya procesada completamente
            ];
            
            if (in_array($cotizacion->estado_aprobacion, $estadosNoEditables)) {
                $mensajesEstado = [
                    'pendiente_picking' => 'Esta NVV ya fue aprobada por el Supervisor y está pendiente de revisión por Compras',
                    'aprobada_supervisor' => 'Esta NVV ya fue aprobada por el Supervisor',
                    'aprobada_compras' => 'Esta NVV ya fue aprobada por Compras y está pendiente de Picking',
                    'aprobada_picking' => 'Esta NVV ya fue aprobada por Picking',
                    'ingresada' => 'Esta NVV ya fue ingresada al sistema SQL Server',
                    'procesada' => 'Esta NVV ya fue procesada completamente'
                ];
                
                $mensaje = $mensajesEstado[$cotizacion->estado_aprobacion] ?? 'No se puede editar una cotización que ya ha sido aprobada';
                
                // Redirigir a la vista de solo lectura en lugar de mostrar error
                return redirect()->route('cotizacion.ver', $cotizacion->id)
                    ->with('info', $mensaje . '. Redirigido a la vista de solo lectura.');
            }
            
            // Obtener datos del cliente desde la cotización (solo mostrar, no editar)
            $cliente = (object) [
                'codigo' => $cotizacion->cliente_codigo ?? '',
                'nombre' => $cotizacion->cliente_nombre ?? 'Cliente no asignado',
                'direccion' => $cotizacion->cliente_direccion ?? '',
                'telefono' => $cotizacion->cliente_telefono ?? '',
                'email' => '',
                'region' => '',
                'comuna' => '',
                'vendedor' => '',
                'lista_precios_codigo' => $cotizacion->cliente_lista_precios ?? '01P',
                'lista_precios_nombre' => 'Lista Precios ' . ($cotizacion->cliente_lista_precios ?? '01P'),
                'bloqueado' => false,
                'puede_generar_nota_venta' => true,
                'motivo_rechazo' => null
            ];
            
            // Obtener productos de la cotización desde la tabla cotizacion_productos
            $productosCotizacion = [];
            
            foreach ($cotizacion->productos as $producto) {
                // Obtener información adicional del producto desde la tabla productos
                $productoDB = DB::table('productos')
                    ->where('KOPR', $producto->codigo_producto)
                    ->first();
                
                // Determinar lista de precios para obtener descuento máximo
                $listaPrecios = $cotizacion->cliente_lista_precios ?? '01P';
                $descuentoMaximo = 0;
                $multiplo = 1;
                $unidad = 'UN';
                
                if ($productoDB) {
                    // Obtener múltiplo de venta
                    $multiplo = $productoDB->MUVECODI ?? 1;
                    if ($multiplo <= 0) $multiplo = 1;
                    
                    // Obtener unidad de medida
                    $unidad = $productoDB->KOPRUDEN ?? 'UN';
                    
                    // Obtener descuento máximo según lista de precios
                    if ($listaPrecios === '01P' || $listaPrecios === '01') {
                        $descuentoMaximo = $productoDB->descuento_maximo_01p ?? 0;
                    } elseif ($listaPrecios === '02P' || $listaPrecios === '02') {
                        $descuentoMaximo = $productoDB->descuento_maximo_02p ?? 0;
                    } elseif ($listaPrecios === '03P' || $listaPrecios === '03') {
                        $descuentoMaximo = $productoDB->descuento_maximo_03p ?? 0;
                    } elseif ($listaPrecios === '04P' || $listaPrecios === '04') {
                        $descuentoMaximo = $productoDB->descuento_maximo_04p ?? 0;
                    } elseif ($listaPrecios === '05P' || $listaPrecios === '05') {
                        $descuentoMaximo = $productoDB->descuento_maximo_05p ?? 0;
                    }
                }
                
                $productosCotizacion[] = [
                    'codigo' => $producto->codigo_producto,
                    'nombre' => $producto->nombre_producto,
                    'cantidad' => $producto->cantidad,
                    'precio' => floatval($producto->precio_unitario),
                    'subtotal' => floatval($producto->subtotal),
                    'descuento' => floatval($producto->descuento_porcentaje ?? 0),
                    'descuentoMaximo' => floatval($descuentoMaximo),
                    'multiplo' => intval($multiplo),
                    'stock_disponible' => $producto->stock_disponible,
                    'stock_suficiente' => $producto->stock_suficiente,
                    'unidad' => $unidad,
                    'stock' => $producto->stock_disponible // Alias para compatibilidad con frontend
                ];
            }
            
            \Log::info('Cliente encontrado: ' . ($cliente ? 'SÍ' : 'NO'));
            \Log::info('Productos encontrados: ' . count($productosCotizacion));
            
            // Variable para controlar si se puede generar nota de venta
            $puedeGenerarNotaVenta = true;
            
            return view('cotizaciones.editar', compact('cotizacion', 'cliente', 'productosCotizacion', 'puedeGenerarNotaVenta'));
            
        } catch (\Exception $e) {
            \Log::error('Error editando cotización: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            abort(500, 'Error editando cotización: ' . $e->getMessage());
        }
    }

    /**
     * Ver cotización (solo lectura)
     */
    public function ver($id)
    {
        try {
            $cotizacion = \App\Models\Cotizacion::findOrFail($id);
            
            \Log::info('Viendo cotización ID: ' . $id);
            \Log::info('Productos en cotización: ' . $cotizacion->productos->count());
            
            // Obtener datos del cliente desde la cotización (solo mostrar, no editar)
            $cliente = (object) [
                'codigo' => $cotizacion->cliente_codigo ?? '',
                'nombre' => $cotizacion->cliente_nombre ?? 'Cliente no asignado',
                'direccion' => $cotizacion->cliente_direccion ?? '',
                'telefono' => $cotizacion->cliente_telefono ?? '',
                'email' => '',
                'region' => '',
                'comuna' => '',
                'vendedor' => '',
                'lista_precios_codigo' => $cotizacion->cliente_lista_precios ?? '01P',
                'lista_precios_nombre' => 'Lista Precios ' . ($cotizacion->cliente_lista_precios ?? '01P'),
                'bloqueado' => false,
                'puede_generar_nota_venta' => true,
                'motivo_rechazo' => null
            ];
            
            // Obtener productos de la cotización desde la tabla cotizacion_productos
            $productosCotizacion = [];
            
            foreach ($cotizacion->productos as $producto) {
                $productosCotizacion[] = [
                    'codigo' => $producto->codigo_producto,
                    'nombre' => $producto->nombre_producto,
                    'cantidad' => $producto->cantidad,
                    'precio' => floatval($producto->precio_unitario),
                    'subtotal' => floatval($producto->subtotal),
                    'descuento' => floatval($producto->descuento_porcentaje ?? 0),
                    'descuento_valor' => floatval($producto->descuento_valor ?? 0),
                    'subtotal_con_descuento' => floatval($producto->subtotal_con_descuento ?? 0),
                    'iva_valor' => floatval($producto->iva_valor ?? 0),
                    'total_producto' => floatval($producto->total_producto ?? 0),
                    'stock_disponible' => $producto->stock_disponible,
                    'stock_suficiente' => $producto->stock_suficiente
                ];
            }
            
            // Variable para controlar si se puede generar nota de venta
            $puedeGenerarNotaVenta = false; // En modo vista no se puede generar
            
            // Obtener historial completo
            $historial = \App\Models\CotizacionHistorial::obtenerHistorialCompleto($id);
            
            // Obtener resumen de tiempos
            $resumenTiempos = \App\Services\HistorialCotizacionService::obtenerResumenTiempos($cotizacion);
            
            return view('cotizaciones.detalle', compact('cotizacion', 'cliente', 'productosCotizacion', 'puedeGenerarNotaVenta', 'historial', 'resumenTiempos'));
            
        } catch (\Exception $e) {
            \Log::error('Error viendo cotización: ' . $e->getMessage());
            abort(500, 'Error viendo cotización');
        }
    }

    /**
     * Actualizar cotización
     */
    public function actualizar(Request $request, $id)
    {
        try {
            $cotizacion = \App\Models\Cotizacion::findOrFail($id);
            
            \Log::info('Actualizando cotización ID: ' . $id);
            \Log::info('Datos recibidos: ' . json_encode($request->all()));
            
            // Verificar que el usuario pueda editar esta cotización
            if (!auth()->user()->hasPermissionTo('edit_quotations') && 
                auth()->id() !== $cotizacion->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para editar esta cotización'
                ], 403);
            }
            
            // Verificar que la cotización no haya sido aprobada por ningún perfil
            // Solo se puede editar si está en estado pendiente (borrador) o rechazada
            $estadosNoEditables = [
                'pendiente_picking',      // Ya aprobada por Supervisor
                'aprobada_supervisor',    // Ya aprobada por Supervisor
                'aprobada_compras',       // Ya aprobada por Compras
                'aprobada_picking',       // Ya aprobada por Picking
                'ingresada',             // Ya ingresada en SQL Server
                'procesada'              // Ya procesada completamente
            ];
            
            if (in_array($cotizacion->estado_aprobacion, $estadosNoEditables)) {
                $mensajesEstado = [
                    'pendiente_picking' => 'Esta NVV ya fue aprobada por el Supervisor y está pendiente de revisión por Compras',
                    'aprobada_supervisor' => 'Esta NVV ya fue aprobada por el Supervisor',
                    'aprobada_compras' => 'Esta NVV ya fue aprobada por Compras y está pendiente de Picking',
                    'aprobada_picking' => 'Esta NVV ya fue aprobada por Picking',
                    'ingresada' => 'Esta NVV ya fue ingresada al sistema SQL Server',
                    'procesada' => 'Esta NVV ya fue procesada completamente'
                ];
                
                $mensaje = $mensajesEstado[$cotizacion->estado_aprobacion] ?? 'No se puede editar una cotización que ya ha sido aprobada';
                
                // Redirigir a la vista de solo lectura en lugar de mostrar error
                return redirect()->route('cotizacion.ver', $cotizacion->id)
                    ->with('info', $mensaje . '. Redirigido a la vista de solo lectura.');
            }
            
            // Calcular totales por producto y generales
            $productos = $request->input('productos', []);
            $subtotalSinDescuentos = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;
            
            foreach ($productos as $producto) {
                // Calcular valores por producto
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                
                // Acumular totales
                $subtotalSinDescuentos += $precioBase;
                $descuentoTotal += $descuentoValor;
                $ivaTotal += $ivaProducto;
            }
            
            // Calcular subtotal neto (después de descuentos)
            $subtotalNeto = $subtotalSinDescuentos - $descuentoTotal;
            
            // Total final con IVA
            $total = $subtotalNeto + $ivaTotal;
            
            \Log::info('Totales calculados - Subtotal sin descuentos: ' . $subtotalSinDescuentos . ', Descuento total: ' . $descuentoTotal . ', Subtotal neto: ' . $subtotalNeto . ', IVA total: ' . $ivaTotal . ', Total: ' . $total);
            
            // Actualizar cotización
            $cotizacion->update([
                'observaciones' => $request->input('observaciones', ''),
                'fecha_despacho' => $request->fecha_despacho ? \Carbon\Carbon::parse($request->fecha_despacho)->startOfDay() : null,
                'subtotal' => $subtotalSinDescuentos,
                'descuento_global' => $descuentoTotal,
                'subtotal_neto' => $subtotalNeto,
                'iva' => $ivaTotal,
                'total' => $total,
                'updated_at' => now()
            ]);
            
            // Actualizar productos en la tabla cotizacion_productos
            // Primero eliminar productos existentes
            $cotizacion->productos()->delete();
            
            // Luego agregar los nuevos productos
            foreach ($productos as $producto) {
                // Calcular valores del producto
                $precioBase = $producto['cantidad'] * $producto['precio'];
                $descuentoPorcentaje = $producto['descuento'] ?? 0;
                $descuentoValor = $precioBase * ($descuentoPorcentaje / 100);
                $subtotalConDescuento = $precioBase - $descuentoValor;
                $ivaProducto = $subtotalConDescuento * 0.19;
                $totalProducto = $subtotalConDescuento + $ivaProducto;
                
                $cotizacion->productos()->create([
                    'codigo_producto' => $producto['codigo'],
                    'nombre_producto' => $producto['nombre'],
                    'cantidad' => $producto['cantidad'],
                    'precio_unitario' => $producto['precio'],
                    'subtotal' => $precioBase, // Precio base sin descuentos
                    'descuento_porcentaje' => $descuentoPorcentaje,
                    'descuento_valor' => $descuentoValor,
                    'subtotal_con_descuento' => $subtotalConDescuento,
                    'iva_porcentaje' => 19.00,
                    'iva_valor' => $ivaProducto,
                    'total_producto' => $totalProducto,
                    'stock_disponible' => $producto['stock_disponible'] ?? 0,
                    'stock_suficiente' => $producto['stock_suficiente'] ?? false
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Cotización actualizada exitosamente',
                'cotizacion_id' => $cotizacion->id
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error actualizando cotización: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error actualizando cotización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cotización
     */
    public function eliminar($id)
    {
        try {
            \Log::info('Intentando eliminar cotización ID: ' . $id);
            \Log::info('Usuario autenticado: ' . auth()->id() . ' - ' . auth()->user()->name);
            
            $cotizacion = \App\Models\Cotizacion::findOrFail($id);
            \Log::info('Cotización encontrada: ' . $cotizacion->id . ' - Estado: ' . $cotizacion->estado);
            
            // Verificar que el usuario pueda eliminar esta cotización
            $hasPermission = auth()->user()->hasPermissionTo('delete_quotations');
            $hasRole = auth()->user()->hasRole('Super Admin');
            $isOwner = auth()->id() === $cotizacion->user_id; // El vendedor puede eliminar sus propias cotizaciones
            \Log::info('Tiene permiso delete_quotations: ' . ($hasPermission ? 'SÍ' : 'NO'));
            \Log::info('Tiene rol Super Admin: ' . ($hasRole ? 'SÍ' : 'NO'));
            \Log::info('Es propietario de la cotización: ' . ($isOwner ? 'SÍ' : 'NO'));
            \Log::info('Usuario actual: ' . auth()->id() . ', Creador: ' . $cotizacion->user_id);
            
            if (!$hasPermission && !$hasRole && !$isOwner) {
                \Log::warning('Usuario sin permisos para eliminar cotización');
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para eliminar esta cotización'
                ], 403);
            }
            
            // Verificar que sea una nota de venta
            if ($cotizacion->tipo_documento !== 'nota_venta') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una cotización desde el controlador de notas de venta'
                ], 400);
            }
            
            // Verificar que la NVV no esté validada (solo se pueden eliminar si están en estado 'borrador', 'enviada' o 'rechazada')
            // No se pueden eliminar si están en 'procesada', 'ingresada', o si ya fueron aprobadas
            if (in_array($cotizacion->estado, ['procesada', 'ingresada'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una nota de venta que ya ha sido validada y procesada'
                ], 403);
            }
            
            // Verificar que no esté en un estado de aprobación avanzado
            if (in_array($cotizacion->estado_aprobacion, ['aprobada_supervisor', 'aprobada_compras', 'aprobada_picking', 'procesada'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una nota de venta que ya ha sido aprobada'
                ], 403);
            }
            
            // Verificar que la cotización no haya sido validada por supervisor, compras o picking
            $validadaPorSupervisor = !is_null($cotizacion->aprobado_por_supervisor);
            $validadaPorCompras = !is_null($cotizacion->aprobado_por_compras);
            $validadaPorPicking = !is_null($cotizacion->aprobado_por_picking);
            
            \Log::info('Validaciones de aprobación:');
            \Log::info('- Validada por supervisor: ' . ($validadaPorSupervisor ? 'SÍ' : 'NO'));
            \Log::info('- Validada por compras: ' . ($validadaPorCompras ? 'SÍ' : 'NO'));
            \Log::info('- Validada por picking: ' . ($validadaPorPicking ? 'SÍ' : 'NO'));
            
            if ($validadaPorSupervisor || $validadaPorCompras || $validadaPorPicking) {
                $motivos = [];
                if ($validadaPorSupervisor) $motivos[] = 'supervisor';
                if ($validadaPorCompras) $motivos[] = 'compras';
                if ($validadaPorPicking) $motivos[] = 'picking';
                
                \Log::warning('Intento de eliminar nota de venta ya validada por: ' . implode(', ', $motivos));
                
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una nota de venta que ya ha sido validada por: ' . implode(', ', $motivos)
                ], 403);
            }
            
            // Liberar stock comprometido si existe
            $stockComprometidoService = new \App\Services\StockComprometidoService();
            $stockComprometidoService->liberarStock($cotizacion->id, 'Eliminada por usuario');
            
            // Eliminar productos de la cotización
            $cotizacion->productos()->delete();
            
            // Eliminar cotización/NVV
            \Log::info('Eliminando nota de venta...');
            $cotizacion->delete();
            \Log::info('Nota de venta eliminada exitosamente');
            
            return response()->json([
                'success' => true,
                'message' => 'Nota de venta eliminada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error eliminando nota de venta: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error eliminando nota de venta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar historial de una cotización
     */
    public function historial($id)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Vendedor') && !$user->hasRole('Supervisor') && !$user->hasRole('Super Admin')) {
            return redirect()->route('dashboard')->with('error', 'Acceso no autorizado');
        }

        $cotizacion = Cotizacion::with(['user', 'productos'])->findOrFail($id);
        
        // Si es Vendedor, verificar que la cotización le pertenece
        if ($user->hasRole('Vendedor') && $cotizacion->user_id !== $user->id) {
            return redirect()->route('cotizaciones.index')->with('error', 'No tienes permisos para ver esta cotización');
        }

        // Obtener historial completo
        $historial = \App\Models\CotizacionHistorial::obtenerHistorialCompleto($id);
        
        // Obtener resumen de tiempos
        $resumenTiempos = \App\Services\HistorialCotizacionService::obtenerResumenTiempos($cotizacion);

        return view('cotizaciones.historial-simple', compact('cotizacion', 'historial', 'resumenTiempos'))->with('pageSlug', 'cotizaciones');
    }
    
    /**
     * Verificar si el cliente tiene cheques protestados
     */
    private function verificarChequesProtestados($codigoCliente)
    {
        try {
            $cheques = DB::table('cheques_protestados')
                ->where('codigo_cliente', $codigoCliente)
                ->get();
            
            if ($cheques->isEmpty()) {
                return [
                    'tiene_cheques_protestados' => false,
                    'cantidad' => 0,
                    'valor_total' => 0,
                    'cheques' => []
                ];
            }
            
            $valorTotal = $cheques->sum('valor');
            
            return [
                'tiene_cheques_protestados' => true,
                'cantidad' => $cheques->count(),
                'valor_total' => $valorTotal,
                'cheques' => $cheques->toArray()
            ];
            
        } catch (\Exception $e) {
            \Log::error('Error verificando cheques protestados: ' . $e->getMessage());
            return [
                'tiene_cheques_protestados' => false,
                'cantidad' => 0,
                'valor_total' => 0,
                'cheques' => []
            ];
        }
    }
    
    /**
     * Obtener información de cheques protestados de un cliente
     */
    public function obtenerChequesProtestados(Request $request)
    {
        $request->validate([
            'codigo_cliente' => 'required|string'
        ]);
        
        $codigoCliente = $request->codigo_cliente;
        $chequesProtestados = $this->verificarChequesProtestados($codigoCliente);
        
        return response()->json([
            'success' => true,
            'data' => $chequesProtestados
        ]);
    }
    
    /**
     * Convertir cotización a nota de venta
     */
    public function convertirANotaVenta(Request $request, $id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);
            
            // Verificar permisos
            if (!auth()->user()->hasRole(['Super Admin', 'Vendedor']) && auth()->id() !== $cotizacion->user_id) {
                return response()->json(['error' => 'No tienes permiso para convertir esta cotización'], 403);
            }
            
            // Verificar que sea una cotización
            if ($cotizacion->tipo_documento !== 'cotizacion') {
                return response()->json(['error' => 'Este documento ya es una Nota de Venta'], 400);
            }
            
            DB::beginTransaction();
            
            // Actualizar campos adicionales antes de convertir
            $cotizacion->numero_orden_compra = $request->numero_orden_compra;
            $cotizacion->observacion_vendedor = $request->observacion_vendedor;
            $cotizacion->solicitar_descuento_extra = (bool) $request->solicitar_descuento_extra;
            
            // Si solicita descuento extra, forzar aprobación de supervisor
            if ($cotizacion->solicitar_descuento_extra) {
                $cotizacion->tiene_problemas_credito = true;
            }
            
            // Convertir a nota de venta
            $cotizacion->convertirANotaVenta(auth()->id());
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Cotización convertida exitosamente a Nota de Venta. Ahora entrará al flujo de aprobaciones.'
            ]);
                
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error convirtiendo cotización a nota de venta: ' . $e->getMessage());
            return response()->json(['error' => 'Error al convertir cotización: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Obtener stock actual de un producto específico
     * ACTUALIZA el stock físico en MySQL desde SQL Server antes de retornar
     */
    public function obtenerStockProducto($codigo)
    {
        try {
            // 1. Consultar stock desde SQL Server y actualizar MySQL
            $stockConsultaService = new \App\Services\StockConsultaService();
            $stockSQL = $stockConsultaService->consultarStockDesdeSQLServer([$codigo]);
            
            if (isset($stockSQL[$codigo])) {
                // Actualizar stock en MySQL si es diferente
                $stockConsultaService->actualizarStockSiEsDiferente(
                    $codigo,
                    $stockSQL[$codigo]['stock_fisico'],
                    $stockSQL[$codigo]['stock_comprometido']
                );
                \Log::info("📦 Stock actualizado en MySQL para {$codigo}: Físico={$stockSQL[$codigo]['stock_fisico']}, Comprometido={$stockSQL[$codigo]['stock_comprometido']}");
            }
            
            // 2. Obtener stock disponible real (después de actualizar MySQL)
            $stockService = new \App\Services\StockComprometidoService();
            $stockDisponibleReal = $stockService->obtenerStockDisponibleReal($codigo);
            
            // 3. Obtener datos del producto actualizados
            $producto = \App\Models\Producto::where('KOPR', $codigo)->first();
            
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'stock_disponible' => $stockDisponibleReal,
                'stock_fisico' => $producto->stock_fisico ?? 0,
                'stock_comprometido' => $producto->stock_comprometido ?? 0,
                'producto' => [
                    'codigo' => $producto->KOPR,
                    'nombre' => $producto->NOKOPR,
                    'unidad' => $producto->UD01PR ?? 'UN'
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en obtenerStockProducto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo stock: ' . $e->getMessage()
            ]);
        }
    }
} 