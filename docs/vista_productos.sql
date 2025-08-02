-- Vista para productos con información completa para cotizaciones
-- Incluye: código, nombre, marca, unidad, relación de unidades, categoría, subcategoría, stock y alertas

CREATE VIEW vw_productos_cotizacion AS
SELECT 
    MAEPR.KOPR as codigo,
    MAEPR.NOKOPR as nombre,
    TABMR.NOKOMR as marca,
    MAEPR.UD01PR as unidad,
    MAEPR.RLUD as relacion_unidades,
    TABFM.NOKOFM as categoria,
    TABPF.NOKOPF as subcategoria,
    MAEPR.DIVISIBLE as divisible_ud1,
    MAEPR.DIVISIBLE2 as divisible_ud2,
    ISNULL(MAEST.STFI1, 0) as stock_fisico,
    ISNULL(MAEST.STOCNV1, 0) as stock_comprometido,
    (ISNULL(MAEST.STFI1, 0) - ISNULL(MAEST.STOCNV1, 0)) as stock_disponible,
    MAEPR.TIPR as tipo_producto,
    CASE 
        WHEN (ISNULL(MAEST.STFI1, 0) - ISNULL(MAEST.STOCNV1, 0)) <= 0 THEN 'danger'
        WHEN (ISNULL(MAEST.STFI1, 0) - ISNULL(MAEST.STOCNV1, 0)) < 10 THEN 'warning'
        ELSE 'success'
    END as alerta_stock
FROM MAEPR 
INNER JOIN TABMR ON MAEPR.MRPR = TABMR.KOMR
LEFT JOIN TABFM ON MAEPR.FMPR = TABFM.KOFM
LEFT JOIN TABPF ON MAEPR.PFPR = TABPF.KOPF
LEFT JOIN MAEST ON MAEPR.KOPR = MAEST.KOPR AND MAEST.KOBO = '01'
WHERE MAEPR.TIPR != 'D'  -- Excluir productos descontinuados
GO

-- Vista para precios de productos
CREATE VIEW vw_precios_productos AS
SELECT 
    KOPR as codigo_producto,
    KOLT as lista_precio,
    PP01UD as precio_ud1,
    PP02UD as precio_ud2,
    MG01UD as margen_ud1,
    MG02UD as margen_ud2,
    RLUD as relacion_unidades
FROM TABPRE
GO 