# Consultas SQL para Stock por Bodega - Aplicar en Vista Cotizar

Este documento contiene las consultas SQL mejoradas para obtener stock por bodega que deben aplicarse en la vista de cotizar del proyecto con SQL Server 2012.

## 1. Consulta de Stock por Bodega (Mejorada)

Esta es la consulta que se usa actualmente en `ManejoStockController` para obtener stock filtrado por bodega:

### SQL Server Query Builder (Laravel Eloquent)

```php
$stockQuery = DB::connection('sqlsrv_external')
    ->table('MAEST')
    ->selectRaw('SUM(ISNULL(STFI1,0)) as stock_fisico, SUM(ISNULL(STOCNV1,0)) as stock_comprometido')
    ->where('KOPR', trim($sku));

if ($bodega && $bodega->kobo) {
    $stockQuery->where('KOBO', $bodega->kobo);
}

$stockData = $stockQuery->first();
$stockFisico = (float) ($stockData->stock_fisico ?? 0);
$stockComprometido = (float) ($stockData->stock_comprometido ?? 0);
$stockDisponible = $stockFisico - $stockComprometido;
```

### SQL Puro (para usar directamente en SQL Server)

```sql
-- Consulta con bodega específica
SELECT 
    SUM(ISNULL(STFI1, 0)) AS stock_fisico,
    SUM(ISNULL(STOCNV1, 0)) AS stock_comprometido,
    SUM(ISNULL(STFI1, 0)) - SUM(ISNULL(STOCNV1, 0)) AS stock_disponible
FROM MAEST
WHERE KOPR = 'CODIGO_PRODUCTO'
  AND KOBO = 'CODIGO_BODEGA'  -- Ejemplo: '01', 'LIB', etc.

-- Consulta sin filtro de bodega (suma todas las bodegas)
SELECT 
    SUM(ISNULL(STFI1, 0)) AS stock_fisico,
    SUM(ISNULL(STOCNV1, 0)) AS stock_comprometido,
    SUM(ISNULL(STFI1, 0)) - SUM(ISNULL(STOCNV1, 0)) AS stock_disponible
FROM MAEST
WHERE KOPR = 'CODIGO_PRODUCTO'
```

### Consulta para múltiples productos con bodega específica

```sql
SELECT 
    MAEST.KOPR AS codigo_producto,
    SUM(ISNULL(MAEST.STFI1, 0)) AS stock_fisico,
    SUM(ISNULL(MAEST.STOCNV1, 0)) AS stock_comprometido,
    SUM(ISNULL(MAEST.STFI1, 0)) - SUM(ISNULL(MAEST.STOCNV1, 0)) AS stock_disponible
FROM MAEST
WHERE MAEST.KOPR IN ('PRODUCTO1', 'PRODUCTO2', 'PRODUCTO3')
  AND MAEST.KOBO = 'CODIGO_BODEGA'
GROUP BY MAEST.KOPR
```

---

## 2. Consulta Actual de Búsqueda de Productos en Cotizar

Esta es la consulta actual que se usa en `CotizacionController::buscarProductos()`:

### Búsqueda en MySQL (Tabla Local)

```php
// Buscar productos en tabla local MySQL
$query = DB::table('productos')->where('activo', true);

if (count($terminos) > 1) {
    // Búsqueda con múltiples términos
    $query->where(function($q) use ($terminos) {
        foreach ($terminos as $termino) {
            $q->where('NOKOPR', 'LIKE', "%{$termino}%");
        }
    });
} else {
    // Búsqueda simple: por código o nombre
    $query->where(function($q) use ($busqueda) {
        $q->where('KOPR', 'LIKE', "{$busqueda}%")
          ->orWhere('NOKOPR', 'LIKE', "%{$busqueda}%");
    });
}

$productos = $query->limit(15)->get();
```

### SQL Puro equivalente

