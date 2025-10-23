# INSERT MAEDTLI - Implementación Completada

## ✅ **FUNCIONALIDAD AGREGADA**

### **Condición de Inserción:**
- ✅ **Solo productos CON descuento** (`PODTGLLI > 0`)
- ❌ **NO inserta** productos sin descuento
- 📝 **Log detallado** para cada producto

### **Campos Insertados:**
```sql
INSERT INTO MAEDTLI (
    IDMAEEDO,    -- ID del documento (MAEDDO->IDMAEEDO)
    NULIDO,      -- Número de línea (MAEDDO->NULILG)
    KODT,        -- 'D_SIN_TIPO' (texto fijo)
    PODT,        -- Porcentaje descuento (MAEDDO->PODTGLLI)
    VADT         -- Valor descuento (MAEDDO->VADTNELI)
) VALUES (
    {IDMAEEDO}, 'D_SIN_TIPO', {PORCENTAJE}, {VALOR}
)
```

## 📊 **EJEMPLO CON NVV 0000040424**

### **Productos con Descuento (se insertan en MAEDTLI):**

#### **1. LATEX BOLSA OCRE**
```
IDMAEEDO: 159071
NULIDO: 00003
KODT: 'D_SIN_TIPO'
PODT: 30.00
VADT: 8112.00
```

#### **2. TALADRO DE PERCUSION**
```
IDMAEEDO: 159071
NULIDO: 00001
KODT: 'D_SIN_TIPO'
PODT: 5.00
VADT: 1493.60
```

#### **3. CEMENTO BLANCO**
```
IDMAEEDO: 159071
NULIDO: 00002
KODT: 'D_SIN_TIPO'
PODT: 20.00
VADT: 4460.00
```

### **Productos SIN Descuento:**
- ❌ **NO se insertan** en MAEDTLI
- 📝 **Log**: "Producto línea X sin descuento - NO se inserta en MAEDTLI"

## 🔧 **IMPLEMENTACIÓN TÉCNICA**

### **Ubicación en el Código:**
- **Archivo**: `app/Http/Controllers/AprobacionController.php`
- **Método**: `insertarEnSQLServer()`
- **Línea**: Después del INSERT de MAEDDO, dentro del bucle de productos

### **Lógica de Validación:**
```php
if ($porcentajeDescuento > 0) {
    // INSERT MAEDTLI
    // Log: "✅ MAEDTLI insertado correctamente"
} else {
    // Log: "⏭️ Producto sin descuento - NO se inserta"
}
```

### **Manejo de Errores:**
- ✅ **Éxito**: Log de confirmación
- ⚠️ **Error**: Log de warning (no detiene el proceso)
- 📝 **Trazabilidad**: Logs detallados para cada producto

## 🎯 **FLUJO COMPLETO DE INSERCIÓN**

1. **MAEEDO** - Encabezado del documento
2. **MAEDDO** - Detalle de cada producto
3. **MAEDTLI** - Solo productos con descuento ⭐ **NUEVO**
4. **MAEST** - Actualización de stock
5. **MAEPREM** - Actualización de stock NVV
6. **MySQL** - Actualización de stock comprometido

## ✅ **ESTADO**

- ✅ **Código implementado**
- ✅ **Validación de descuentos**
- ✅ **Logs detallados**
- ✅ **Manejo de errores**
- ✅ **Caché limpiado**

## 🚀 **LISTO PARA PROBAR**

La próxima NVV que se apruebe incluirá automáticamente los INSERT en MAEDTLI para todos los productos que tengan descuento asociado.



