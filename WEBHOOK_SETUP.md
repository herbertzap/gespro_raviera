# Configuración de Webhook para Despliegue Automático

## Configuración del Webhook en GitHub

1. **Ir a la configuración del repositorio en GitHub:**
   - Ve a tu repositorio en GitHub
   - Haz clic en "Settings" (Configuración)
   - En el menú lateral, haz clic en "Webhooks"
   - Haz clic en "Add webhook"

2. **Configurar el webhook:**
   - **Payload URL:** `http://admin-base.test/webhook/github`
   - **Content type:** `application/json`
   - **Secret:** Genera un secret seguro (puedes usar: `openssl rand -hex 32`)
   - **Events:** Selecciona "Just the push event"
   - **Active:** ✅ Marcado

3. **Agregar la variable de entorno:**
   Agrega esta línea a tu archivo `.env`:
   ```
   GITHUB_WEBHOOK_SECRET=tu_secret_generado_aqui
   ```

## Archivos Creados

- `deploy.sh`: Script de despliegue automático
- `app/Http/Controllers/WebhookController.php`: Controlador para manejar webhooks
- Ruta agregada en `routes/web.php`: `/webhook/github`

## Funcionamiento

1. Cuando se hace push a la rama `main` en GitHub
2. GitHub envía un webhook a `http://admin-base.test/webhook/github`
3. El controlador verifica la firma del webhook
4. Si es válido, ejecuta el script `deploy.sh` en segundo plano
5. El script actualiza el código, dependencias y optimiza la aplicación

## Logs

Los logs del despliegue se guardan en: `/var/log/gespro_deploy.log`

## Verificación

Para verificar que funciona:
1. Haz un commit y push a la rama main
2. Revisa los logs: `tail -f /var/log/gespro_deploy.log`
3. Verifica que la aplicación se actualizó correctamente