```sql
-- Búsqueda simple (un término)
SELECT *
FROM productos
WHERE activo = 1
  AND (KOPR LIKE 'TERMINO%' OR NOKOPR LIKE '%TERMINO%')
LIMIT 15;

-- Búsqueda múltiples términos (todos deben estar en el nombre)
SELECT *
FROM productos
WHERE activo = 1
  AND NOKOPR LIKE '%TERMINO1%'
  AND NOKOPR LIKE '%TERMINO2%'
LIMIT 15;
```

---

## 3. Consulta Mejorada: Búsqueda de Productos con Stock por Bodega

### Opción A: Consulta Directa a SQL Server (Recomendada)

Esta consulta busca productos directamente en SQL Server y obtiene el stock por bodega en una sola consulta:

```sql
SELECT 
    MAEPR.KOPR AS CODIGO_PRODUCTO,
    MAEPR.NOKOPR AS NOMBRE_PRODUCTO,
    MAEPR.UD01PR AS UNIDAD_MEDIDA,
    MAEPR.RLUD AS RELACION_UNIDADES,
    ISNULL(SUM(MAEST.STFI1), 0) AS STOCK_FISICO,
    ISNULL(SUM(MAEST.STOCNV1), 0) AS STOCK_COMPROMETIDO,
    ISNULL(SUM(MAEST.STFI1), 0) - ISNULL(SUM(MAEST.STOCNV1), 0) AS STOCK_DISPONIBLE,
    TABBO.NOKOBO AS NOMBRE_BODEGA,
    MAEST.KOBO AS CODIGO_BODEGA
FROM MAEPR
LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR 
    AND MAEST.KOBO = 'CODIGO_BODEGA'  -- Filtrar por bodega específica
LEFT JOIN TABBO ON MAEST.KOBO = TABBO.KOBO
WHERE MAEPR.KOPR LIKE 'TERMINO%' 
   OR MAEPR.NOKOPR LIKE '%TERMINO%'
GROUP BY 
    MAEPR.KOPR, 
    MAEPR.NOKOPR, 
    MAEPR.UD01PR, 
    MAEPR.RLUD,
    TABBO.NOKOBO,
    MAEST.KOBO
ORDER BY MAEPR.KOPR
```

### Opción B: Consulta en dos pasos (MySQL + SQL Server)

**Paso 1: Buscar productos en MySQL (tabla local)**
```php
$productos = DB::table('productos')
    ->where('activo', true)
    ->where(function($q) use ($busqueda) {
        $q->where('KOPR', 'LIKE', "{$busqueda}%")
          ->orWhere('NOKOPR', 'LIKE', "%{$busqueda}%");
    })
    ->limit(15)
    ->get();

$codigosProductos = $productos->pluck('KOPR')->toArray();
```

**Paso 2: Consultar stock por bodega desde SQL Server**
```php
// Obtener código de bodega (puede venir del request o ser una variable)
$codigoBodega = $request->get('bodega_codigo', '01'); // Por defecto '01'

$stocks = DB::connection('sqlsrv_external')
    ->table('MAEST')
    ->selectRaw('
        KOPR,
        SUM(ISNULL(STFI1, 0)) as stock_fisico,
        SUM(ISNULL(STOCNV1, 0)) as stock_comprometido,
        SUM(ISNULL(STFI1, 0)) - SUM(ISNULL(STOCNV1, 0)) as stock_disponible
    ')
    ->whereIn('KOPR', $codigosProductos)
    ->where('KOBO', $codigoBodega)
    ->groupBy('KOPR')
    ->get()
    ->keyBy('KOPR');
```

