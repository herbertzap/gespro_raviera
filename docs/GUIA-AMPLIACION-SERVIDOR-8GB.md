# Guía: ampliación de servidor AWS a 8 GB RAM

**Proyecto:** APP-Higera (`gespro_raviera`)  
**Instancia actual:** `t3.medium` (~4 GB RAM) → **objetivo:** instancia con **8 GB RAM** (recomendado: `t3.large`)  
**Ventana planificada:** **18:00 hora Chile** (retomar chat / tareas después de este cambio)

---

## Resumen del diagnóstico (antes del cambio)

| Ítem | Estado encontrado |
|------|-------------------|
| Tipo EC2 | `t3.medium` (2 vCPU, ~4 GB RAM) |
| ID instancia | `i-04cb30b19d732861d` |
| Disco raíz | ~12 GB, **~79% usado** |
| Swap | **Sin swap** |
| PHP-FPM | `pm.max_children = 50` (muy alto para 4 GB) |
| Procesos PHP-FPM activos | ~37 (cada uno ~90–140 MB) |
| Carga CPU (momento del análisis) | Baja, pero la config aguanta mal picos de usuarios |
| Búsqueda de productos | MySQL + **`tsql` a SQL Server en cada búsqueda**, sin caché de stock, logs verbosos |

**Conclusión:** más RAM ayuda, pero conviene **ajustar PHP-FPM** y, en un segundo paso, **optimizar la búsqueda en código**.

---

## Antes de las 18:00 (preparación)

- [ ] Avisar a usuarios: mantenimiento breve (~5–15 min) al reiniciar la instancia.
- [ ] Tener acceso a **AWS Console** (EC2) o CLI con permisos `ec2:ModifyInstanceAttribute` / cambio de tipo.
- [ ] Anotar tipo actual: `t3.medium`.
- [ ] (Opcional) Snapshot del volumen EBS raíz: **EC2 → Volúmenes → Crear snapshot**.

---

## Cambio en AWS (18:00 Chile, aprox.)

### 1. Cambiar tipo de instancia

1. AWS Console → **EC2** → **Instancias**.
2. Seleccionar instancia `i-04cb30b19d732861d` (hostname tipo `ip-172-31-4-144`, región **us-east-2**).
3. **Estado de instancia** → **Detener instancia** (Stop). Esperar estado `stopped`.
4. **Acciones** → **Instancia** → **Cambiar tipo de instancia**.
5. Elegir **`t3.large`** (2 vCPU, **8 GiB RAM**) u otro tipo con 8 GB si usan otra familia.
6. Aplicar → **Iniciar instancia** (Start).

### 2. Verificar tras el arranque

Conectarse por SSH y ejecutar:

```bash
# RAM (debe mostrar ~7.5–8 Gi total)
free -h

# Tipo de instancia
TOKEN=$(curl -s -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 21600")
curl -s -H "X-aws-ec2-metadata-token: $TOKEN" http://169.254.169.254/latest/meta-data/instance-type

# Servicios web
sudo systemctl status php-fpm httpd --no-pager

# App responde
curl -sI https://app.higuerafc.cl/ | head -5
```

- [ ] `free -h` muestra ~8 Gi en **total**.
- [ ] Sitio `https://app.higuerafc.cl` carga y login funciona.
- [ ] Probar búsqueda de productos en cotización/NVV.

---

## Ajuste PHP-FPM recomendado (8 GB RAM)

Editar: `/etc/php-fpm.d/www.conf`

Valores orientativos para **8 GB** (Apache + MariaDB + PHP):

```ini
pm = dynamic
pm.max_children = 25
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 12
```

Antes estaba `pm.max_children = 50` (riesgo de llenar RAM aunque suban a 8 GB).

Aplicar:

```bash
sudo php-fpm -t
sudo systemctl restart php-fpm
sudo systemctl restart httpd
```

Verificar procesos:

```bash
ps aux | grep 'php-fpm: pool www' | wc -l
free -h
```

---

## Swap opcional (red de seguridad)

Si no hay swap, crear 2 GB (una sola vez):

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## Disco (revisar después del cambio)

- Raíz al **~79%** de 12 GB: planear limpieza o ampliar volumen EBS.
- Logs Laravel: `storage/logs/` (~116 MB en el análisis).
- Rotar/limpiar logs viejos si hace falta.

```bash
du -sh /var/www/html/wuayna/gespro_raviera/storage/logs
df -h /
```

---

## Optimización de búsqueda (siguiente fase — chat tarde)

No depende solo de RAM. Pendiente en código:

1. **Caché de stock** en `StockConsultaService::consultarStockDesdeSQLServer` (no forzar `tsql` en cada búsqueda).
2. Reducir **`Log::info`** en producción en rutas de búsqueda.
3. Confirmar índices en tabla `productos` (`KOPR`, `NOKOPR`, `activo`) en MySQL producción.

Archivos clave:

- `app/Http/Controllers/CotizacionBusquedaMejoradaController.php` → `buscarProductos`
- `app/Services/StockConsultaService.php`

---

## Checklist post-mantenimiento (18:30 Chile)

| Prueba | OK |
|--------|-----|
| Login app | ☐ |
| Listado `/aprobaciones` | ☐ |
| Abrir una NVV (`/aprobaciones/{id}`) | ☐ |
| **Búsqueda de productos** (cotización/NVV) — tiempo aceptable | ☐ |
| Informes → Estados NVV | ☐ |
| Aprobar picking (si aplica) | ☐ |

---

## Comandos útiles de referencia

```bash
# Carga y memoria en vivo
uptime && free -h

# Top procesos por memoria
ps aux --sort=-%mem | head -15

# Config PHP-FPM actual
grep -E '^pm\.' /etc/php-fpm.d/www.conf

# Reinicio stack web
sudo systemctl restart php-fpm httpd
```

---

## Retomar en Cursor (tarde)

Al volver al chat, indicar:

> Retomo la guía **GUIA-AMPLIACION-SERVIDOR-8GB.md**: ya subí la RAM a 8 GB. Revisar PHP-FPM y optimizar búsqueda.

Commits recientes relacionados (rama `app_higera`):

- `789495f` — rendimiento aprobaciones / MAEDTLI / stock batch  
- `2163841` — rol Consulta Informes  

---

## Notas

- **Región:** us-east-2 (Ohio).  
- **SQL Server ERP:** sigue siendo cuello de botella por red/`tsql`; la RAM no elimina esa latencia por sí sola.  
- **Cursor Server** en la misma máquina de desarrollo consume RAM (~2 GB en el análisis); en producción no aplica si solo es el servidor de la app.

*Documento generado para continuidad del trabajo. Última revisión: mayo 2026.*
