# Modo Previsualización - Habilitado

## ✅ **MODO PREVISUALIZACIÓN ACTIVADO**

### **🎯 FUNCIONALIDAD IMPLEMENTADA:**

#### **1. Método `previsualizarInsertSQL()` Actualizado:**
- ✅ **MAEEDO** - Encabezado del documento (ya existía)
- ✅ **MAEDDO** - Detalle de productos (ya existía)
- ✅ **MAEEDOOB** - Observaciones y orden de compra ⭐ **NUEVO**
- ✅ **MAEDTLI** - Productos con descuento ⭐ **NUEVO**
- ✅ **UPDATE STOCK** - Actualizaciones de stock (ya existía)

#### **2. Nuevas Secciones Agregadas:**

##### **📊 PREVISUALIZACIÓN TABLA MAEEDOOB:**
```
╔══════════════════════════════════════════════════════════════════════════╗
║                  📊 PREVISUALIZACIÓN TABLA MAEEDOOB                       ║
╠══════════════════════════════════════════════════════════════════════════╣
║ IDMAEEDO                        = 159071                                 ║
║ OBDO (observación vendedor)     = Observación del vendedor...            ║
║ CPDO (condición pago)           = 30                                     ║
║ OCDO (orden compra)             = OC-2025-001                            ║
╚══════════════════════════════════════════════════════════════════════════╝
```

##### **📊 PREVISUALIZACIÓN TABLA MAEDTLI:**
```
╔══════════════════════════════════════════════════════════════════════════╗
║                  📊 PREVISUALIZACIÓN TABLA MAEDTLI                       ║
╠══════════════════════════════════════════════════════════════════════════╣
║ Productos con descuento         = 2                                     ║
║ KODT (fijo)                     = D_SIN_TIPO                              ║
║                                                                          ║
║ Línea 1                         = 616905030R310                          ║
║   IDMAEEDO =                    = 159071                                 ║
║   NULIDO =                      = 00001                                  ║
║   KODT =                        = D_SIN_TIPO                             ║
║   PODT =                        = 30.00                                  ║
║   VADT =                        = 8112.00                                ║
║                                                                          ║
║ Línea 2                         = 616905030R311                          ║
║   IDMAEEDO =                    = 159071                                 ║
║   NULIDO =                      = 00002                                  ║
║   KODT =                        = D_SIN_TIPO                             ║
║   PODT =                        = 5.00                                   ║
║   VADT =                        = 1493.60                                ║
╚══════════════════════════════════════════════════════════════════════════╝
```

#### **3. Lógica de Validación:**
- ✅ **MAEEDOOB**: Siempre se inserta (con observación y orden de compra)
- ✅ **MAEDTLI**: Solo productos CON descuento (`PODTGLLI > 0`)
- ✅ **Truncado automático**: Observación (250 chars), Orden compra (40 chars)
- ✅ **Condición de pago**: Obtenida desde `MAEEN->CPEN`

#### **4. Modo Previsualización Habilitado:**
- ✅ **Código real comentado** - No se ejecutan INSERT reales
- ✅ **Solo previsualización** - Muestra todos los datos en logs
- ✅ **Estado NVV no cambia** - Permanece en `pendiente_picking`
- ✅ **Mensaje informativo** - "MODO PREVISUALIZACIÓN activado"

## 🔧 **CÓMO USAR:**

### **1. Aprobar una NVV:**
- Ir a la NVV pendiente de picking
- Hacer clic en "Aprobar" 
- **NO se inserta nada en SQL Server**
- **NO cambia el estado de la NVV**
- **Solo genera logs detallados**

### **2. Revisar los Logs:**
- Ir a `/var/log/laravel.log` o usar `tail -f storage/logs/laravel.log`
- Buscar las secciones:
  - `📊 PREVISUALIZACIÓN TABLA MAEEDO`
  - `📊 PREVISUALIZACIÓN TABLA MAEDDO`
  - `📊 PREVISUALIZACIÓN TABLA MAEEDOOB` ⭐ **NUEVO**
  - `📊 PREVISUALIZACIÓN TABLA MAEDTLI` ⭐ **NUEVO**
  - `📊 PREVISUALIZACIÓN UPDATE STOCK`

