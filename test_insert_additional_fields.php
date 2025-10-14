<?php

require_once 'vendor/autoload.php';

// Inicializar Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PRUEBA DE CAMPOS ADICIONALES MAEDDO ===\n";
echo "Probando campos comentados uno por uno\n\n";

// Obtener datos de la cotización 42
$cotizacion = \App\Models\Cotizacion::find(42);
$producto = $cotizacion->productos->first();

if (!$cotizacion || !$producto) {
    echo "ERROR: No se encontró la cotización 42 o sus productos\n";
    exit;
}

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
$siguienteId = 158893;
$nudoFormateado = '0000037580';
$lineaId = str_pad('1', 5, '0', STR_PAD_LEFT);

echo "Siguiente ID: {$siguienteId}\n";
echo "Siguiente NUDO: {$nudoFormateado}\n\n";

// PRUEBA 7: Agregar campos EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP
echo "=== PRUEBA 7: Campos EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP ===\n";
$insertCampos1 = "
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
        EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP,
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
        '', '', '', '', '', 0,
        GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}', 0,
        '{$producto->nombre_producto}'
    )
";

if (ejecutarInsert($insertCampos1, "Campos EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP")) {
    echo "✅ Campos EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP OK\n\n";
    
    // PRUEBA 8: Agregar campos OPERACION, CODMAQ, ESLIDO
    echo "=== PRUEBA 8: Campos OPERACION, CODMAQ, ESLIDO ===\n";
    $siguienteId++;
    $nudoFormateado = '0000037581';
    
    $insertCampos2 = "
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
            EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP,
            OPERACION, CODMAQ, ESLIDO,
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
            '', '', '', '', '', 0,
            '', '', 0,
            GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}', 0,
            '{$producto->nombre_producto}'
        )
    ";
    
    if (ejecutarInsert($insertCampos2, "Campos OPERACION, CODMAQ, ESLIDO")) {
        echo "✅ Campos OPERACION, CODMAQ, ESLIDO OK\n\n";
        
        // PRUEBA 9: Agregar campos PPPRNERE1, PPPRNERE2
        echo "=== PRUEBA 9: Campos PPPRNERE1, PPPRNERE2 ===\n";
        $siguienteId++;
        $nudoFormateado = '0000037582';
        
        $insertCampos3 = "
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
                EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP,
                OPERACION, CODMAQ, ESLIDO,
                PPPRNERE1, PPPRNERE2,
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
                '', '', '', '', '', 0,
                '', '', 0,
                " . ($producto->precio_unitario - ($valorDescuento / $producto->cantidad)) . ", " . ($producto->precio_unitario - ($valorDescuento / $producto->cantidad)) . ",
                GETDATE(), '{$cotizacion->fecha_despacho->format('Y-m-d H:i:s')}', 0,
                '{$producto->nombre_producto}'
            )
        ";
        
        if (ejecutarInsert($insertCampos3, "Campos PPPRNERE1, PPPRNERE2")) {
            echo "✅ Campos PPPRNERE1, PPPRNERE2 OK\n\n";
            echo "=== PRUEBAS BÁSICAS COMPLETADAS ===\n";
            echo "Los campos básicos adicionales funcionan correctamente\n";
            echo "El problema puede estar en campos más complejos\n";
        } else {
            echo "❌ ERROR en campos PPPRNERE1, PPPRNERE2\n";
        }
    } else {
        echo "❌ ERROR en campos OPERACION, CODMAQ, ESLIDO\n";
    }
} else {
    echo "❌ ERROR en campos EMPREPA, TIDOPA, NUDOPA, ENDOPA, NULIDOPA, LLEVADESP\n";
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
