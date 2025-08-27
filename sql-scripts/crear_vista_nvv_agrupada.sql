-- Vista para NVV Pendientes Agrupadas por Número de NVV
-- Esta vista agrupa las notas de venta por número y muestra información consolidada

IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_nvv_pendientes_agrupada')
    DROP VIEW vw_nvv_pendientes_agrupada
GO

CREATE VIEW vw_nvv_pendientes_agrupada AS
SELECT 
    -- Información básica de la NVV
    dbo.MAEDDO.TIDO AS TD, 
    dbo.MAEDDO.NUDO AS NUM, 
    dbo.MAEDDO.FEEMLI AS EMIS_FCV, 
    dbo.MAEDDO.ENDO AS COD_CLI, 
    dbo.MAEEN.NOKOEN AS CLIE, 
    
    -- Información del vendedor
    dbo.TABFU.NOKOFU AS VENDEDOR_NOMBRE,
    dbo.MAEDDO.KOFULIDO AS KOFU,
    
    -- Información geográfica
    dbo.TABCI.NOKOCI AS REGION, 
    dbo.TABCM.NOKOCM AS COMUNA,
    
    -- Totales consolidados
    SUM(dbo.MAEDDO.CAPRCO1) AS TOTAL_CANTIDAD,
    SUM(dbo.MAEDDO.CAPRAD1 + dbo.MAEDDO.CAPREX1) AS TOTAL_FACTURADO,
    SUM(dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1) AS TOTAL_PENDIENTE,
    SUM(dbo.MAEDDO.VANELI) AS TOTAL_VALOR,
    SUM((dbo.MAEDDO.VANELI / dbo.MAEDDO.CAPRCO1) * (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1)) AS TOTAL_VALOR_PENDIENTE,
    
    -- Información de días y rango
    CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) AS DIAS, 
    CASE 
        WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) < 8 THEN 'Entre 1 y 7 días' 
        WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 8 AND 30 THEN 'Entre 8 y 30 Días' 
        WHEN CAST(GETDATE() - dbo.MAEDDO.FEEMLI AS INT) BETWEEN 31 AND 60 THEN 'Entre 31 y 60 Días' 
        ELSE 'Mas de 60 Días' 
    END AS Rango,
    
    -- Información de productos (agrupada)
    COUNT(DISTINCT dbo.MAEDDO.KOPRCT) AS CANTIDAD_PRODUCTOS,
    
    -- Información de facturación relacionada
    CASE WHEN EXISTS (
        SELECT 1 FROM dbo.MAEDDO AS MAEDDO_FACT 
        WHERE MAEDDO_FACT.IDMAEDDO = dbo.MAEDDO.IDRST 
        AND MAEDDO_FACT.TIDO IN ('FCV', 'FDV')
    ) THEN 'FACTURADA' ELSE 'PENDIENTE' END AS ESTADO_FACTURACION,
    
    -- Número de factura relacionada (si existe)
    (SELECT TOP 1 MAEDDO_FACT.NUDO 
     FROM dbo.MAEDDO AS MAEDDO_FACT 
     WHERE MAEDDO_FACT.IDMAEDDO = dbo.MAEDDO.IDRST 
     AND MAEDDO_FACT.TIDO IN ('FCV', 'FDV')) AS NUMERO_FACTURA,
    
    -- Fecha de facturación (si existe)
    (SELECT TOP 1 MAEDDO_FACT.FEEMLI 
     FROM dbo.MAEDDO AS MAEDDO_FACT 
     WHERE MAEDDO_FACT.IDMAEDDO = dbo.MAEDDO.IDRST 
     AND MAEDDO_FACT.TIDO IN ('FCV', 'FDV')) AS FECHA_FACTURACION

FROM dbo.MAEDDO 
INNER JOIN dbo.MAEEN ON dbo.MAEDDO.ENDO = dbo.MAEEN.KOEN AND dbo.MAEDDO.SUENDO = dbo.MAEEN.SUEN 
INNER JOIN dbo.TABFU ON dbo.MAEDDO.KOFULIDO = dbo.TABFU.KOFU 
INNER JOIN dbo.TABCI ON dbo.MAEEN.PAEN = dbo.TABCI.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI 
INNER JOIN dbo.TABCM ON dbo.MAEEN.PAEN = dbo.TABCM.KOPA AND dbo.MAEEN.CIEN = dbo.TABCI.KOCI AND dbo.MAEEN.CMEN = dbo.TABCM.KOCM 
WHERE (dbo.MAEDDO.TIDO = 'NVV') 
    AND (dbo.MAEDDO.LILG = 'SI') 
    AND (dbo.MAEDDO.CAPRCO1 - dbo.MAEDDO.CAPRAD1 - dbo.MAEDDO.CAPREX1 <> 0) 
    AND (dbo.MAEDDO.KOPRCT <> 'D') 
    AND (dbo.MAEDDO.KOPRCT <> 'FLETE')