### **3. Verificar Datos:**
- **Observación del vendedor**: Se muestra truncada a 250 chars
- **Orden de compra**: Se muestra truncada a 40 chars
- **Productos con descuento**: Solo los que tienen `PODTGLLI > 0`
- **Condición de pago**: Obtenida desde SQL Server

## 📋 **EJEMPLO DE LOGS:**

```
[2025-10-17 20:47:33] local.INFO: 👁️ === PREVISUALIZACIÓN DE DATOS A INSERTAR ===
[2025-10-17 20:47:33] local.INFO: 
╔══════════════════════════════════════════════════════════════════════════╗
║                    📊 PREVISUALIZACIÓN TABLA MAEEDO                      ║
╠══════════════════════════════════════════════════════════════════════════╣
║ IDMAEEDO                        = 159071                                 ║
║ EMPRESA                         = 01                                      ║
║ TIDO                            = NVV                                     ║
║ NUDO                            = 0000037622                              ║
║ ENDO                            = 2                                       ║
║ SUENDO                          = 001                                     ║
║ OBDO (observación vendedor)     = Cliente requiere entrega urgente...    ║
║ CPDO (condición pago)           = 30                                     ║
║ OCDO (orden compra)             = OC-2025-001                            ║
╚══════════════════════════════════════════════════════════════════════════╝

[2025-10-17 20:47:33] local.INFO: 
╔══════════════════════════════════════════════════════════════════════════╗
║                  📊 PREVISUALIZACIÓN TABLA MAEEDOOB                       ║
╠══════════════════════════════════════════════════════════════════════════╣
║ IDMAEEDO                        = 159071                                 ║
║ OBDO (observación vendedor)     = Cliente requiere entrega urgente...    ║
║ CPDO (condición pago)           = 30                                     ║
║ OCDO (orden compra)             = OC-2025-001                            ║
╚══════════════════════════════════════════════════════════════════════════╝

[2025-10-17 20:47:33] local.INFO: 
╔══════════════════════════════════════════════════════════════════════════╗
║                  📊 PREVISUALIZACIÓN TABLA MAEDTLI                       ║
╠══════════════════════════════════════════════════════════════════════════╣
║ Productos con descuento         = 2                                     ║
║ KODT (fijo)                     = D_SIN_TIPO                              ║
║                                                                          ║
║ Línea 1                         = 616905030R310                          ║
║   IDMAEEDO =                    = 159071                                 ║
║   NULIDO =                      = 00001                                  ║
║   KODT =                        = D_SIN_TIPO                             ║
║   PODT =                        = 30.00                                  ║
║   VADT =                        = 8112.00                                ║
║                                                                          ║
║ Línea 2                         = 616905030R311                          ║
║   IDMAEEDO =                    = 159071                                 ║
║   NULIDO =                      = 00002                                  ║
║   KODT =                        = D_SIN_TIPO                             ║
║   PODT =                        = 5.00                                   ║
║   VADT =                        = 1493.60                                ║
╚══════════════════════════════════════════════════════════════════════════╝
```

## ✅ **ESTADO ACTUAL:**

- ✅ **Modo previsualización**: HABILITADO
- ✅ **INSERT reales**: DESHABILITADOS (comentados)
- ✅ **Nuevas tablas**: MAEEDOOB y MAEDTLI incluidas
- ✅ **Logs detallados**: Todos los campos mostrados
- ✅ **Validaciones**: Truncado y condiciones aplicadas

## 🚀 **LISTO PARA PROBAR:**

1. **Crear una nueva cotización** con observación y orden de compra
2. **Aprobar la NVV** desde el perfil Picking
3. **Revisar los logs** para ver todos los datos que se insertarían
4. **Verificar** que MAEEDOOB y MAEDTLI aparecen correctamente

## 🔄 **PARA VOLVER AL MODO REAL:**

1. Comentar el bloque de previsualización
2. Descomentar el bloque de inserción real
3. Limpiar caché
4. Probar con una NVV real