**Paso 3: Combinar resultados**
```php
$productosConStock = $productos->map(function($producto) use ($stocks, $codigoBodega) {
    $stock = $stocks->get($producto->KOPR);
    
    return [
        'CODIGO_PRODUCTO' => $producto->KOPR,
        'NOMBRE_PRODUCTO' => $producto->NOKOPR,
        'UNIDAD_MEDIDA' => $producto->UD01PR,
        'STOCK_FISICO' => $stock ? (float)$stock->stock_fisico : 0,
        'STOCK_COMPROMETIDO' => $stock ? (float)$stock->stock_comprometido : 0,
        'STOCK_DISPONIBLE' => $stock ? (float)$stock->stock_disponible : 0,
        'BODEGA_CODIGO' => $codigoBodega,
        // ... otros campos del producto
    ];
});
```

### SQL Puro para Paso 2

```sql
SELECT 
    KOPR,
    SUM(ISNULL(STFI1, 0)) AS stock_fisico,
    SUM(ISNULL(STOCNV1, 0)) AS stock_comprometido,
    SUM(ISNULL(STFI1, 0)) - SUM(ISNULL(STOCNV1, 0)) AS stock_disponible
FROM MAEST
WHERE KOPR IN ('PRODUCTO1', 'PRODUCTO2', 'PRODUCTO3', ...)
  AND KOBO = 'CODIGO_BODEGA'
GROUP BY KOPR
```

---

## 4. Implementación Completa Recomendada

### Método en Controller

```php
public function buscarProductos(Request $request)
{
    try {
        $busqueda = $request->get('busqueda', '');
        $codigoBodega = $request->get('bodega_codigo', '01'); // Obtener de request
        $listaPrecios = $request->get('lista_precios', '01');
        
        if (empty($busqueda) || strlen($busqueda) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar al menos 3 caracteres para buscar'
            ]);
        }
        
        // PASO 1: Buscar productos en MySQL (tabla local)
        $terminos = array_filter(explode(' ', trim($busqueda)));
        $query = DB::table('productos')->where('activo', true);
        
        if (count($terminos) > 1) {
            $query->where(function($q) use ($terminos) {
                foreach ($terminos as $termino) {
                    $q->where('NOKOPR', 'LIKE', "%{$termino}%");
                }
            });
        } else {
            $query->where(function($q) use ($busqueda) {
                $q->where('KOPR', 'LIKE', "{$busqueda}%")
                  ->orWhere('NOKOPR', 'LIKE', "%{$busqueda}%");
            });
        }
        
        $productos = $query->limit(15)->get();
        
        if ($productos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron productos'
            ]);
        }
        
        // PASO 2: Obtener códigos de productos
        $codigosProductos = $productos->pluck('KOPR')->toArray();
        
        // PASO 3: Consultar stock por bodega desde SQL Server
        $stocks = DB::connection('sqlsrv_external')
            ->table('MAEST')
            ->selectRaw('
                KOPR,
                SUM(ISNULL(STFI1, 0)) as stock_fisico,
                SUM(ISNULL(STOCNV1, 0)) as stock_comprometido,
                SUM(ISNULL(STFI1, 0)) - SUM(ISNULL(STOCNV1, 0)) as stock_disponible
            ')
            ->whereIn('KOPR', $codigosProductos)
            ->where('KOBO', $codigoBodega)
            ->groupBy('KOPR')
            ->get()
            ->keyBy('KOPR');
        
        // PASO 4: Combinar resultados
        $productosConStock = $productos->map(function($producto) use ($stocks, $codigoBodega, $listaPrecios) {
            $stock = $stocks->get($producto->KOPR);
            
            // Obtener precios según lista
            $precio = 0;
            if ($listaPrecios === '01P' || $listaPrecios === '01') {
                $precio = $producto->precio_01p ?? 0;
            } elseif ($listaPrecios === '02P' || $listaPrecios === '02') {
                $precio = $producto->precio_02p ?? 0;
            } elseif ($listaPrecios === '03P' || $listaPrecios === '03') {
                $precio = $producto->precio_03p ?? 0;
            }
            
            return [
                'CODIGO_PRODUCTO' => $producto->KOPR,
                'NOMBRE_PRODUCTO' => $producto->NOKOPR,
                'UNIDAD_MEDIDA' => $producto->UD01PR,
                'PRECIO_UD1' => $precio,
                'STOCK_FISICO' => $stock ? (float)$stock->stock_fisico : 0,
                'STOCK_COMPROMETIDO' => $stock ? (float)$stock->stock_comprometido : 0,
                'STOCK_DISPONIBLE' => $stock ? (float)$stock->stock_disponible : 0,
                'BODEGA_CODIGO' => $codigoBodega,
                'LISTA_PRECIOS' => $listaPrecios,
                'PRECIO_VALIDO' => $precio > 0,
            ];
        })->toArray();
        
        return response()->json([
            'success' => true,
            'data' => $productosConStock,
            'total' => count($productosConStock),
            'search_term' => $busqueda,
            'bodega_codigo' => $codigoBodega
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error buscando productos: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al buscar productos: ' . $e->getMessage()
        ]);
    }
}
```

