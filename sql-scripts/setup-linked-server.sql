-- Configurar Linked Server para conectar a la base de datos externa
-- Reemplazar con los valores reales de tu base de datos externa

-- Crear el linked server
EXEC sp_addlinkedserver 
    @server = 'EXTERNAL_SERVER',
    @srvproduct = '',
    @provider = 'SQLNCLI',
    @datasrc = '152.231.92.82,1433';

-- Configurar las credenciales
EXEC sp_addlinkedsrvlogin 
    @rmtsrvname = 'EXTERNAL_SERVER',
    @useself = 'FALSE',
    @rmtuser = 'AMANECER',
    @rmtpassword = 'AMANECER';

-- Configurar opciones del linked server
EXEC sp_serveroption 
    @server = 'EXTERNAL_SERVER',
    @optname = 'rpc',
    @optvalue = 'true';

EXEC sp_serveroption 
    @server = 'EXTERNAL_SERVER',
    @optname = 'rpc out',
    @optvalue = 'true';

-- Probar la conexión
SELECT @@VERSION as LocalVersion;
SELECT * FROM EXTERNAL_SERVER.HIGUERA030924.INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE';

-- Crear vistas para facilitar el acceso a las tablas principales
-- Vista para clientes (MAEEN)
IF EXISTS (SELECT * FROM sys.views WHERE name = 'V_CLIENTES')
    DROP VIEW V_CLIENTES;
GO

CREATE VIEW V_CLIENTES AS
SELECT * FROM EXTERNAL_SERVER.HIGUERA030924.dbo.MAEEN;
GO

-- Vista para facturas (MAEEDDO)
IF EXISTS (SELECT * FROM sys.views WHERE name = 'V_FACTURAS')
    DROP VIEW V_FACTURAS;
GO

CREATE VIEW V_FACTURAS AS
SELECT * FROM EXTERNAL_SERVER.HIGUERA030924.dbo.MAEEDDO;
GO

-- Vista para productos (si existe)
IF EXISTS (SELECT * FROM sys.views WHERE name = 'V_PRODUCTOS')
    DROP VIEW V_PRODUCTOS;
GO

CREATE VIEW V_PRODUCTOS AS
SELECT * FROM EXTERNAL_SERVER.HIGUERA030924.dbo.MAEPRO;
GO

PRINT 'Linked Server configurado exitosamente!'; 