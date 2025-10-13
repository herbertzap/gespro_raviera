# Correcciones Realizadas al Insert SQL Server

## Fecha: 8 de Octubre 2025

---

## Problema Encontrado

Al ejecutar la aprobación de Picking para la NVV ID 16, se detectó que los INSERT a SQL Server **no se estaban ejecutando realmente**, aunque el código reportaba éxito.

### Síntomas:
- El método `insertarEnSQLServer()` devolvía `success = true`
- Los logs mostraban "insertado correctamente"
- **PERO** las queries a SQL Server mostraban 0 registros insertados

---

## Causa Raíz

### 1. Error de IDENTITY_INSERT
```
Msg 544 (severity 16, state 1) from SERVERRANDOM Line 2:
"Cannot insert explicit value for identity column in table 'MAEEDO' 
when IDENTITY_INSERT is set to OFF."
```

**Problema:** 
- La columna `IDMAEEDO` es una columna IDENTITY (autoincremental)
- No se puede insertar un valor explícito sin activar `SET IDENTITY_INSERT MAEEDO ON`

### 2. Detección de Errores Incorrecta
```php
// ANTES (INCORRECTO):
if (str_contains($result, 'error')) {  // Busca "error" en minúsculas
    throw new \Exception(...);
}
```

**Problema:**
- SQL Server devuelve errores con "Msg" y "Error" (con mayúscula)
- La búsqueda de "error" (minúsculas) no detectaba los errores
- El código creía que todo se había insertado correctamente

---

## Soluciones Implementadas

### 1. Activar IDENTITY_INSERT en MAEEDO

**ANTES:**
```sql
INSERT INTO MAEEDO (
    IDMAEEDO, TIDO, NUDO, ...
) VALUES (
    {$siguienteId}, 'NVV', ...
)
```

**DESPUÉS:**
```sql
SET IDENTITY_INSERT MAEEDO ON

INSERT INTO MAEEDO (
    IDMAEEDO, TIDO, NUDO, ...
) VALUES (
    {$siguienteId}, 'NVV', ...
)

SET IDENTITY_INSERT MAEEDO OFF
```

### 2. Mejorar Detección de Errores

**ANTES:**
```php
if (str_contains($result, 'error')) {  // ❌ Solo minúsculas
    throw new \Exception(...);
}
```

**DESPUÉS:**
```php
if (str_contains($result, 'Msg') || str_contains($result, 'Error')) {  // ✅ Detecta errores SQL Server
    throw new \Exception(...);
}
```

### 3. Archivos Modificados

**Archivo:** `app/Http/Controllers/AprobacionController.php`

**Cambios realizados:**

1. **Línea 256**: Agregado `SET IDENTITY_INSERT MAEEDO ON` antes del INSERT
2. **Línea 279**: Agregado `SET IDENTITY_INSERT MAEEDO OFF` después del INSERT
3. **Línea 290**: Cambió detección de errores en INSERT MAEEDO
4. **Línea 336**: Cambió detección de errores en INSERT MAEDDO (detalles)
5. **Línea 360**: Cambió detección de errores en INSERT MAEEDOOB (observaciones)
6. **Línea 383**: Cambió detección de errores en INSERT MAEVEN (vendedor)
7. **Línea 405**: Cambió detección de errores en UPDATE MAEST (stock)
8. **Línea 428**: Cambió detección de errores en UPDATE MAEPR (productos)

---

## Queries SQL Ejecutados

### Para la NVV ID 16:

#### 1. SELECT para obtener correlativo:
```sql
SELECT TOP 1 ISNULL(MAX(IDMAEEDO), 0) + 1 AS siguiente_id 
FROM MAEEDO 
WHERE EMPRESA = '01'
```
**Resultado esperado:** 119724 (o el siguiente disponible)

#### 2. INSERT en MAEEDO:
```sql
SET IDENTITY_INSERT MAEEDO ON

INSERT INTO MAEEDO (
    IDMAEEDO, TIDO, NUDO, ENDO, SUENDO, FEEMDO, FE01VEDO, FEULVEDO,
    VABRDO, VAABDO, EMPRESA, KOFU, SUDO, ESDO, TIDOEXTE, NUDOEXTE, ...
) VALUES (
    119724, 'NVV', 119724, '77192175', '001', GETDATE(), '2025-11-07', '2025-11-07',
    33140, 0, '01', 'LCB', '001', 'N', 'NVV', 119724, ...
)

SET IDENTITY_INSERT MAEEDO OFF
```

#### 3. INSERT en MAEDDO (4 productos):
```sql
-- Producto 1: ALARGADOR
INSERT INTO MAEDDO (...) VALUES (119724, 1, '0550616000000', 'ALARGADOR 6 POS. 5 MTS GMAX', 6, 7620, ...)

-- Producto 2: LATEX BLANCO
INSERT INTO MAEDDO (...) VALUES (119724, 2, 'LBBL000000000', 'LATEX BOLSA BLANCO', 3, 1690, ...)

-- Producto 3: LATEX LA PALOMA
INSERT INTO MAEDDO (...) VALUES (119724, 3, 'LABLANPALOMA0', 'LATEX BOLSA 2,8K BLANCO LA PALOMA', 2, 1490, ...)

-- Producto 4: CEMENTO
INSERT INTO MAEDDO (...) VALUES (119724, 4, '2700900110000', 'CEMENTO BLANCO KILO UN', 2, 1115, ...)
```

