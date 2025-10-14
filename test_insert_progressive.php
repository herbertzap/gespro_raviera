<?php

require_once 'vendor/autoload.php';

// Inicializar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== PRUEBA PROGRESIVA DE INSERT MAEDDO ===\n";
echo "Agregando campos uno por uno hasta encontrar el problemático\n\n";

// Obtener datos de la cotización 42
$cotizacion = \App\Models\Cotizacion::find(42);
$producto = $cotizacion->productos->first();

if (!$cotizacion || !$producto) {
    echo "ERROR: No se encontró la cotización 42 o sus productos\n";
    exit;
}

echo "Cotización: {$cotizacion->cliente_nombre}\n";
echo "Producto: {$producto->nombre_producto}\n";
echo "Cantidad: {$producto->cantidad}\n";
echo "Precio: {$producto->precio_unitario}\n\n";

// Valores calculados
$udtrpr = 1;
$rludpr = 1;
$ud01pr = 'UN';
$ud02pr = 'UN';
$codigoVendedor = 'CHP';
$sucursalCliente = '           ';
$porcentajeIVA = 19;
$valorIVA = ($producto->subtotal_con_descuento * $porcentajeIVA) / 100;
$precioConIVA = $producto->subtotal_con_descuento + $valorIVA;
$precioConIVARedondeado = round($precioConIVA, 2);
$porcentajeDescuento = $producto->descuento_porcentaje ?? 0;
$valorDescuento = $producto->descuento_valor ?? 0;
$subtotalConDescuento = $producto->subtotal_con_descuento;

// Obtener siguiente ID y NUDO
$siguienteId = 158892;
$nudoFormateado = '0000037579';
$lineaId = str_pad('1', 5, '0', STR_PAD_LEFT);

echo "Siguiente ID: {$siguienteId}\n";
echo "Siguiente NUDO: {$nudoFormateado}\n\n";

// PRUEBA 1: Insert básico (campos esenciales)
echo "=== PRUEBA 1: Insert básico ===\n";
$insertBasico = "
    INSERT INTO MAEDDO (
        IDMAEEDO, ARCHIRST, IDRST, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI,
        LILG, NULIDO, SULIDO, LUVTLIDO, BOSULIDO, KOFULIDO, NULILG,
        TIPR, KOPRCT,
        CAPRCO1, UD01PR,
        PPPRNELT, PPPRNE,
        VANELI, VABRLI,
        NOKOPR
    ) VALUES (
        {$siguienteId}, 0, 0, '01', 'NVV', '{$nudoFormateado}',
        '{$cotizacion->cliente_codigo}', '{$sucursalCliente}', '{$cotizacion->cliente_codigo}',
        'SI', '{$lineaId}', 'LIB', 'LIB', 'LIB', '{$codigoVendedor}', '{$lineaId}',
        'FPN', '{$producto->codigo_producto}',
        {$producto->cantidad}, '{$ud01pr}',
        {$producto->precio_unitario}, {$producto->precio_unitario},
        {$subtotalConDescuento}, {$subtotalConDescuento},
        '{$producto->nombre_producto}'
    )
";

