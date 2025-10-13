# Análisis del Excel "Insert NVV.xlsx"

## Fecha: 8 de Octubre 2025

---

## 📋 ESTRUCTURA DETECTADA

### Hoja 1: MAEEDO (Encabezado)

**Columnas identificadas (primeras 30):**
1. IDMAEEDO - ID único del documento
2. EMPRESA - Código empresa
3. TIDO - Tipo documento
4. NUDO - Número documento
5. ENDO - Código cliente
6. SUENDO - Sub-entidad
7. ENDOFI - Entidad final
8. TIGEDO - Tipo generación documento
9. SUDO - Sucursal
10. LUVTDO - Lugar de venta
11. FEEMDO - Fecha emisión
12. KOFUDO - Código vendedor
13. ESDO - Estado documento
14. ESPGDO - Estado página documento
15. CAPRCO - Cantidad productos
16. CAPRAD - Cantidad productos adicionales
17. CAPREX - Cantidad productos extras
18. CAPRNC - Cantidad productos no conformes
19. MEARDO - Medio
20. MODO - Modo
21. TIMODO - Tipo modo
22. TAMODO - Tabla modo
23. NUCTAP - Número cuenta principal
24. VACTDTNEDO - Valor cuenta documento neto
25. VACTDTBRDO - Valor cuenta documento bruto
26. NUIVDO - Número IVA documento
27. POIVDO - Porcentaje IVA documento
28. VAIVDO - Valor IVA documento
29. NUIMDO - Número impuesto documento
30. VAIMDO - Valor impuesto documento

**Total de columnas en MAEEDO:** ~100 columnas

### Hoja 2: MAEDDO (Detalle)

**Columnas identificadas (primeras 30):**
1. IDMAEDDO - ID línea (autoincremental)
2. IDMAEEDO - ID del encabezado (FK)
3. ARCHIRST - Archivo RST
4. IDRST - ID RST
5. EMPRESA - Código empresa
6. TIDO - Tipo documento
7. NUDO - Número documento
8. ENDO - Código cliente
9. SUENDO - Sub-entidad
10. ENDOFI - Entidad final
11. LILG - Línea legalizada
12. NULIDO - Número línea documento
13. SULIDO - Sub línea documento
14. LUVTLIDO - Lugar venta línea
15. BOSULIDO - Bodega sub línea
16. KOFULIDO - Código vendedor línea
17. NULILG - Número línea legal
18. PRCT - Producto
19. TICT - Tipo producto
20. TIPR - Tipo precio
21. NUSEPR - Número serie producto
22. KOPRCT - Código producto
23. UDTRPR - Unidad transporte producto
24. RLUDPR - Relación unidad producto
25. CAPRCO1 - Cantidad producto 1
26. CAPRAD1 - Cantidad adicional 1
27. CAPREX1 - Cantidad extra 1
28. CAPRNC1 - Cantidad no conforme 1
29. UD01PR - Unidad 1 producto
30. CAPRCO2 - Cantidad producto 2

**Total de columnas en MAEDDO:** ~120 columnas

---

## 🎯 VALORES RECOMENDADOS (del Excel)

Basándonos en la estructura detectada y los índices encontrados, aquí están los valores que el sistema interno espera:

### MAEEDO (Encabezado):

| Columna | Valor Recomendado | Descripción |
|---------|-------------------|-------------|
| `IDMAEEDO` | Correlativo | ID único (IDENTITY) |
| `EMPRESA` | `'01'` | Código empresa fijo |
| `TIDO` | `'NVV'` | Tipo documento |
| `NUDO` | Mismo que IDMAEEDO | Número documento |
| `ENDO` | Código cliente | Del cliente |
| `SUENDO` | `'001'` | Sub-entidad por defecto |
| `SUDO` | `'001'` | Sucursal por defecto |
| `FEEMDO` | `GETDATE()` | Fecha emisión |
| `KOFUDO` | Código vendedor | Del usuario |
| `ESDO` | `'N'` | Estado: Nuevo |
| `TIGEDO` | `'I'` | Tipo generación |
| `LUVTDO` | `'LIB'` | Lugar de venta |
| `MEARDO` | `'S'` | Medio |
| `MODO` | `'TABPP01P'` o similar | Modo de precio |
| `VABRDO` | Total | Valor bruto documento |
| `VANEDO` | Total | Valor neto documento |
| `FE01VEDO` | `GETDATE() + 30` | Fecha vencimiento |
| `FEULVEDO` | `GETDATE() + 30` | Fecha última vez |
| `VAABDO` | `0` | Valor abonado |

### MAEDDO (Detalle):

