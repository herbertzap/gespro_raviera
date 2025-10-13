# Prueba de NVV en Base de Datos de Producción

## ✅ Configuración Actual

- **Base de datos**: `HIGUERA` (PRODUCCIÓN)
- **Host**: `152.231.92.82:1433`
- **Cotización de prueba**: #35
- **Cliente**: PRUEBA 2 (código: 2)
- **Total**: $95,445.14
- **Estado actual**: `pendiente`

## 📦 Productos en la Cotización #35

1. **CEMENTO BLANCO KILO UN** (2700900110000)
   - Cantidad: 20
   - Precio: $1,115.00

2. **MARTILLO STANDARD ESTWING 33 CM (22OZ) UN** (1212000000000)
   - Cantidad: 1
   - Precio: $35,800.00

3. **LATEX BOLSA AZUL COLONIAL** (LBAC000000000)
   - Cantidad: 16
   - Precio: $1,690.00

## 🔄 Flujo de Aprobación

### Paso 1: Aprobar por Supervisor
1. Ir a: https://app.wuayna.com/aprobaciones/35
2. Hacer clic en **"Aprobar"** como Supervisor
3. El estado cambiará a:
   - Si hay problemas de stock → `aprobada_supervisor` (va a Compras)
   - Si NO hay problemas → `pendiente_picking` (va directo a Picking)

### Paso 2: Aprobar por Compras (si aplica)
1. Si fue a Compras, aprobar como usuario Compras
2. El estado cambiará a `pendiente_picking`

### Paso 3: Aprobar por Picking (FINAL)
1. Aprobar como usuario Picking
2. **AQUÍ SE EJECUTA EL INSERT A SQL SERVER**
3. El estado cambiará a `aprobada_picking`
4. Se asignará un número de NVV

## 🔍 Verificación del Insert

### 1. Verificar en MySQL que se guardó el número NVV

```bash
php artisan tinker --execute="
\$cotizacion = \App\Models\Cotizacion::find(35);
echo 'Número NVV: ' . (\$cotizacion->numero_nvv ?? 'NULL') . PHP_EOL;
echo 'Estado: ' . \$cotizacion->estado_aprobacion . PHP_EOL;
"
```

### 2. Verificar en SQL Server que existe la NVV

```bash
cd /var/www/html/wuayna/gespro_raviera
cat > /tmp/verify_nvv.sql << 'EOF'
SELECT 
    IDMAEEDO,
    TIDO,
    NUDO,
    ENDO,
    FEEMDO,
    VABRDO,
    ESDO
FROM MAEEDO 
WHERE IDMAEEDO = [NUMERO_NVV_AQUI]
go
quit
EOF
tsql -H 152.231.92.82 -p 1433 -U AMANECER -P AMANECER -D HIGUERA < /tmp/verify_nvv.sql
```

### 3. Verificar los detalles (productos) de la NVV

```bash
cat > /tmp/verify_nvv_detail.sql << 'EOF'
SELECT 
    IDMAEEDO,
    IDMAEDDO,
    KOPRCT,
    NOKOPR,
    CAPRCO1,
    PPPRNE,
    VANELI
FROM MAEDDO 
WHERE IDMAEEDO = [NUMERO_NVV_AQUI]
ORDER BY IDMAEDDO
go
quit
EOF
tsql -H 152.231.92.82 -p 1433 -U AMANECER -P AMANECER -D HIGUERA < /tmp/verify_nvv_detail.sql
```

### 4. Ver los logs del proceso

```bash
tail -100 storage/logs/laravel.log
```

**Logs esperados:**
```
[local.INFO]: Iniciando aprobación por picking para cotización 35
[local.INFO]: Cotización aprobada en MySQL, iniciando insert en SQL Server
[local.INFO]: Siguiente ID para MAEEDO: [NUMERO]
[local.INFO]: Encabezado MAEEDO insertado correctamente
[local.INFO]: Detalles MAEDDO insertados correctamente
[local.INFO]: Vendedor MAEVEN insertado correctamente
[local.INFO]: Stock comprometido MAEST actualizado correctamente
[local.INFO]: Stock comprometido MySQL actualizado correctamente
[local.INFO]: Nota de venta 35 aprobada por picking [USER_ID] y insertada en SQL Server con ID [NUMERO]
```