#### 4. INSERT en MAEEDOOB:
```sql
INSERT INTO MAEEDOOB (IDMAEEDO, IDMAEDOOB, OBSERVACION, EMPRESA) 
VALUES (119724, 1, 'NVV generada desde sistema web - ID: 16', '01')
```

#### 5. INSERT en MAEVEN:
```sql
INSERT INTO MAEVEN (IDMAEEDO, KOFU, NOKOFU, EMPRESA) 
VALUES (119724, 'LCB', 'LUIS CASANGA BERRIOS', '01')
```

#### 6. UPDATE en MAEST (4 productos):
```sql
UPDATE MAEST SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + 6 WHERE KOPR = '0550616000000' AND EMPRESA = '01'
UPDATE MAEST SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + 3 WHERE KOPR = 'LBBL000000000' AND EMPRESA = '01'
UPDATE MAEST SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + 2 WHERE KOPR = 'LABLANPALOMA0' AND EMPRESA = '01'
UPDATE MAEST SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + 2 WHERE KOPR = '2700900110000' AND EMPRESA = '01'
```

#### 7. UPDATE en MAEPR (4 productos):
```sql
UPDATE MAEPR SET ULTIMACOMPRA = GETDATE() WHERE KOPR = '0550616000000'
UPDATE MAEPR SET ULTIMACOMPRA = GETDATE() WHERE KOPR = 'LBBL000000000'
UPDATE MAEPR SET ULTIMACOMPRA = GETDATE() WHERE KOPR = 'LABLANPALOMA0'
UPDATE MAEPR SET ULTIMACOMPRA = GETDATE() WHERE KOPR = '2700900110000'
```

#### 8. UPDATE en MySQL (tabla productos - 4 productos):
```php
// Incrementar stock_comprometido y recalcular stock_disponible
UPDATE productos SET stock_comprometido = stock_comprometido + 6 WHERE KOPR = '0550616000000'
UPDATE productos SET stock_comprometido = stock_comprometido + 3 WHERE KOPR = 'LBBL000000000'
UPDATE productos SET stock_comprometido = stock_comprometido + 2 WHERE KOPR = 'LABLANPALOMA0'
UPDATE productos SET stock_comprometido = stock_comprometido + 2 WHERE KOPR = '2700900110000'
```

#### 9. UPDATE en MySQL (tabla cotizaciones):
```php
UPDATE cotizaciones SET numero_nvv = 119724 WHERE id = 16
```

---

## Resumen de Lógica Aplicada

### Flujo completo:
1. **Obtener correlativo**: SELECT MAX + 1 desde MAEEDO
2. **Activar IDENTITY_INSERT**: Permitir insert explícito en columna IDENTITY
3. **Insertar encabezado**: INSERT en MAEEDO con todos los datos de la NVV
4. **Desactivar IDENTITY_INSERT**: Volver a modo automático
5. **Insertar detalles**: INSERT en MAEDDO por cada producto (loop)
6. **Insertar observaciones**: INSERT en MAEEDOOB (opcional, no crítico)
7. **Insertar vendedor**: INSERT en MAEVEN (opcional, no crítico)
8. **Actualizar stock SQL**: UPDATE en MAEST incrementando STOCKSALIDA
9. **Actualizar productos SQL**: UPDATE en MAEPR actualizando ULTIMACOMPRA
10. **Actualizar stock MySQL**: UPDATE en tabla productos local
11. **Guardar número NVV**: UPDATE en cotizaciones con el numero_nvv

### Manejo de errores:
- **Críticos** (detienen proceso): MAEEDO, MAEDDO
- **No críticos** (solo log): MAEEDOOB, MAEVEN, MAEST, MAEPR
- **Detección**: Buscar "Msg" o "Error" en resultado de tsql

---

## Pendiente para Pruebas

### Para validar las correcciones:

1. **Crear una nueva NVV** (no usar la ID 16 que ya fue aprobada)
2. **Aprobar por Supervisor** (si requiere)
3. **Aprobar por Compras**
4. **Aprobar por Picking** → Aquí se ejecuta el insert a SQL Server
5. **Verificar en SQL Server** que se crearon los registros:
   ```sql
   SELECT * FROM MAEEDO WHERE TIDO = 'NVV' ORDER BY IDMAEEDO DESC
   SELECT * FROM MAEDDO WHERE IDMAEEDO = [último_id]
   ```

---

## Notas Adicionales

### Columnas IDENTITY en SQL Server:
- `MAEEDO.IDMAEEDO`: Es IDENTITY, necesita `SET IDENTITY_INSERT ON`
- `MAEDDO.IDMAEDDO`: NO es IDENTITY (es solo un número de línea 1, 2, 3...)

### Valores por defecto:
- `EMPRESA`: Siempre '01'
- `SUDO/SUENDO`: Siempre '001'
- `ESDO`: Siempre 'N' (nuevo)
- `TIDO/TIDOEXTE`: Siempre 'NVV'
- `FEEMDO`: GETDATE() (fecha actual)
- `FE01VEDO/FEULVEDO`: +30 días desde hoy

### Campos importantes:
- `ENDO`: Código del cliente
- `KOFU`: Código del vendedor  
- `VABRDO`: Total de la NVV
- `KOPRCT`: Código del producto
- `CAPRCO1`: Cantidad del producto
- `PPPRNE`: Precio unitario
- `STOCKSALIDA`: Stock comprometido (pendiente de salir)

---

**Estado:** ✅ Correcciones implementadas, pendiente de prueba con nueva NVV  
**Última actualización:** 8 de Octubre 2025