GROUP BY 
    dbo.MAEDDO.TIDO, 
    dbo.MAEDDO.NUDO, 
    dbo.MAEDDO.FEEMLI, 
    dbo.MAEDDO.ENDO, 
    dbo.MAEEN.NOKOEN, 
    dbo.TABFU.NOKOFU,
    dbo.MAEDDO.KOFULIDO,
    dbo.TABCI.NOKOCI, 
    dbo.TABCM.NOKOCM,
    dbo.MAEDDO.IDRST
GO

-- Vista para mostrar la relación NVV -> Factura
IF EXISTS (SELECT * FROM sys.views WHERE name = 'vw_nvv_factura_relacion')
    DROP VIEW vw_nvv_factura_relacion
GO

CREATE VIEW vw_nvv_factura_relacion AS
SELECT 
    -- Información de la NVV
    nvv.TIDO AS NVV_TIPO,
    nvv.NUDO AS NVV_NUMERO,
    nvv.FEEMLI AS NVV_FECHA,
    nvv.ENDO AS CLIENTE_CODIGO,
    cli.NOKOEN AS CLIENTE_NOMBRE,
    nvv.KOFULIDO AS VENDEDOR_CODIGO,
    vend.NOKOFU AS VENDEDOR_NOMBRE,
    
    -- Información de la Factura relacionada
    fact.TIDO AS FACTURA_TIPO,
    fact.NUDO AS FACTURA_NUMERO,
    fact.FEEMLI AS FACTURA_FECHA,
    
    -- Totales
    nvv.CAPRCO1 AS NVV_CANTIDAD_TOTAL,
    nvv.CAPRAD1 + nvv.CAPREX1 AS NVV_FACTURADO,
    nvv.CAPRCO1 - nvv.CAPRAD1 - nvv.CAPREX1 AS NVV_PENDIENTE,
    nvv.VANELI AS NVV_VALOR_TOTAL,
    
    -- Estado
    CASE 
        WHEN fact.TIDO IS NOT NULL THEN 'FACTURADA'
        WHEN nvv.CAPRCO1 - nvv.CAPRAD1 - nvv.CAPREX1 = 0 THEN 'COMPLETAMENTE FACTURADA'
        ELSE 'PENDIENTE DE FACTURACION'
    END AS ESTADO,
    
    -- Días desde la NVV
    CAST(GETDATE() - nvv.FEEMLI AS INT) AS DIAS_DESDE_NVV,
    
    -- Días desde la factura
    CASE 
        WHEN fact.FEEMLI IS NOT NULL THEN CAST(GetDate() - fact.FEEMLI AS INT)
        ELSE NULL 
    END AS DIAS_DESDE_FACTURA

FROM dbo.MAEDDO nvv
INNER JOIN dbo.MAEEN cli ON nvv.ENDO = cli.KOEN AND nvv.SUENDO = cli.SUEN
INNER JOIN dbo.TABFU vend ON nvv.KOFULIDO = vend.KOFU
LEFT JOIN dbo.MAEDDO fact ON nvv.IDRST = fact.IDMAEDDO AND fact.TIDO IN ('FCV', 'FDV')
WHERE nvv.TIDO = 'NVV' 
    AND nvv.LILG = 'SI'
    AND nvv.KOPRCT <> 'D' 
    AND nvv.KOPRCT <> 'FLETE'
GO

-- Verificar las vistas creadas
SELECT 'Vista NVV Agrupada creada' AS Mensaje
GO

SELECT TOP 3 * FROM vw_nvv_pendientes_agrupada WHERE KOFU = 'GOP'
GO

SELECT 'Vista Relación NVV-Factura creada' AS Mensaje
GO

SELECT TOP 3 * FROM vw_nvv_factura_relacion WHERE VENDEDOR_CODIGO = 'GOP'
GO
