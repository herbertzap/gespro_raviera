# Flujo de Aprobación Picking y Insert SQL Server

## Resumen de Cambios Implementados

### 1. Corrección del Método `aprobarPicking` (AprobacionController.php)

**Cambios realizados:**
- ✅ Cambiado de respuestas JSON a redirects con mensajes flash
- ✅ Validación de stock real opcional (no obligatoria)
- ✅ Logs detallados en cada paso del proceso
- ✅ Registro en historial de aprobaciones
- ✅ Manejo de errores con mensajes descriptivos

**Flujo actual:**
```
1. Usuario Picking hace clic en "Aprobar"
2. JavaScript envía POST a /aprobaciones/{id}/picking
3. Controlador valida permisos y estado
4. (Opcional) Valida stock real en SQL Server
5. Aprueba en MySQL (estado -> aprobada_picking)
6. Inserta en SQL Server (MAEEDO, MAEDDO, MAEVEN, MAEEDOOB)
7. Actualiza MAEST (STOCKSALIDA)
8. Actualiza stock en MySQL (stock_comprometido)
9. Guarda numero_nvv en cotizacion
10. Registra en historial
11. Redirige con mensaje de éxito
```

### 2. Corrección de Nombres de Campos

**Problema:** Inconsistencia entre `producto_codigo` y `codigo_producto`

**Solución:** Estandarizado a `codigo_producto` en todo el código:
- ✅ Insert MAEDDO: `$producto->codigo_producto`
- ✅ Update MAEST: `$producto->codigo_producto`
- ✅ Update MAEPR: `$producto->codigo_producto`
- ✅ Update MySQL: `$producto->codigo_producto`

### 3. Función JavaScript de Aprobación (show.blade.php)

**Cambios realizados:**
```javascript
function aprobarNota(notaId, tipo) {
    // Confirmar aprobación
    if (!confirm('¿Estás seguro de aprobar esta nota de venta?')) {
        return;
    }

    // Crear formulario con campos necesarios
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    
    // Token CSRF
    form.appendChild(csrfToken);
    
    // Para picking: agregar validar_stock_real
    if (tipo === 'picking') {
        const validarStock = document.createElement('input');
        validarStock.type = 'hidden';
        validarStock.name = 'validar_stock_real';
        validarStock.value = '1';
        form.appendChild(validarStock);
    }
    
    // Comentarios vacíos
    const comentarios = document.createElement('input');
    comentarios.type = 'hidden';
    comentarios.name = 'comentarios';
    comentarios.value = '';
    form.appendChild(comentarios);
    
    form.submit();
}
```

### 4. Comando de Prueba `test:insert-nvv`

**Uso:**
```bash
php artisan test:insert-nvv {cotizacion_id}
```

**Funcionalidad:**
- Muestra información completa de la cotización
- Verifica conexión a SQL Server
- Obtiene siguiente ID disponible
- Genera y muestra todos los SQL queries
- Permite confirmar antes de ejecutar
- Ejecuta el insert completo si se confirma

**Ejemplo:**
```bash
php artisan test:insert-nvv 16
```

## Estructura de Tablas SQL Server

### MAEEDO (Encabezado de Documento)
```sql
IDMAEEDO    - ID único del documento
TIDO        - Tipo de documento ('NVV')
NUDO        - Número de documento
ENDO        - Código de cliente
SUENDO      - Sucursal ('001')
FEEMDO      - Fecha de emisión
FE01VEDO    - Fecha de vencimiento
VABRDO      - Valor bruto
EMPRESA     - Código de empresa ('01')
KOFU        - Código de vendedor
SUDO        - Sucursal de documento ('001')
ESDO        - Estado ('N' = Normal)
```

### MAEDDO (Detalle de Documento)
```sql
IDMAEEDO    - ID del documento (FK a MAEEDO)
IDMAEDDO    - ID de línea (1, 2, 3...)
KOPRCT      - Código de producto
NOKOPR      - Nombre de producto
CAPRCO1     - Cantidad
PPPRNE      - Precio unitario
VANELI      - Valor neto de línea
VABRLI      - Valor bruto de línea
EMPRESA     - Código de empresa ('01')
TIDO        - Tipo de documento ('NVV')
```

### MAEST (Stock de Productos)
```sql
KOPR         - Código de producto
STOCKSALIDA  - Stock comprometido/salida
EMPRESA      - Código de empresa ('01')
```

### MAEVEN (Vendedor del Documento)
```sql
IDMAEEDO    - ID del documento (FK a MAEEDO)
KOFU        - Código de vendedor
NOKOFU      - Nombre de vendedor
EMPRESA     - Código de empresa ('01')
```

### MAEEDOOB (Observaciones del Documento)
```sql
IDMAEEDO     - ID del documento (FK a MAEEDO)
IDMAEDOOB    - ID de observación
OBSERVACION  - Texto de observación
EMPRESA      - Código de empresa ('01')
```

## Actualización de Stock

### SQL Server (MAEST)
```sql
UPDATE MAEST 
SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + {cantidad}
WHERE KOPR = '{codigo_producto}' AND EMPRESA = '01'
```

**STOCKSALIDA:** Representa el stock comprometido (pendiente de despacho)

### MySQL (productos)
```php
$productoLocal->stock_comprometido += $cantidad;
$productoLocal->stock_disponible = $productoLocal->stock_fisico - $productoLocal->stock_comprometido;
```

