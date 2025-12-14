# Funcionalidad de Separación de Productos en NVV

## 📋 Resumen Ejecutivo

La funcionalidad de separación de productos permite crear una nueva Nota de Venta (NVV) a partir de productos seleccionados de una NVV existente. Esta funcionalidad se utiliza principalmente para manejar problemas de stock, permitiendo separar productos con disponibilidad limitada o problemas de inventario en una nueva NVV independiente.

**Fecha de Documentación:** 2025-01-14  
**Archivo Principal:** `app/Http/Controllers/AprobacionController.php`  
**Vista Principal:** `resources/views/aprobaciones/show.blade.php`

---

## 🔄 Tipos de Separación

### 1. Separación Individual de Productos

Permite separar una cantidad específica (parcial o total) de un solo producto.

**Características:**
- Se especifica la cantidad a separar en el campo "Separar" de cada producto
- Permite separar una cantidad parcial (ej: de 100 unidades, separar 30)
- Valida múltiplos de venta del producto
- Solo disponible para roles: **Compras** y **Picking**

**Método:** `separarProductoIndividual()`

### 2. Separación Múltiple de Productos

Permite seleccionar varios productos y separarlos todos juntos en una nueva NVV.

**Características:**
- Selección múltiple mediante checkboxes
- Todos los productos seleccionados se eliminan completamente de la NVV original
- Solo disponible para el rol: **Compras**

**Método:** `separarProductos()`

---

## 📊 Flujo del Proceso

### Separación Individual - Paso a Paso

1. **Definir Cantidad a Separar:**
   - El usuario ingresa la cantidad deseada en el campo "Separar" del producto
   - Se valida que cumpla con los múltiplos de venta
   - Se guarda temporalmente con: `guardarSeparar()`

2. **Ejecutar Separación:**
   - El usuario hace clic en el botón "Separar" del producto
   - Se solicita confirmación y motivo de la separación
   - Se ejecuta: `separarProductoIndividual()`

3. **Crear Nueva NVV:**
   - Se duplica la NVV original con `replicate()`
   - Se crea el producto con la cantidad separada
   - Se establecen estados y observaciones

4. **Actualizar NVV Original:**
   - Si se separó la cantidad completa → se elimina el producto
   - Si se separó cantidad parcial → se reduce la cantidad del producto
   - Se recalculan los totales

5. **Registro y Notificaciones:**
   - Se registra en el historial de ambas NVV
   - Se envía notificación al vendedor

### Separación Múltiple - Paso a Paso

1. **Seleccionar Productos:**
   - El usuario marca los checkboxes de los productos a separar
   - Puede usar "Seleccionar Todos"

2. **Ejecutar Separación:**
   - Hace clic en "Separar Seleccionados"
   - Se solicita confirmación y motivo
   - Se ejecuta: `separarProductos()`

3. **Crear Nueva NVV:**
   - Se duplican todos los productos seleccionados
   - Se mantienen las cantidades originales de cada producto

4. **Actualizar NVV Original:**
   - Se eliminan todos los productos seleccionados
   - Se recalculan los totales

---

## 🔧 Detalles Técnicos

### Rutas (routes/web.php)

```php
Route::post('/aprobaciones/{id}/guardar-separar', 
    [AprobacionController::class, 'guardarSeparar'])
    ->name('aprobaciones.guardar-separar');

Route::post('/aprobaciones/{id}/separar-producto-individual', 
    [AprobacionController::class, 'separarProductoIndividual'])
    ->name('aprobaciones.separar-producto-individual');

Route::post('/aprobaciones/{id}/separar-productos', 
    [AprobacionController::class, 'separarProductos'])
    ->name('aprobaciones.separar-productos');
```

### Métodos Principales

#### 1. `guardarSeparar($id)`

Guarda la cantidad a separar en el campo `cantidad_separar` del producto.

**Validaciones:**
- Verifica permisos (Compras o Picking)
- Valida múltiplos de venta del producto
- Valida que no exceda la cantidad disponible

**Ubicación:** `app/Http/Controllers/AprobacionController.php:2619`

#### 2. `separarProductoIndividual(Request $request, $id)`

Ejecuta la separación individual de un producto.

**Validaciones:**
- Permisos: Compras o Picking
- Cantidad a separar > 0
- Múltiplos de venta
- No exceder cantidad disponible

