# Análisis de la Lógica de Stock de Productos

## 🔍 **Problema Identificado**

Los vendedores reportan que productos que tienen stock físico aparecen como "sin stock" en el sistema, causando problemas en las cotizaciones.

## 📊 **Lógica Actual del Stock**

### **Fórmula Actual:**
```
Stock Disponible = Stock Físico - Stock Comprometido (SQL) - Stock Comprometido (MySQL)
```

### **Componentes:**

1. **Stock Físico** (`stock_fisico` en tabla `productos`)
   - Sincronizado desde SQL Server (tabla MAEST)
   - Representa el stock real en bodega

2. **Stock Comprometido SQL** (`stock_comprometido` en tabla `productos`)
   - Sincronizado desde SQL Server
   - Incluye NVVs ya generadas en el sistema principal

3. **Stock Comprometido MySQL** (tabla `stock_comprometidos`)
   - Stock comprometido por cotizaciones pendientes
   - Stock comprometido por NVVs pendientes de aprobación

## 🚨 **Problemas Identificados**

### **1. Doble Conteo de Stock Comprometido**
- El stock comprometido se cuenta tanto en SQL Server como en MySQL
- Cuando se aprueba una NVV, se actualiza el stock comprometido en SQL Server
- Pero el stock comprometido en MySQL no se libera correctamente

### **2. Inconsistencia en la Sincronización**
- El stock físico se sincroniza desde SQL Server
- Pero el stock comprometido puede estar desactualizado
- No hay una sincronización bidireccional

### **3. Lógica de Aprobación Confusa**
- Cuando se aprueba una NVV, se incrementa el stock comprometido en SQL Server
- Pero el stock comprometido en MySQL no se reduce
- Esto causa que el stock disponible sea incorrecto

## ✅ **Lógica Propuesta (Mejorada)**

### **Fórmula Corregida:**
```
Stock Real = Stock Físico + Stock NVV (SQL) + Stock NVV (MySQL)
Stock Disponible = Stock Real - Stock Comprometido (Cotizaciones Pendientes)
```

### **Componentes Corregidos:**

1. **Stock Físico** (desde SQL Server)
   - Stock real en bodega
   - Base para todos los cálculos

2. **Stock NVV (SQL Server)**
   - NVVs ya generadas y aprobadas
   - Se suma al stock físico para obtener stock real

3. **Stock NVV (MySQL)**
   - NVVs generadas en el sistema web
   - Se suma al stock físico para obtener stock real

4. **Stock Comprometido (Solo Cotizaciones Pendientes)**
   - Solo cotizaciones que no han sido aprobadas
   - Se resta del stock real para obtener stock disponible

## 🔧 **Implementación Propuesta**

### **1. Nuevo Método de Cálculo de Stock**

```php
public function obtenerStockDisponibleReal($productoCodigo, $bodegaCodigo = '01')
{
    // 1. Obtener stock físico desde SQL Server
    $stockFisico = $this->obtenerStockFisicoSQL($productoCodigo);
    
    // 2. Obtener stock NVV desde SQL Server (ya aprobadas)
    $stockNvvSQL = $this->obtenerStockNvvSQL($productoCodigo);
    
    // 3. Obtener stock NVV desde MySQL (generadas en sistema web)
    $stockNvvMySQL = $this->obtenerStockNvvMySQL($productoCodigo);
    
    // 4. Calcular stock real
    $stockReal = $stockFisico + $stockNvvSQL + $stockNvvMySQL;
    
    // 5. Obtener stock comprometido (solo cotizaciones pendientes)
    $stockComprometido = $this->obtenerStockComprometidoPendiente($productoCodigo);
    
    // 6. Calcular stock disponible
    $stockDisponible = $stockReal - $stockComprometido;
    
    return max(0, $stockDisponible);
}
```

### **2. Flujo de Aprobación Corregido**

#### **Cuando se crea una cotización:**
1. Verificar stock disponible real
2. Si hay stock suficiente, comprometer stock (solo en MySQL)
3. No afectar el stock de SQL Server

#### **Cuando se aprueba una NVV:**
1. Liberar stock comprometido en MySQL
2. Actualizar stock NVV en SQL Server
3. Actualizar stock NVV en MySQL
4. Recalcular stock disponible

#### **Cuando se rechaza una NVV:**
1. Liberar stock comprometido en MySQL
2. No afectar stock de SQL Server

### **3. Sincronización Mejorada**

#### **Desde SQL Server hacia MySQL:**
- Stock físico
- Stock NVV (ya aprobadas)
- Stock comprometido (solo para referencia)

#### **Desde MySQL hacia SQL Server:**
- Solo cuando se aprueba una NVV
- Actualizar stock NVV en SQL Server

## 📋 **Beneficios de la Nueva Lógica**

1. **Consistencia**: El stock siempre refleja la realidad
2. **Precisión**: No hay doble conteo de stock comprometido
3. **Transparencia**: Los vendedores ven el stock real disponible
4. **Eficiencia**: Menos consultas a SQL Server
5. **Confiabilidad**: El sistema es más predecible

## 🎯 **Próximos Pasos**

1. **Implementar** la nueva lógica de cálculo de stock
2. **Migrar** los datos existentes
3. **Probar** con productos problemáticos
4. **Validar** con el equipo de ventas
5. **Documentar** el nuevo flujo

## 🔍 **Productos a Revisar**

- Productos que aparecen con stock pero se muestran como "sin stock"
- Productos con stock comprometido incorrecto
- Productos con inconsistencias entre SQL Server y MySQL

---

**Fecha de análisis**: 23/10/2025
**Estado**: Pendiente de implementación
**Prioridad**: Alta
