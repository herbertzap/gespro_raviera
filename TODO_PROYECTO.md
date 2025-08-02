# TODO - Proyecto GesPro Raviera

## 📋 **Estado del Proyecto según Documentación "Anexo 1: Desarrollo APP Ventas"**

---

## ✅ **FUNCIONALIDADES IMPLEMENTADAS**

### **1. Módulo de Ventas - Básico**
- ✅ **Búsqueda de clientes** por código o nombre
- ✅ **Búsqueda de productos** por SKU, nombre, marca o categoría
- ✅ **Creación de cotizaciones/pedidos**
- ✅ **Sistema de permisos de usuarios**
- ✅ **Interfaz de cotización** con motor de búsqueda

### **2. Alertas de Cobranza (Punto 3a)**
- ✅ **Facturas vencidas:** Verificación de documentos vencidos
- ✅ **Cheques protestados:** Verificación de cheques no aclarados
- ✅ **Saldo alto:** Alerta cuando saldo > $1,000,000
- ✅ **Visualización de alertas** en interfaz de cotización

### **3. Verificación de Stock (Punto 3b)**
- ✅ **Stock disponible:** Cálculo STOCK FISICO – STOCK COMPROMETIDO
- ✅ **Alertas visuales:** 
  - 🔴 Sin stock disponible
  - 🟡 Stock bajo (< 10 unidades)
  - 🟢 Stock suficiente
- ✅ **Información detallada** de stock físico y comprometido

### **4. Base de Datos**
- ✅ **Tabla cotizaciones** creada y migrada
- ✅ **Tabla cotizacion_detalles** creada y migrada
- ✅ **Modelos Cotizacion y CotizacionDetalle** implementados
- ✅ **Integración con SQL Server** para productos y precios

---

## ❌ **FUNCIONALIDADES PENDIENTES**

### **1. Algoritmo de Descuentos (Punto 5)**
- ❌ **Descuento del 5%** si pedido > $400,000
- ❌ **Descuento por promedio** de compras últimos 3 meses
- ❌ **Configuración flexible** de descuentos por empresa
- ❌ **Cálculo automático** de descuentos en cotización

### **2. Estados de Pedidos (Punto 6)**
- ❌ **Autorización automática** vs pendiente de aprobación
- ❌ **Inserción en tablas MAEEDO, MAEDDO, MAEEDOOB, MAEVEN**
- ❌ **Actualización de MAEST, MAEPR, MAEPREM**
- ❌ **Documento tipo "NVV"** (Nota de Venta)

### **3. Módulo de Autorización de Ventas (Punto 7)**
- ❌ **Lista de pedidos pendientes** de aprobación
- ❌ **Revisión y modificación** de pedidos
- ❌ **Eliminación de productos** del pedido
- ❌ **Modificación de cantidades** y descuentos
- ❌ **LOG de transacciones** para auditoría
- ❌ **Permisos específicos** para revisores

### **4. Módulo de Compras (Punto 9)**
- ❌ **Gestión de estados de pedidos:**
  - En proceso de compra
  - Recepcionado
  - NO facturar (Cerrar Nota de venta)
- ❌ **Información de documentos de compras:**
  - Orden de compra
  - Factura de compra
  - Cierre de nota de venta
- ❌ **Notificaciones por email** a facturación/despacho

### **5. Módulo de Bodega (Punto 10)**
- ❌ **Lista de pedidos aprobados** (automático/manual)
- ❌ **Asignación de preparador** del pedido
- ❌ **Impresión de picking** para preparación
- ❌ **Estados de picking:**
  - Parcial (con motivo)
  - Completado (con cantidad de bultos)
- ❌ **Cierre de pedido** como "Completado"

### **6. Informes Adicionales (Punto 11)**
- ❌ **Informe de Cobranza:**
  - Documentos sin documentar
  - Cheques protestados
  - Sobre cheques en cartera
- ❌ **Seguimiento de pedidos** (línea de tiempo)
- ❌ **Informe de Ventas efectuadas**

### **7. Venta Off Line (Punto 12)**
- ❌ **Web service/Hosting** intermediario
- ❌ **Daemon** para sincronización cada 5 minutos
- ❌ **Almacenamiento temporal** sin nube
- ❌ **Sincronización** cuando conexión esté activa

---

## 🔧 **MEJORAS TÉCNICAS PENDIENTES**

### **1. Base de Datos**
- ❌ **Tabla de estados de pedidos**
- ❌ **Tabla de logs de transacciones**
- ❌ **Tabla de autorizaciones**
- ❌ **Tabla de picking**
- ❌ **Configuración de descuentos**

### **2. Controladores**
- ❌ **AutorizacionController** para módulo de autorización
- ❌ **CompraController** para módulo de compras
- ❌ **BodegaController** para módulo de bodega
- ❌ **InformeController** para reportes

### **3. Modelos**
- ❌ **EstadoPedido** para gestión de estados
- ❌ **LogTransaccion** para auditoría
- ❌ **Autorizacion** para aprobaciones
- ❌ **Picking** para gestión de bodega

### **4. Vistas**
- ❌ **Vista de autorización de ventas**
- ❌ **Vista de gestión de compras**
- ❌ **Vista de bodega y picking**
- ❌ **Vistas de informes**

---

## 🎯 **PRIORIDADES DE IMPLEMENTACIÓN**

### **Alta Prioridad (Core del Sistema)**
1. **Estados de pedidos** - Fundamental para el flujo
2. **Algoritmo de descuentos** - Requerimiento específico
3. **Módulo de autorización** - Control de aprobaciones

### **Media Prioridad (Funcionalidad Completa)**
4. **Módulo de compras** - Gestión de pedidos pendientes
5. **Módulo de bodega** - Preparación y despacho
6. **Informes básicos** - Seguimiento y control

### **Baja Prioridad (Mejoras)**
7. **Venta off line** - Funcionalidad adicional
8. **Informes avanzados** - Análisis y reportes

---

## 📝 **NOTAS IMPORTANTES**

### **Estructura de Documentos del Sistema:**
- **FCV:** Factura de Venta
- **FDV:** Factura de Venta (otro tipo)
- **NCV:** Nota de Crédito de Venta
- **NVV:** Nota de Venta (para cotizaciones/pedidos)
- **CHV:** Cheques

### **Tablas SQL Server Principales:**
- **MAEEDO:** Encabezado de documentos
- **MAEDDO:** Detalle de documentos
- **MAEEN:** Entidades (clientes)
- **MAEPR:** Productos
- **MAEST:** Stock
- **TABPRE:** Listas de precios
- **MAEDPCE:** Cheques

### **Consideraciones Técnicas:**
- Mantener sincronización con SQL Server
- Implementar transacciones para consistencia
- Logging completo para auditoría
- Permisos granulares por módulo

---

## 🚀 **PRÓXIMO PASO RECOMENDADO**

**Implementar Estados de Pedidos y Algoritmo de Descuentos** para completar el flujo básico de ventas según la documentación.

---

*Última actualización: 31 de Julio 2025*
*Documentación base: Anexo 1 Detalle desarrollo APP Ventas* 