**Lógica:**
- Si `cantidad_separar == cantidad` → elimina el producto
- Si `cantidad_separar < cantidad` → reduce la cantidad

**Ubicación:** `app/Http/Controllers/AprobacionController.php:2665`

#### 3. `separarProductos(Request $request, $id)`

Ejecuta la separación múltiple de productos.

**Validaciones:**
- Permisos: Solo Compras
- Array de productos válidos
- Motivo requerido

**Lógica:**
- Elimina todos los productos seleccionados de la original
- Los duplica en la nueva NVV

**Ubicación:** `app/Http/Controllers/AprobacionController.php:2345`

#### 4. `crearNvvConProductoSeparado()`

Método privado que crea la nueva NVV con el producto separado.

**Ubicación:** `app/Http/Controllers/AprobacionController.php:2750`

#### 5. `crearNvvDuplicadaMultiple()`

Método privado que crea la nueva NVV con múltiples productos.

**Ubicación:** `app/Http/Controllers/AprobacionController.php:2506`

#### 6. `actualizarTotalesCotizacion($cotizacion)`

Recalcula los totales (subtotal, descuento, total) de una cotización.

**Ubicación:** `app/Http/Controllers/AprobacionController.php:2187`

---

## ✨ Lo que se Genera (Nueva NVV)

### Datos Copiados de la NVV Original

Cuando se replica una NVV, se copian **todos los campos** excepto:
- `id` (nuevo ID automático)
- `created_at` (nueva fecha)
- `updated_at` (nueva fecha)
- `estado` → se establece en `'pendiente_stock'`
- `estado_aprobacion` → se establece en `'pendiente'`
- `observaciones` → se sobrescribe con motivo de separación
- `nota_original_id` → se establece con el ID de la NVV original

### Campos Específicos de la Nueva NVV

```php
$nuevaCotizacion = $cotizacionOriginal->replicate();
$nuevaCotizacion->estado = 'pendiente_stock';
$nuevaCotizacion->estado_aprobacion = 'pendiente';
$nuevaCotizacion->created_at = now();
$nuevaCotizacion->updated_at = now();
$nuevaCotizacion->observaciones = "NVV creada con producto separado: ...";
$nuevaCotizacion->nota_original_id = $cotizacionOriginal->id;
```

### Productos en la Nueva NVV

**Separación Individual:**
```php
$nuevoProducto = $producto->replicate();
$nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
$nuevoProducto->cantidad = $cantidadSeparar;  // Cantidad específica
$nuevoProducto->cantidad_separar = 0;  // Resetear
$nuevoProducto->subtotal = $producto->precio_unitario * $cantidadSeparar;
```

**Separación Múltiple:**
```php
foreach ($productos as $producto) {
    $nuevoProducto = $producto->replicate();
    $nuevoProducto->cotizacion_id = $nuevaCotizacion->id;
    // Mantiene la cantidad original del producto
}
```

### Totales Recalculados

```php
$subtotal = $productos->sum(function($producto) {
    return $producto->precio_unitario * $producto->cantidad;
});

$descuento = $subtotal * ($cotizacion->descuento_porcentaje / 100);
$total = $subtotal - $descuento;
```

---

## 🔄 Lo que Afecta en la NVV Original

### Separación Individual

**Si se separa la cantidad completa:**
```php
if ($cantidadSeparar == $producto->cantidad) {
    $producto->delete();  // Se elimina el producto
}
```

**Si se separa cantidad parcial:**
```php
else {
    $nuevaCantidad = $producto->cantidad - $cantidadSeparar;
    $producto->update([
        'cantidad' => $nuevaCantidad,
        'cantidad_separar' => 0,  // Resetear
        'subtotal' => $producto->precio_unitario * $nuevaCantidad
    ]);
}
```

### Separación Múltiple

```php
// Se eliminan todos los productos seleccionados
$cotizacion->productos()->whereIn('id', $request->productos_ids)->delete();
```

### Actualización de Totales

En ambos casos, después de modificar los productos, se recalculan los totales:

```php
$this->actualizarTotalesCotizacion($cotizacion);
```

Esto actualiza:
- `subtotal`
- `descuento_monto`
- `total`

---

## 📝 Registro en Historial

### Historial de la NVV Original

