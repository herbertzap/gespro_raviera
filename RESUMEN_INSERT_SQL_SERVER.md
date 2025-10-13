# Resumen: Inserción a SQL Server después de Aprobación de Picking

## Fecha: 8 de Octubre 2025

---

## 1. Flujo de Aprobación

Cuando el usuario con rol **Picking** aprueba una NVV (Nota de Venta), se ejecuta el siguiente flujo:

1. **Aprobación en MySQL** (`aprobarPorPicking()`)
2. **Inserción en SQL Server** (`insertarEnSQLServer()`)
3. **Actualización de stock en MySQL**

### Método Principal: `aprobarPicking()`
**Ubicación:** `app/Http/Controllers/AprobacionController.php` (líneas 149-213)

```php
public function aprobarPicking(Request $request, $id)
{
    // 1. Validar permisos y estado
    // 2. Opcional: Validar stock real
    // 3. Aprobar en MySQL
    $cotizacion->aprobarPorPicking($user->id, $request->comentarios);
    
    // 4. Insertar en SQL Server
    $resultado = $this->insertarEnSQLServer($cotizacion);
    
    // 5. Retornar éxito o error
}
```

---

## 2. Proceso de Inserción en SQL Server

### Método: `insertarEnSQLServer()`
**Ubicación:** `app/Http/Controllers/AprobacionController.php` (líneas 218-462)

### Paso 1: Obtener Correlativo MAEEDO
```sql
SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id 
FROM MAEEDO 
WHERE EMPRESA = '01'
```
- Obtiene el siguiente ID disponible para la tabla `MAEEDO`
- Este ID se usa como correlativo para la NVV

### Paso 2: INSERT en MAEEDO (Encabezado del Documento)
```sql
INSERT INTO MAEEDO (
    IDMAEEDO,           -- ID correlativo
    TIDO,               -- Tipo documento: 'NVV'
    NUDO,               -- Número documento (mismo que IDMAEEDO)
    ENDO,               -- Entidad (código cliente)
    SUENDO,             -- Sub-entidad: '001'
    FEEMDO,             -- Fecha emisión: GETDATE()
    FE01VEDO,           -- Fecha vencimiento: +30 días
    FEULVEDO,           -- Fecha última vez: +30 días
    VABRDO,             -- Valor bruto (total)
    VAABDO,             -- Valor abono: 0
    EMPRESA,            -- Empresa: '01'
    KOFU,               -- Código funcionario (vendedor)
    SUDO,               -- Sucursal: '001'
    ESDO,               -- Estado: 'N' (nuevo)
    TIDOEXTE,           -- Tipo documento externo: 'NVV'
    NUDOEXTE,           -- Número documento externo
    ... (múltiples campos KOFU*)
) VALUES (...)
```

**Campos importantes:**
- `IDMAEEDO`: ID único del documento
- `TIDO`: Siempre 'NVV' (Nota de Venta)
- `NUDO`: Mismo valor que IDMAEEDO
- `ENDO`: Código del cliente (`$cotizacion->cliente_codigo`)
- `VABRDO`: Total de la cotización (`$cotizacion->total`)
- `KOFU`: Código del vendedor (`$cotizacion->user->codigo_vendedor`)

### Paso 3: INSERT en MAEDDO (Detalle del Documento)
Se ejecuta un INSERT por cada producto de la cotización:

```sql
INSERT INTO MAEDDO (
    IDMAEEDO,           -- ID del encabezado
    IDMAEDDO,           -- ID de línea (1, 2, 3, ...)
    KOPRCT,             -- Código producto
    NOKOPR,             -- Nombre producto
    CAPRCO1,            -- Cantidad producto (UD1)
    PPPRNE,             -- Precio unitario neto
    CAPRCO2,            -- Cantidad UD2: 0
    PPPRNE2,            -- Precio UD2: 0
    EMPRESA,            -- Empresa: '01'
    TIDO,               -- Tipo documento: 'NVV'
    NUDO,               -- Número documento
    ENDO,               -- Entidad (cliente)
    SUENDO,             -- Sub-entidad: '001'
    FEEMLI,             -- Fecha emisión línea: GETDATE()
    FEULVE,             -- Fecha última vez: +30 días
    VANELI,             -- Valor neto línea
    VABRLI,             -- Valor bruto línea
    VAABLI,             -- Valor abono línea: 0
    ESDO,               -- Estado: 'N'
    LILG,               -- Línea legalizada: 'SI'
    ... (múltiples campos)
) VALUES (...)
```