## 🧪 Comando de Prueba (Opcional)

Antes de aprobar desde el navegador, puedes probar el insert sin ejecutarlo:

```bash
php artisan test:insert-nvv 35
```

Este comando:
- ✅ Verifica la conexión a SQL Server
- ✅ Muestra el siguiente ID disponible
- ✅ Genera todos los SQL queries
- ✅ Te permite confirmar antes de ejecutar

## 📊 Tablas SQL Server Afectadas

### MAEEDO (Encabezado)
Se insertará 1 registro con:
- IDMAEEDO: [auto-generado]
- TIDO: 'NVV'
- ENDO: '2' (código cliente)
- VABRDO: 95445.14 (total)

### MAEDDO (Detalles)
Se insertarán 3 registros (uno por producto):
- Línea 1: CEMENTO BLANCO (20 unidades)
- Línea 2: MARTILLO ESTWING (1 unidad)
- Línea 3: LATEX AZUL (16 unidades)

### MAEVEN (Vendedor)
Se insertará 1 registro con el vendedor asignado

### MAEEDOOB (Observaciones)
Se insertará 1 registro con: "NVV generada desde sistema web - ID: 35"

### MAEST (Stock)
Se actualizarán 3 registros:
- STOCNV1 (stock comprometido) se incrementará para cada producto

## ⚠️ Importante

1. **Backup**: La base de datos de producción está en uso. Asegúrate de que el cliente "PRUEBA 2" sea realmente de prueba.

2. **Reversión**: Si necesitas revertir el insert, tendrás que:
   - Eliminar el registro de MAEEDO
   - Eliminar los registros de MAEDDO
   - Eliminar el registro de MAEVEN
   - Eliminar el registro de MAEEDOOB
   - Revertir el stock en MAEST

3. **Logs**: Los logs se guardan en `storage/logs/laravel.log` y son fundamentales para debugging.

## 🎯 Checklist de Verificación

Después de aprobar la NVV #35:

- [ ] Verificar que `numero_nvv` se guardó en MySQL
- [ ] Verificar que el registro existe en MAEEDO (SQL Server)
- [ ] Verificar que los 3 productos están en MAEDDO (SQL Server)
- [ ] Verificar que el vendedor está en MAEVEN (SQL Server)
- [ ] Verificar que las observaciones están en MAEEDOOB (SQL Server)
- [ ] Verificar que el stock se actualizó en MAEST (SQL Server)
- [ ] Verificar que el stock_comprometido se actualizó en MySQL
- [ ] Revisar los logs para confirmar que no hay errores

## 🔄 Volver a Base de Datos de Respaldo

Si quieres volver a usar la base de datos de respaldo después de la prueba:

```bash
cd /var/www/html/wuayna/gespro_raviera
sed -i 's/^SQLSRV_EXTERNAL_DATABASE=HIGUERA$/#SQLSRV_EXTERNAL_DATABASE=HIGUERA/' .env
sed -i 's/^#SQLSRV_EXTERNAL_DATABASE=HIGUERA030924$/SQLSRV_EXTERNAL_DATABASE=HIGUERA030924/' .env
echo "✓ Configuración actualizada a base de datos de RESPALDO (HIGUERA030924)"
```

## 📞 Soporte

Si algo falla:
1. Revisar `storage/logs/laravel.log`
2. Ejecutar `php artisan test:insert-nvv 35` para ver el SQL generado
3. Verificar la conexión a SQL Server
4. Verificar que el usuario tiene permisos de escritura en SQL Server

---

**Estado actual**: ✅ Sistema configurado para insertar en PRODUCCIÓN (HIGUERA)
**Siguiente paso**: Aprobar la cotización #35 desde https://app.wuayna.com/aprobaciones/35