**Campos:**
- `stock_fisico`: Stock real en bodega (sincronizado desde SQL Server)
- `stock_comprometido`: Stock reservado por NVVs pendientes
- `stock_disponible`: Stock físico - Stock comprometido

## Flujo Completo de Aprobación

### Estado Inicial
```
Cotización creada
  ↓
estado_aprobacion: 'pendiente'
requiere_aprobacion: true
```

### Aprobación Supervisor
```
Supervisor aprueba
  ↓
¿Tiene problemas de stock?
  → SI: estado_aprobacion: 'aprobada_supervisor' (va a Compras)
  → NO: estado_aprobacion: 'pendiente_picking' (va directo a Picking)
```

### Aprobación Compras (si hay problemas de stock)
```
Compras aprueba
  ↓
estado_aprobacion: 'pendiente_picking'
```

### Aprobación Picking (FINAL)
```
Picking aprueba
  ↓
1. Valida stock real (opcional)
2. estado_aprobacion: 'aprobada_picking'
3. INSERT en SQL Server:
   - MAEEDO (encabezado)
   - MAEDDO (detalles)
   - MAEVEN (vendedor)
   - MAEEDOOB (observaciones)
4. UPDATE MAEST (stock comprometido)
5. UPDATE MySQL productos (stock_comprometido)
6. Guarda numero_nvv en cotizacion
7. Registra en historial
  ↓
NVV creada en sistema ✅
```

## Verificación de Funcionamiento

### 1. Verificar Estado de Cotización
```bash
php artisan tinker --execute="
\$cotizacion = \App\Models\Cotizacion::find(16);
echo 'Estado: ' . \$cotizacion->estado_aprobacion . PHP_EOL;
echo 'Puede aprobar picking: ' . (\$cotizacion->puedeAprobarPicking() ? 'SI' : 'NO') . PHP_EOL;
"
```

### 2. Probar Insert (sin ejecutar)
```bash
php artisan test:insert-nvv 16
```

### 3. Ver Logs en Tiempo Real
```bash
tail -f storage/logs/laravel.log | grep -i "picking\|insert\|nvv"
```

### 4. Verificar en SQL Server
```sql
-- Ver última NVV creada
SELECT TOP 1 * FROM MAEEDO 
WHERE TIDO = 'NVV' 
ORDER BY IDMAEEDO DESC

-- Ver detalles de una NVV
SELECT * FROM MAEDDO 
WHERE IDMAEEDO = 119724

-- Ver stock comprometido
SELECT KOPR, STOCKSALIDA 
FROM MAEST 
WHERE KOPR IN ('0550616000000', 'LBBL000000000')
```

## Troubleshooting

### Problema: El botón no hace nada
**Solución:** 
- Verificar que el JavaScript se cargó correctamente
- Abrir consola del navegador (F12) y buscar errores
- Verificar que el token CSRF es válido

### Problema: Error de conexión SQL Server
**Solución:**
- Verificar variables de entorno (.env)
- Probar conexión con: `php artisan test:insert-nvv {id}`
- Verificar firewall y permisos de red

### Problema: Error "Column not found"
**Solución:**
- Verificar que los nombres de campos coinciden con la BD
- Usar `codigo_producto` (no `producto_codigo`)

### Problema: Stock no se actualiza
**Solución:**
- Verificar que el UPDATE MAEST se ejecuta correctamente
- Verificar que el producto existe en MAEST
- Verificar que EMPRESA = '01'

## Logs Importantes

### Inicio de Aprobación
```
[local.INFO]: Iniciando aprobación por picking para cotización {id}
```

### Aprobación en MySQL
```
[local.INFO]: Cotización aprobada en MySQL, iniciando insert en SQL Server
```

### Insert SQL Server
```
[local.INFO]: Siguiente ID para MAEEDO: {id}
[local.INFO]: Encabezado MAEEDO insertado correctamente
[local.INFO]: Detalles MAEDDO insertados correctamente
[local.INFO]: Vendedor MAEVEN insertado correctamente
[local.INFO]: Stock comprometido MAEST actualizado correctamente
```

### Stock MySQL
```
[local.INFO]: Stock MySQL actualizado para {codigo}: Comprometido +{cantidad}, Disponible: {disponible}
```

### Finalización
```
[local.INFO]: Nota de venta {id} aprobada por picking {user_id} y insertada en SQL Server con ID {nvv_id}
```

## Archivos Modificados

1. `app/Http/Controllers/AprobacionController.php`
   - Método `aprobarPicking()`
   - Método `insertarEnSQLServer()`

2. `resources/views/aprobaciones/show.blade.php`
   - Función JavaScript `aprobarNota()`

3. `app/Console/Commands/TestInsertNVV.php` (nuevo)
   - Comando de prueba y debugging

4. `database/migrations/2025_10_07_195722_add_numero_nvv_to_cotizaciones_table.php`
   - Campo `numero_nvv` en tabla `cotizaciones`

5. `app/Models/Cotizacion.php`
   - Campo `numero_nvv` en `$fillable`

## Próximos Pasos

1. ✅ Probar aprobación desde navegador
2. ✅ Verificar logs durante el proceso
3. ✅ Confirmar insert en SQL Server
4. ✅ Verificar actualización de stock
5. ✅ Validar número de NVV guardado
6. ✅ Confirmar registro en historial

## Contacto y Soporte

Para debugging adicional:
- Revisar logs: `storage/logs/laravel.log`
- Ejecutar comando de prueba: `php artisan test:insert-nvv {id}`
- Verificar estado: `php artisan tinker`