**Campos importantes por producto:**
- `KOPRCT`: Código del producto
- `NOKOPR`: Nombre del producto
- `CAPRCO1`: Cantidad solicitada
- `PPPRNE`: Precio unitario
- `VANELI` / `VABRLI`: Cantidad × Precio unitario

### Paso 4: INSERT en MAEEDOOB (Observaciones)
```sql
INSERT INTO MAEEDOOB (
    IDMAEEDO,           -- ID del encabezado
    IDMAEDOOB,          -- ID observación: 1
    OBSERVACION,        -- Texto observación
    EMPRESA             -- Empresa: '01'
) VALUES (
    {$siguienteId}, 
    1, 
    'NVV generada desde sistema web - ID: {$cotizacion->id}', 
    '01'
)
```

### Paso 5: INSERT en MAEVEN (Vendedor)
```sql
INSERT INTO MAEVEN (
    IDMAEEDO,           -- ID del encabezado
    KOFU,               -- Código vendedor
    NOKOFU,             -- Nombre vendedor
    EMPRESA             -- Empresa: '01'
) VALUES (
    {$siguienteId}, 
    '{$codigoVendedor}', 
    '{$nombreVendedor}', 
    '01'
)
```

### Paso 6: UPDATE en MAEST (Stock Comprometido)
Para cada producto se actualiza el stock comprometido en SQL Server:

```sql
UPDATE MAEST 
SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + {$cantidad}
WHERE KOPR = '{$codigoProducto}' AND EMPRESA = '01'
```

**Importante:** `STOCKSALIDA` representa el stock comprometido (pendiente de salir).

### Paso 7: UPDATE en MAEPR (Última Compra)
Para cada producto se actualiza la fecha de última compra:

```sql
UPDATE MAEPR 
SET ULTIMACOMPRA = GETDATE()
WHERE KOPR = '{$codigoProducto}'
```

### Paso 8: UPDATE en MySQL (Stock Local)
Actualizar la tabla local `productos` en MySQL:

```php
$productoLocal = \App\Models\Producto::where('KOPR', $producto->codigo_producto)->first();

if ($productoLocal) {
    // Incrementar stock comprometido
    $productoLocal->stock_comprometido += $producto->cantidad;
    
    // Recalcular stock disponible
    $productoLocal->stock_disponible = $productoLocal->stock_fisico - $productoLocal->stock_comprometido;
    
    $productoLocal->save();
}
```

### Paso 9: Guardar número NVV en MySQL
```php
$cotizacion->numero_nvv = $siguienteId;
$cotizacion->save();
```

---

## 3. Resumen de Tablas Afectadas

### SQL Server:
1. **MAEEDO** (Encabezado): 1 INSERT
   - Documento principal con datos de la NVV
   
2. **MAEDDO** (Detalle): N INSERT (uno por producto)
   - Líneas de productos con cantidades y precios
   
3. **MAEEDOOB** (Observaciones): 1 INSERT
   - Observación indicando origen web
   
4. **MAEVEN** (Vendedor): 1 INSERT
   - Información del vendedor asignado
   
5. **MAEST** (Stock): N UPDATE (uno por producto)
   - Incrementa `STOCKSALIDA` (stock comprometido)
   
6. **MAEPR** (Productos): N UPDATE (uno por producto)
   - Actualiza fecha de última compra

### MySQL:
1. **cotizaciones**: 1 UPDATE
   - Guarda el `numero_nvv` generado
   - Estado cambia a `aprobada_picking`
   
2. **productos**: N UPDATE (uno por producto)
   - Incrementa `stock_comprometido`
   - Recalcula `stock_disponible`

---

## 4. Manejo de Errores

### Logs implementados:
```php
// Éxito
Log::info('Siguiente ID para MAEEDO: ' . $siguienteId);
Log::info('Encabezado MAEEDO insertado correctamente');
Log::info('Detalles MAEDDO insertados correctamente');
Log::info("Stock MySQL actualizado para {$producto->codigo_producto}");

// Advertencias (no críticas)
Log::warning('Error insertando observaciones MAEEDOOB: ' . $result);
Log::warning('Error insertando vendedor MAEVEN: ' . $result);

// Errores críticos
Log::error('Error en insertarEnSQLServer: ' . $e->getMessage());
throw $e; // Re-lanza la excepción
```

