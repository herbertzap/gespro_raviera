#!/bin/bash

# Script de despliegue automático para gespro_raviera
# Este script se ejecuta cuando GitHub envía un webhook

# Configuración
PROJECT_DIR="/var/www/html/wuayna/gespro_raviera"
LOG_FILE="/var/log/gespro_deploy.log"
BRANCH="main"

# Función para logging
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> $LOG_FILE
}

log "=== Iniciando despliegue automático ==="

# Cambiar al directorio del proyecto
cd $PROJECT_DIR

# Verificar que estamos en la rama correcta
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    log "Cambiando a la rama $BRANCH"
    git checkout $BRANCH
fi

# Obtener los últimos cambios
log "Obteniendo cambios del repositorio remoto..."
git fetch origin

# Verificar si hay cambios
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/$BRANCH)

if [ "$LOCAL" = "$REMOTE" ]; then
    log "No hay cambios nuevos. Despliegue cancelado."
    exit 0
fi

log "Hay cambios nuevos. Iniciando actualización..."

# Hacer pull de los cambios
git pull origin $BRANCH

# Actualizar dependencias de Composer
log "Actualizando dependencias de Composer..."
composer install --no-dev --optimize-autoloader

# Actualizar dependencias de NPM
log "Actualizando dependencias de NPM..."
npm install --production

# Compilar assets
log "Compilando assets..."
npm run build

# Ejecutar migraciones de base de datos
log "Ejecutando migraciones..."
php artisan migrate --force

# Limpiar caché
log "Limpiando caché..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
log "Optimizando para producción..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Establecer permisos correctos
log "Estableciendo permisos..."
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/bootstrap/cache

log "=== Despliegue completado exitosamente ==="