```php
CotizacionHistorial::create([
    'cotizacion_id' => $cotizacionOriginal->id,
    'usuario_id' => $user->id,
    'estado_anterior' => $cotizacionOriginal->estado_aprobacion,
    'estado_nuevo' => $cotizacionOriginal->estado_aprobacion,
    'fecha_accion' => now(),
    'comentarios' => "Producto '{$producto->nombre_producto}' separado...",
    'detalles_adicionales' => [
        'accion' => 'separar_producto_individual',
        'producto_codigo' => $producto->codigo_producto,
        'cantidad_separada' => $cantidadSeparada,
        'nueva_cotizacion_id' => $cotizacionNueva->id,
        'motivo' => $motivo
    ]
]);
```

### Historial de la Nueva NVV

```php
CotizacionHistorial::create([
    'cotizacion_id' => $cotizacionNueva->id,
    'usuario_id' => $user->id,
    'estado_anterior' => null,
    'estado_nuevo' => 'pendiente',
    'fecha_accion' => now(),
    'comentarios' => "NVV creada por separación...",
    'detalles_adicionales' => [
        'accion' => 'crear_por_separacion',
        'cotizacion_origen_id' => $cotizacionOriginal->id,
        // ...
    ]
]);
```

---

## 🔐 Permisos y Roles

### Separación Individual

**Roles permitidos:**
- ✅ Compras
- ✅ Picking

**Restricciones:**
- Solo disponible cuando `$cotizacion->tiene_problemas_stock == true`
- Para Picking: solo si la NVV no está aprobada por Compras o si tiene rol Picking

### Separación Múltiple

**Roles permitidos:**
- ✅ Solo Compras

**Restricciones:**
- Solo disponible cuando `$cotizacion->tiene_problemas_stock == true`
- Solo si la NVV no está aprobada por Compras (`!$cotizacion->aprobado_por_compras`)

---

## ✅ Validaciones Implementadas

### Validaciones de Cantidad

1. **Cantidad > 0:**
   ```php
   if ($cantidadSeparar <= 0) {
       return response()->json(['error' => 'Debe especificar una cantidad a separar mayor a 0'], 400);
   }
   ```

2. **No exceder cantidad disponible:**
   ```php
   if ($cantidadSeparar > $producto->cantidad) {
       return response()->json(['error' => 'La cantidad a separar no puede exceder la cantidad del producto'], 400);
   }
   ```

3. **Múltiplos de venta:**
   ```php
   $multiplo = intval($producto->multiplo ?? (\DB::table('productos')->where('KOPR', $producto->codigo_producto)->value('multiplo_venta') ?? 1));
   if ($multiplo > 1 && ($cantidadSeparar % $multiplo) !== 0) {
       return response()->json(['error' => "La cantidad a separar debe ser múltiplo de {$multiplo}"], 400);
   }
   ```

### Validaciones de Permisos

```php
if (!$user->hasRole('Compras') && !$user->hasRole('Picking')) {
    return response()->json(['error' => 'No tienes permisos para realizar esta acción'], 403);
}
```

---

## 🎨 Interfaz de Usuario

### Vista: `resources/views/aprobaciones/show.blade.php`

#### Campo "Separar" en Tabla de Productos

```blade
@if((Auth::user()->hasRole('Compras') || Auth::user()->hasRole('Picking')) && ...)
    <input type="number" class="form-control separar-input" 
           value="{{ $producto->cantidad_separar ?? 0 }}" 
           min="{{ $multiploVenta }}" 
           step="{{ $multiploVenta }}" 
           max="{{ $producto->cantidad }}"
           data-producto-id="{{ $producto->id }}"
           data-multiplo="{{ $multiploVenta }}">
    <button onclick="guardarSeparar({{ $producto->id }})">
        <i class="material-icons">save</i>
    </button>
@endif
```

#### Botón Separar Individual

```blade
@if($producto->stock_disponible < $producto->cantidad)
    <button class="btn btn-warning btn-sm" 
            onclick="separarProductoIndividual({{ $producto->id }})">
        <i class="material-icons">call_split</i> Separar
    </button>
@endif
```

#### Separación Múltiple

```blade
<input type="checkbox" class="product-checkbox" value="{{ $producto->id }}" 
       onchange="updateSelectedProducts()">

<button class="btn btn-warning" 
        onclick="separarProductosSeleccionados()" 
        id="btnSepararSeleccionados" disabled>
    <i class="material-icons">call_split</i> Separar Seleccionados
</button>
```

### Funciones JavaScript Clave