| Columna | Valor Recomendado | Descripción |
|---------|-------------------|-------------|
| `IDMAEDDO` | Autoincremental | No insertar, se genera automáticamente |
| `IDMAEEDO` | Del encabezado | FK al encabezado |
| `EMPRESA` | `'01'` | Código empresa |
| `TIDO` | `'NVV'` | Tipo documento |
| `NUDO` | Mismo que encabezado | Número documento |
| `ENDO` | Código cliente | Del cliente |
| `SUENDO` | `'001'` | Sub-entidad |
| `LILG` | `'SI'` | Línea legalizada |
| `KOPRCT` | Código producto | Del producto |
| `NOKOPR` | Nombre producto | Del producto |
| `CAPRCO1` | Cantidad | Cantidad solicitada |
| `PPPRNE` | Precio unitario | Precio neto |
| `VANELI` | Cantidad × Precio | Valor neto línea |
| `VABRLI` | Cantidad × Precio | Valor bruto línea |
| `FEEMLI` | `GETDATE()` | Fecha emisión línea |

---

## ⚠️ CAMPOS QUE FALTAN EN NUESTRA IMPLEMENTACIÓN ACTUAL

### MAEEDO:
- `TIGEDO` = 'I' (Tipo generación: Ingreso)
- `LUVTDO` = 'LIB' (Lugar de venta: Libre?)
- `MEARDO` = 'S' (Medio: Sistema?)
- `MODO` = 'TABPP01P' (Modo: Tabla de precios 01P?)
- `VANEDO` = Total (Valor neto, actualmente solo tenemos VABRDO)

### MAEDDO:
- Todas las columnas actuales parecen correctas

---

## 🔄 ACTUALIZACIÓN REQUERIDA

### INSERT MAEEDO - Versión Corregida:

```sql
SET IDENTITY_INSERT MAEEDO ON

INSERT INTO MAEEDO (
    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO, SUDO,
    TIGEDO, LUVTDO, MEARDO, MODO,
    FEEMDO, FE01VEDO, FEULVEDO, 
    VABRDO, VANEDO, VAABDO, ESDO, KOFUDO
) VALUES (
    {$siguienteId},  -- IDMAEEDO
    '01',            -- EMPRESA
    'NVV',           -- TIDO
    {$siguienteId},  -- NUDO
    '{$cliente}',    -- ENDO
    '001',           -- SUENDO
    '001',           -- SUDO
    'I',             -- TIGEDO (Ingreso)
    'LIB',           -- LUVTDO (Lugar venta: Libre)
    'S',             -- MEARDO (Medio: Sistema)
    'TABPP01P',      -- MODO (Tabla precios 01P)
    GETDATE(),       -- FEEMDO
    '{$fecha_venc}', -- FE01VEDO
    '{$fecha_venc}', -- FEULVEDO
    {$total},        -- VABRDO
    {$total},        -- VANEDO (mismo que VABRDO si no hay descuentos)
    0,               -- VAABDO
    'N',             -- ESDO (Nuevo)
    '{$vendedor}'    -- KOFUDO
)

SET IDENTITY_INSERT MAEEDO OFF
```

### INSERT MAEDDO - Mantener Actual:

```sql
INSERT INTO MAEDDO (
    IDMAEEDO, EMPRESA, TIDO, NUDO, ENDO, SUENDO,
    LILG, KOPRCT, NOKOPR, CAPRCO1, PPPRNE, VANELI, VABRLI,
    FEEMLI
) VALUES (
    {$siguienteId},  -- IDMAEEDO
    '01',            -- EMPRESA
    'NVV',           -- TIDO
    {$siguienteId},  -- NUDO
    '{$cliente}',    -- ENDO
    '001',           -- SUENDO
    'SI',            -- LILG
    '{$codigo_prod}',-- KOPRCT
    '{$nombre_prod}',-- NOKOPR
    {$cantidad},     -- CAPRCO1
    {$precio},       -- PPPRNE
    {$subtotal},     -- VANELI
    {$subtotal},     -- VABRLI
    GETDATE()        -- FEEMLI
)
```

---

## 📊 RESUMEN DE CAMBIOS NECESARIOS

### Agregar a MAEEDO:
1. ✅ `TIGEDO = 'I'`
2. ✅ `LUVTDO = 'LIB'`
3. ✅ `MEARDO = 'S'`
4. ✅ `MODO = 'TABPP01P'`
5. ✅ `VANEDO = $total`

### Mantener en MAEDDO:
- La estructura actual está correcta

---

## 🎯 PRÓXIMA ACCIÓN

Actualizar el método `insertarEnSQLServer()` en `AprobacionController.php` para incluir los campos faltantes en MAEEDO:
- TIGEDO
- LUVTDO
- MEARDO
- MODO
- VANEDO

Esto asegurará que el sistema interno de SQL Server reconozca correctamente las NVV generadas desde el sistema web.

---

**Nota**: Los valores exactos de algunos campos (como MODO) pueden variar según la configuración del sistema. Se recomienda verificar con el equipo de soporte o revisar NVV existentes en SQL Server para confirmar los valores correctos.

**Estado**: ⏳ Pendiente de implementación  
**Fecha**: 8 de Octubre 2025


