-- Consultas de prueba para las vistas de productos

-- 1. Probar la vista de productos (sin filtro)
SELECT TOP 10 * FROM vw_productos_cotizacion

-- 2. Probar búsqueda por nombre
SELECT TOP 10 * FROM vw_productos_cotizacion 
WHERE nombre LIKE '%ANTICORROSIVO%'

-- 3. Probar búsqueda por código
SELECT TOP 10 * FROM vw_productos_cotizacion 
WHERE codigo LIKE '%1D01%'

-- 4. Probar búsqueda por marca
SELECT TOP 10 * FROM vw_productos_cotizacion 
WHERE marca LIKE '%AMANECER%'

-- 5. Probar la vista de precios
SELECT TOP 10 * FROM vw_precios_productos 
WHERE codigo_producto = '1D01000001504'

-- 6. Contar total de productos
SELECT COUNT(*) as total_productos FROM vw_productos_cotizacion

-- 7. Verificar productos con stock bajo
SELECT COUNT(*) as productos_stock_bajo 
FROM vw_productos_cotizacion 
WHERE alerta_stock IN ('danger', 'warning') 