1. **`guardarSeparar(productoId)`** - Guarda la cantidad a separar
2. **`separarProductoIndividual(productoId)`** - Ejecuta separación individual
3. **`separarProductosSeleccionados()`** - Ejecuta separación múltiple
4. **`updateSelectedProducts()`** - Actualiza contador de seleccionados
5. **`actualizarMaximoSeparar(productoId)`** - Ajusta máximo cuando cambia cantidad

---

## 🔗 Relaciones y Referencias

### Campo `nota_original_id`

La nueva NVV guarda una referencia a la original mediante el campo `nota_original_id`:

```php
$nuevaCotizacion->nota_original_id = $cotizacionOriginal->id;
```

Esto permite:
- Rastrear el origen de la NVV separada
- Implementar funcionalidades futuras de relación entre NVV
- Generar reportes de separaciones

### Relación en Modelo Cotizacion

```php
// En el modelo Cotizacion.php
public function notaOriginal()
{
    return $this->belongsTo(Cotizacion::class, 'nota_original_id');
}

public function notasSeparadas()
{
    return $this->hasMany(Cotizacion::class, 'nota_original_id');
}
```

---

## 📊 Ejemplo de Flujo Completo

### Escenario: Separar 30 unidades de 100

**Estado Inicial:**
- Producto A: 100 unidades en NVV #123

**Proceso:**
1. Usuario ingresa "30" en campo "Separar" → `guardarSeparar()` guarda en BD
2. Usuario hace clic en "Separar" → confirma y envía motivo
3. Sistema crea NVV #456:
   - Duplica toda la información de NVV #123
   - Crea Producto A con 30 unidades
   - Establece `nota_original_id = 123`
4. Sistema actualiza NVV #123:
   - Reduce Producto A a 70 unidades
   - Recalcula totales
5. Sistema registra en historial de ambas NVV
6. Sistema envía notificación al vendedor

**Estado Final:**
- NVV #123: Producto A con 70 unidades
- NVV #456: Producto A con 30 unidades (nueva)

---

## 🐛 Casos Especiales y Consideraciones

### Caso 1: Separar cantidad igual al total

Si `cantidad_separar == cantidad`, el producto se elimina completamente de la original en lugar de reducirse a 0.

### Caso 2: Separación múltiple con productos sin problemas de stock

Para roles que no sean "Compras", se valida que solo se puedan separar productos con problemas de stock. El perfil "Compras" puede separar cualquier producto.

### Caso 3: Múltiplos de venta

Si un producto tiene `multiplo_venta = 5`, solo se pueden separar cantidades: 5, 10, 15, 20, etc.

### Caso 4: Actualización de stock

El sistema consulta el stock disponible desde SQL Server y MySQL. La separación no actualiza automáticamente el stock; el stock se consulta en tiempo real cuando se agrega un producto.

---

## 📌 Puntos Importantes

1. **La nueva NVV siempre queda en estado `pendiente_stock`** - necesita aprobación
2. **La nueva NVV siempre queda con estado de aprobación `pendiente`** - reinicia el flujo de aprobación
3. **Los totales se recalculan automáticamente** en ambas NVV
4. **El campo `cantidad_separar` se resetea a 0** después de la separación
5. **Se mantiene la referencia a la NVV original** mediante `nota_original_id`
6. **Se registra todo en el historial** para auditoría
7. **Las notificaciones se envían al vendedor** para mantenerlo informado

---

## 🔍 Archivos Relacionados

- **Controlador:** `app/Http/Controllers/AprobacionController.php`
- **Vista:** `resources/views/aprobaciones/show.blade.php`
- **Modelo:** `app/Models/Cotizacion.php`
- **Modelo Historial:** `app/Models/CotizacionHistorial.php`
- **Rutas:** `routes/web.php` (líneas 139-141)

---

## 📝 Notas de Desarrollo

### Para Futuras Mejoras

1. **Visualización de relación:** Mostrar en la vista las NVV relacionadas (original/separadas)
2. **Agrupación:** Permitir ver todas las NVV separadas de una original
3. **Reversión:** Considerar funcionalidad para revertir una separación
4. **Reportes:** Generar reportes de separaciones por período
5. **Validación de stock:** Verificar stock antes de confirmar separación
6. **Separación automática:** Separar automáticamente productos sin stock al aprobar

---

**Última actualización:** 2025-01-14  
**Mantenido por:** Equipo de Desarrollo Gespro Raviera



