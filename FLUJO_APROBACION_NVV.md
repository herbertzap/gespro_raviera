# 📋 Flujo Completo de Aprobación NVV

## 🔄 Proceso de Aprobación por Picking

### 1️⃣ **Obtener Siguiente Correlativo SQL Server**
```sql
SELECT ISNULL(MAX(IDMAEEDO), 0) + 1 FROM MAEEDO WHERE EMPRESA = '01'
```
- Resultado actual: **158485**

---

### 2️⃣ **INSERT en MAEEDO (Encabezado NVV)**
```sql
INSERT INTO MAEEDO (
    IDMAEEDO, TIDO, NUDO, ENDO, SUENDO, FEEMDO, 
    VABRDO, EMPRESA, KOFU, SUDO, ESDO, ...
) VALUES (
    158485, 'NVV', 158485, 'CLIENTE', '001', GETDATE(),
    TOTAL, '01', 'VENDEDOR', '001', 'N', ...
)
```

---

### 3️⃣ **INSERT en MAEDDO (Detalles - Por cada producto)**
```sql
INSERT INTO MAEDDO (
    IDMAEEDO, IDMAEDDO, KOPRCT, NOKOPR, CAPRCO1, PPPRNE, ...
) VALUES (
    158485, 1, 'CODIGO', 'NOMBRE', CANTIDAD, PRECIO, ...
)
```
- Se ejecuta **N veces** (N = cantidad de productos)

---

### 4️⃣ **INSERT en MAEEDOOB (Observaciones)**
```sql
INSERT INTO MAEEDOOB (
    IDMAEEDO, IDMAEDOOB, OBSERVACION, EMPRESA
) VALUES (
    158485, 1, 'NVV generada desde sistema web - ID: 16', '01'
)
```

---

### 5️⃣ **INSERT en MAEVEN (Vendedor)**
```sql
INSERT INTO MAEVEN (
    IDMAEEDO, KOFU, NOKOFU, EMPRESA
) VALUES (
    158485, 'LCB', 'LUIS CASANGA BERRIOS', '01'
)
```

---

### 6️⃣ **UPDATE en MAEST (Stock Comprometido SQL Server)**
```sql
UPDATE MAEST 
SET STOCKSALIDA = ISNULL(STOCKSALIDA, 0) + CANTIDAD
WHERE KOPR = 'CODIGO_PRODUCTO' AND EMPRESA = '01'
```
- Se ejecuta **N veces** (por cada producto)
- **STOCKSALIDA**: Stock comprometido (NVV pendientes)

---

### 7️⃣ **UPDATE en MAEPR (Última Compra)**
```sql
UPDATE MAEPR 
SET ULTIMACOMPRA = GETDATE()
WHERE KOPR = 'CODIGO_PRODUCTO'
```
- Se ejecuta **N veces** (por cada producto)

---

### 8️⃣ **UPDATE en MySQL (Stock Comprometido Virtual)**
```php
// Por cada producto:
$producto->stock_comprometido += $cantidad;
$producto->stock_disponible = $stock_fisico - $stock_comprometido;
$producto->save();
```
- Mantiene stock virtual actualizado en MySQL
- Usado para validar nuevas ventas antes de aprobar

---

### 9️⃣ **UPDATE en MySQL (Número NVV en Cotización)**
```php
$cotizacion->numero_nvv = 158485;
$cotizacion->save();
```
- Guarda el correlativo de SQL Server en MySQL
- Permite rastrear la NVV

---

## 📊 Resumen de Operaciones

### **SQL Server (4 + 3N operaciones)**:
- 1x INSERT MAEEDO
- Nx INSERT MAEDDO
- 1x INSERT MAEEDOOB
- 1x INSERT MAEVEN
- Nx UPDATE MAEST (stock comprometido)
- Nx UPDATE MAEPR (última compra)

### **MySQL (N + 1 operaciones)**:
- Nx UPDATE productos (stock_comprometido, stock_disponible)
- 1x UPDATE cotizaciones (numero_nvv)

### **Ejemplo con 4 productos**: 
- SQL Server: 4 + (3×4) = **16 operaciones**
- MySQL: 4 + 1 = **5 operaciones**
- **Total: 21 operaciones**

---

## �� Validación de Stock

### **Antes de aprobar NVV**:
```
Stock SQL Server: Físico 20, Comprometido 10
Stock MySQL: stock_fisico 20, stock_comprometido 10
Disponible real: 10
```

### **Al aprobar NVV por 10 unidades**:
```
SQL Server:
- STOCKSALIDA += 10 → Total: 20

MySQL:
- stock_comprometido += 10 → Total: 20
- stock_disponible = 20 - 20 → Total: 0
```

### **Nueva NVV por 10 más**:
```
Validación:
- stock_disponible = 0 (¡SIN STOCK!)
- Se crea como "pendiente por stock"
- Requiere aprobación de Compras
```

---

## ✅ Beneficios del Sistema Dual

1. **Stock SQL Server**: Fuente de verdad (stock real)
2. **Stock MySQL**: Cache rápido para validaciones
3. **Sincronización**: Comando `stock:sincronizar` actualiza desde SQL
4. **Validación en tiempo real**: No se vende más de lo disponible
5. **Control de compromisos**: Stock virtual previene sobreventa