### Estrategia de errores:
- **MAEEDO e MAEDDO**: Errores críticos → detienen el proceso
- **MAEEDOOB y MAEVEN**: Errores no críticos → se registran pero continúa
- **MAEST y MAEPR**: Errores se registran pero no detienen el proceso

---

## 5. Ejemplo de Flujo Completo

### Datos de entrada:
```
Cotización ID: 16
Cliente: 78044580
Vendedor: 001 (Juan Pérez)
Total: $150,000
Productos:
  - LBCE000000000: 10 unidades × $15,000 = $150,000
```

### Ejecución:
```
1. Obtener correlativo → IDMAEEDO = 12345
2. INSERT MAEEDO → NVV 12345 creada para cliente 78044580
3. INSERT MAEDDO → Línea 1: LBCE000000000 × 10 unidades
4. INSERT MAEEDOOB → "NVV generada desde sistema web - ID: 16"
5. INSERT MAEVEN → Vendedor: 001 (Juan Pérez)
6. UPDATE MAEST → LBCE000000000: STOCKSALIDA += 10
7. UPDATE MAEPR → LBCE000000000: ULTIMACOMPRA = HOY
8. UPDATE productos (MySQL) → stock_comprometido += 10
9. UPDATE cotizaciones (MySQL) → numero_nvv = 12345
```

### Resultado:
```json
{
    "success": true,
    "nota_venta_id": 12345,
    "message": "NVV insertada correctamente en SQL Server"
}
```

---

## 6. Consideraciones Técnicas

### Uso de `tsql`:
- Se usa el comando `tsql` para ejecutar queries en SQL Server
- Cada query se escribe en un archivo temporal
- Se ejecuta vía `shell_exec()`
- Se elimina el archivo temporal después

### Parámetros de conexión:
```php
env('SQLSRV_EXTERNAL_HOST')
env('SQLSRV_EXTERNAL_PORT')
env('SQLSRV_EXTERNAL_USERNAME')
env('SQLSRV_EXTERNAL_PASSWORD')
env('SQLSRV_EXTERNAL_DATABASE')
```

### Detección de errores:
```php
if (str_contains($result, 'error')) {
    throw new \Exception('Error insertando...');
}
```

---

## 7. Pendientes y Mejoras Futuras

### Posibles mejoras:
1. **Transacciones**: Implementar rollback si falla algún paso
2. **Validación de duplicados**: Verificar que no exista la NVV antes de insertar
3. **Reintentos automáticos**: Si falla la conexión, reintentar N veces
4. **Notificaciones**: Enviar email/notificación al vendedor cuando se crea la NVV
5. **Sincronización bidireccional**: Verificar que la NVV se creó correctamente en SQL Server

### Consideraciones de seguridad:
- Sanitizar inputs antes de construir queries SQL
- Validar permisos antes de ejecutar inserts
- Encriptar credenciales en `.env`

---

## 8. Comandos Útiles para Debugging

### Verificar última NVV creada en SQL Server:
```bash
tsql -H host -p port -U user -P pass -D db -c "
SELECT TOP 1 * FROM MAEEDO 
WHERE TIDO = 'NVV' 
ORDER BY IDMAEEDO DESC
"
```

### Verificar stock comprometido de un producto:
```bash
tsql -H host -p port -U user -P pass -D db -c "
SELECT KOPR, STFI, STOCKSALIDA, (STFI - STOCKSALIDA) AS DISPONIBLE
FROM MAEST 
WHERE KOPR = 'LBCE000000000' AND EMPRESA = '01'
"
```

### Ver logs de Laravel:
```bash
tail -f storage/logs/laravel.log | grep "insertarEnSQLServer"
```

---

## 9. Contacto y Referencias

### Archivos relacionados:
- `app/Http/Controllers/AprobacionController.php`
- `app/Models/Cotizacion.php`
- `app/Models/Producto.php`
- `resources/views/aprobaciones/show.blade.php`

### Usuario de prueba:
- Email: `picking@gespro.test`
- Rol: Picking

### NVV de prueba:
- ID: 16
- URL: https://app.wuayna.com/aprobaciones/16

---

**Última actualización:** 8 de Octubre 2025  
**Autor:** Sistema Gespro Raviera  
**Estado:** ✅ Implementado y funcional