---

## 5. Notas Importantes

### Diferencias Clave

1. **Filtro por Bodega**: La consulta mejorada incluye `WHERE KOBO = 'CODIGO_BODEGA'` para filtrar stock por bodega específica.

2. **SUM vs SELECT directo**: Se usa `SUM(ISNULL(STFI1, 0))` porque un producto puede tener múltiples registros en `MAEST` (uno por bodega). Si se filtra por bodega específica, normalmente habrá un solo registro, pero usar `SUM` es más seguro.

3. **GROUP BY**: Cuando se consultan múltiples productos, es necesario usar `GROUP BY KOPR` para agrupar los resultados por producto.

4. **Stock Disponible**: Se calcula como `STOCK_FISICO - STOCK_COMPROMETIDO`.

### Consideraciones para SQL Server 2012

- SQL Server 2012 soporta todas las funciones usadas (`ISNULL`, `SUM`, `GROUP BY`).
- La sintaxis es compatible con versiones anteriores de SQL Server.
- Si hay problemas de rendimiento, considerar agregar índices en `MAEST(KOPR, KOBO)`.

### Obtener Código de Bodega

El código de bodega puede venir de:
- Request del frontend: `$request->get('bodega_codigo')`
- Sesión del usuario: `session('bodega_codigo')`
- Configuración por defecto: `'01'` o `'LIB'`
- Relación con el usuario/cliente

---

## 6. Ejemplo de Uso en Frontend

```javascript
// En la búsqueda de productos, incluir el código de bodega
function buscarProductos(termino) {
    const bodegaCodigo = $('#bodega_select').val() || '01';
    const listaPrecios = $('#lista_precios').val() || '01';
    
    $.ajax({
        url: '/cotizaciones/buscar-productos',
        method: 'GET',
        data: {
            busqueda: termino,
            bodega_codigo: bodegaCodigo,
            lista_precios: listaPrecios
        },
        success: function(response) {
            if (response.success) {
                // Mostrar productos con stock por bodega
                mostrarProductos(response.data);
            }
        }
    });
}
```

---

## Resumen

**Cambio Principal**: Agregar filtro `WHERE KOBO = 'CODIGO_BODEGA'` en la consulta de stock para obtener stock específico por bodega en lugar de stock total de todas las bodegas.

**Archivos a Modificar**:
- `app/Http/Controllers/CotizacionController.php` - Método `buscarProductos()`
- Frontend: Incluir `bodega_codigo` en la petición AJAX

**Consulta Clave**:
```sql
SELECT 
    KOPR,
    SUM(ISNULL(STFI1, 0)) AS stock_fisico,
    SUM(ISNULL(STOCNV1, 0)) AS stock_comprometido,
    SUM(ISNULL(STFI1, 0)) - SUM(ISNULL(STOCNV1, 0)) AS stock_disponible
FROM MAEST
WHERE KOPR IN (...)
  AND KOBO = 'CODIGO_BODEGA'  -- ← ESTE ES EL CAMBIO PRINCIPAL
GROUP BY KOPR
```