if (ejecutarInsert($insertBasico, "Insert básico")) {
    echo "✅ Insert básico OK\n\n";
    
    // PRUEBA 2: Agregar campos de identificación
    echo "=== PRUEBA 2: Agregando campos de identificación ===\n";
    $insertIdentificacion = "
        INSERT INTO MAEDDO (
            IDMAEEDO, ARCHIRST, IDRST, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI,
            LILG, NULIDO, SULIDO, LUVTLIDO, BOSULIDO, KOFULIDO, NULILG,
            PRCT, TICT, TIPR, NUSEPR, KOPRCT,
            CAPRCO1, UD01PR,
            PPPRNELT, PPPRNE,
            VANELI, VABRLI,
            NOKOPR
        ) VALUES (
            {$siguienteId}, 0, 0, '01', 'NVV', '{$nudoFormateado}',
            '{$cotizacion->cliente_codigo}', '{$sucursalCliente}', '{$cotizacion->cliente_codigo}',
            'SI', '{$lineaId}', 'LIB', 'LIB', 'LIB', '{$codigoVendedor}', '{$lineaId}',
            0, 0, 'FPN', '', '{$producto->codigo_producto}',
            {$producto->cantidad}, '{$ud01pr}',
            {$producto->precio_unitario}, {$producto->precio_unitario},
            {$subtotalConDescuento}, {$subtotalConDescuento},
            '{$producto->nombre_producto}'
        )
    ";
    
    if (ejecutarInsert($insertIdentificacion, "Campos de identificación")) {
        echo "✅ Campos de identificación OK\n\n";
        
        // PRUEBA 3: Agregar campos de unidades
        echo "=== PRUEBA 3: Agregando campos de unidades ===\n";
        $insertUnidades = "
            INSERT INTO MAEDDO (
                IDMAEEDO, ARCHIRST, IDRST, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI,
                LILG, NULIDO, SULIDO, LUVTLIDO, BOSULIDO, KOFULIDO, NULILG,
                PRCT, TICT, TIPR, NUSEPR, KOPRCT,
                UDTRPR, RLUDPR, CAPRCO1, CAPRAD1, CAPREX1, CAPRNC1, UD01PR,
                CAPRCO2, CAPRAD2, CAPREX2, CAPRNC2, UD02PR,
                PPPRNELT, PPPRNE,
                VANELI, VABRLI,
                NOKOPR
            ) VALUES (
                {$siguienteId}, 0, 0, '01', 'NVV', '{$nudoFormateado}',
                '{$cotizacion->cliente_codigo}', '{$sucursalCliente}', '{$cotizacion->cliente_codigo}',
                'SI', '{$lineaId}', 'LIB', 'LIB', 'LIB', '{$codigoVendedor}', '{$lineaId}',
                0, 0, 'FPN', '', '{$producto->codigo_producto}',
                {$udtrpr}, {$rludpr}, {$producto->cantidad}, 0, 0, 0, '{$ud01pr}',
                {$producto->cantidad}, 0, 0, 0, '{$ud02pr}',
                {$producto->precio_unitario}, {$producto->precio_unitario},
                {$subtotalConDescuento}, {$subtotalConDescuento},
                '{$producto->nombre_producto}'
            )
        ";
        
        if (ejecutarInsert($insertUnidades, "Campos de unidades")) {
            echo "✅ Campos de unidades OK\n\n";
            
            // PRUEBA 4: Agregar campos de precios
            echo "=== PRUEBA 4: Agregando campos de precios ===\n";
            $insertPrecios = "
                INSERT INTO MAEDDO (
                    IDMAEEDO, ARCHIRST, IDRST, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI,
                    LILG, NULIDO, SULIDO, LUVTLIDO, BOSULIDO, KOFULIDO, NULILG,
                    PRCT, TICT, TIPR, NUSEPR, KOPRCT,
                    UDTRPR, RLUDPR, CAPRCO1, CAPRAD1, CAPREX1, CAPRNC1, UD01PR,
                    CAPRCO2, CAPRAD2, CAPREX2, CAPRNC2, UD02PR,
                    KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                    PPPRNELT, PPPRNE, PPPRBRLT, PPPRBR,
                    VANELI, VABRLI,
                    NOKOPR
                ) VALUES (
                    {$siguienteId}, 0, 0, '01', 'NVV', '{$nudoFormateado}',
                    '{$cotizacion->cliente_codigo}', '{$sucursalCliente}', '{$cotizacion->cliente_codigo}',
                    'SI', '{$lineaId}', 'LIB', 'LIB', 'LIB', '{$codigoVendedor}', '{$lineaId}',
                    0, 0, 'FPN', '', '{$producto->codigo_producto}',
                    {$udtrpr}, {$rludpr}, {$producto->cantidad}, 0, 0, 0, '{$ud01pr}',
                    {$producto->cantidad}, 0, 0, 0, '{$ud02pr}',
                    'TABPP01P', '$', 'N', 1,
                    {$producto->precio_unitario}, {$producto->precio_unitario}, {$precioConIVARedondeado}, {$precioConIVARedondeado},
                    {$subtotalConDescuento}, {$subtotalConDescuento},
                    '{$producto->nombre_producto}'
                )
            ";
            
            if (ejecutarInsert($insertPrecios, "Campos de precios")) {
                echo "✅ Campos de precios OK\n\n";
                
                // PRUEBA 5: Agregar campos de descuentos e IVA
                echo "=== PRUEBA 5: Agregando campos de descuentos e IVA ===\n";
                $insertDescuentos = "
                    INSERT INTO MAEDDO (
                        IDMAEEDO, ARCHIRST, IDRST, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI,
                        LILG, NULIDO, SULIDO, LUVTLIDO, BOSULIDO, KOFULIDO, NULILG,
                        PRCT, TICT, TIPR, NUSEPR, KOPRCT,
                        UDTRPR, RLUDPR, CAPRCO1, CAPRAD1, CAPREX1, CAPRNC1, UD01PR,
                        CAPRCO2, CAPRAD2, CAPREX2, CAPRNC2, UD02PR,
                        KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                        PPPRNELT, PPPRNE, PPPRBRLT, PPPRBR,
                        NUDTLI, PODTGLLI, VADTNELI, VADTBRLI,
                        POIVLI, VAIVLI, NUIMLI, POIMGLLI, VAIMLI,
                        VANELI, VABRLI, TIGELI,
                        NOKOPR
                    ) VALUES (
                        {$siguienteId}, 0, 0, '01', 'NVV', '{$nudoFormateado}',
                        '{$cotizacion->cliente_codigo}', '{$sucursalCliente}', '{$cotizacion->cliente_codigo}',
                        'SI', '{$lineaId}', 'LIB', 'LIB', 'LIB', '{$codigoVendedor}', '{$lineaId}',
                        0, 0, 'FPN', '', '{$producto->codigo_producto}',
                        {$udtrpr}, {$rludpr}, {$producto->cantidad}, 0, 0, 0, '{$ud01pr}',
                        {$producto->cantidad}, 0, 0, 0, '{$ud02pr}',
                        'TABPP01P', '$', 'N', 1,
                        {$producto->precio_unitario}, {$producto->precio_unitario}, {$precioConIVARedondeado}, {$precioConIVARedondeado},
                        " . ($porcentajeDescuento > 0 ? 1 : 0) . ", {$porcentajeDescuento}, {$valorDescuento}, 0,
                        {$porcentajeIVA}, {$valorIVA}, 0, 0, 0,
                        {$subtotalConDescuento}, {$subtotalConDescuento}, 'I',
                        '{$producto->nombre_producto}'
                    )
                ";
                
                if (ejecutarInsert($insertDescuentos, "Campos de descuentos e IVA")) {
                    echo "✅ Campos de descuentos e IVA OK\n\n";
                    
                    // PRUEBA 6: Agregar campos de fechas
                    echo "=== PRUEBA 6: Agregando campos de fechas ===\n";
                    $insertFechas = "
                        INSERT INTO MAEDDO (
                            IDMAEEDO, ARCHIRST, IDRST, EMPRESA, TIDO, NUDO, ENDO, SUENDO, ENDOFI,
                            LILG, NULIDO, SULIDO, LUVTLIDO, BOSULIDO, KOFULIDO, NULILG,
                            PRCT, TICT, TIPR, NUSEPR, KOPRCT,
                            UDTRPR, RLUDPR, CAPRCO1, CAPRAD1, CAPREX1, CAPRNC1, UD01PR,
                            CAPRCO2, CAPRAD2, CAPREX2, CAPRNC2, UD02PR,
                            KOLTPR, MOPPPR, TIMOPPPR, TAMOPPPR,
                            PPPRNELT, PPPRNE, PPPRBRLT, PPPRBR,
                            NUDTLI, PODTGLLI, VADTNELI, VADTBRLI,
                            POIVLI, VAIVLI, NUIMLI, POIMGLLI, VAIMLI,
                            VANELI, VABRLI, TIGELI,
                            FEEMLI, FEERLI, PPPRPM,
                            NOKOPR
                        ) VALUES (
                            {$siguienteId}, 0, 0, '01', 'NVV', '{$nudoFormateado}',
                            '{$cotizacion->cliente_codigo}', '{$sucursalCliente}', '{$cotizacion->cliente_codigo}',
                            'SI', '{$lineaId}', 'LIB', 'LIB', 'LIB', '{$codigoVendedor}', '{$lineaId}',
                            0, 0, 'FPN', '', '{$producto->codigo_producto}',
                            {$udtrpr}, {$rludpr}, {$producto->cantidad}, 0, 0, 0, '{$ud01pr}',
                            {$producto->cantidad}, 0, 0, 0, '{$ud02pr}',
                            'TABPP01P', '$', 'N', 1,
                            {$producto->precio_unitario}, {$producto->precio_unitario}, {$precioConIVARedondeado}, {$precioConIVARedondeado},
                            " . ($porcentajeDescuento > 0 ? 1 : 0) . ", {$porcentajeDescuento}, {$valorDescuento}, 0,
                            {$porcentajeIVA}, {$valorIVA}, 0, 0, 0,
                            {$subtotalConDescuento}, {$subtotalConDescuento}, 'I',
                            GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}', 0,
                            '{$producto->nombre_producto}'
                        )
                    ";
                    
                    if (ejecutarInsert($insertFechas, "Campos de fechas")) {
                        echo "✅ Campos de fechas OK\n\n";
                        echo "=== TODAS LAS PRUEBAS PASARON ===\n";
                        echo "El problema NO está en los campos básicos\n";
                        echo "El problema debe estar en los campos adicionales comentados\n";
                    } else {
                        echo "❌ ERROR en campos de fechas\n";
                    }
                } else {
                    echo "❌ ERROR en campos de descuentos e IVA\n";
                }
            } else {
                echo "❌ ERROR en campos de precios\n";
            }
        } else {
            echo "❌ ERROR en campos de unidades\n";
        }
    } else {
        echo "❌ ERROR en campos de identificación\n";
    }
} else {
    echo "❌ ERROR en insert básico\n";
}

function ejecutarInsert($sql, $nombre) {
    echo "Ejecutando: {$nombre}...\n";
    
    $tempFile = tempnam(sys_get_temp_dir(), 'sql_');
    file_put_contents($tempFile, $sql . "\ngo\nquit");
    
    $command = "tsql -H " . $_ENV['SQLSRV_EXTERNAL_HOST'] . " -p " . $_ENV['SQLSRV_EXTERNAL_PORT'] . " -U " . $_ENV['SQLSRV_EXTERNAL_USERNAME'] . " -P " . $_ENV['SQLSRV_EXTERNAL_PASSWORD'] . " -D " . $_ENV['SQLSRV_EXTERNAL_DATABASE'] . " < {$tempFile} 2>&1";
    
    $startTime = microtime(true);
    $result = shell_exec($command);
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    unlink($tempFile);
    
    echo "Duración: " . round($duration, 2) . " segundos\n";
    
    if (str_contains($result, 'error') || str_contains($result, 'Msg')) {
        echo "❌ ERROR: " . substr($result, 0, 200) . "...\n";
        return false;
    }
    
    return true;
}

?>
