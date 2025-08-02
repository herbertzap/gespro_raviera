#!/bin/bash

# Script de configuración para GesPro Raviera en AWS
# Ejecutar como root o con sudo

echo "🚀 Configurando GesPro Raviera en AWS..."

# 1. Actualizar sistema
echo "📦 Actualizando sistema..."
apt update && apt upgrade -y

# 2. Instalar PHP 8.1 y extensiones
echo "🐘 Instalando PHP 8.1 y extensiones..."
apt install php8.1 php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd php8.1-bcmath php8.1-odbc php8.1-sqlsrv -y

# 3. Instalar Nginx
echo "🌐 Instalando Nginx..."
apt install nginx -y

# 4. Instalar Composer
echo "📦 Instalando Composer..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 5. Instalar Node.js y NPM
echo "📦 Instalando Node.js..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

# 6. Instalar MySQL
echo "🗄️ Instalando MySQL..."
apt install mysql-server -y

# 7. Instalar ODBC Driver para SQL Server
echo "🔌 Instalando ODBC Driver para SQL Server..."
curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list > /etc/apt/sources.list.d/mssql-release.list
apt-get update
ACCEPT_EULA=Y apt-get install -y msodbcsql18
apt-get install -y mssql-tools18 unixodbc-dev

# 8. Configurar permisos del proyecto
echo "🔐 Configurando permisos..."
chown -R www-data:www-data /var/www/html/wuayna/gespro_raviera
chmod -R 755 /var/www/html/wuayna/gespro_raviera
chmod -R 775 /var/www/html/wuayna/gespro_raviera/storage
chmod -R 775 /var/www/html/wuayna/gespro_raviera/bootstrap/cache

# 9. Instalar dependencias de Laravel
echo "📦 Instalando dependencias de Laravel..."
cd /var/www/html/wuayna/gespro_raviera
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 10. Crear archivo .env
echo "⚙️ Configurando archivo .env..."
cat > .env << 'EOF'
APP_NAME="GesPro Raviera"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.wuayna.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gespro_raviera
DB_USERNAME=gespro_user
DB_PASSWORD=gespro_password_2024

# Configuración SQL Server externo
SQLSRV_EXTERNAL_HOST=
SQLSRV_EXTERNAL_PORT=1433
SQLSRV_EXTERNAL_DATABASE=
SQLSRV_EXTERNAL_USERNAME=
SQLSRV_EXTERNAL_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
EOF

# 11. Generar clave de aplicación
echo "🔑 Generando clave de aplicación..."
php artisan key:generate

# 12. Configurar base de datos MySQL
echo "🗄️ Configurando base de datos MySQL..."
mysql -e "CREATE DATABASE IF NOT EXISTS gespro_raviera;"
mysql -e "CREATE USER IF NOT EXISTS 'gespro_user'@'localhost' IDENTIFIED BY 'gespro_password_2024';"
mysql -e "GRANT ALL PRIVILEGES ON gespro_raviera.* TO 'gespro_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# 13. Ejecutar migraciones
echo "🔄 Ejecutando migraciones..."
php artisan migrate --force

# 14. Optimizar Laravel
echo "⚡ Optimizando Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 15. Configurar Nginx
echo "🌐 Configurando Nginx..."
cat > /etc/nginx/sites-available/app.wuayna.com << 'EOF'
server {
    listen 80;
    server_name app.wuayna.com;
    root /var/www/html/wuayna/gespro_raviera/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# 16. Habilitar sitio
ln -sf /etc/nginx/sites-available/app.wuayna.com /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
systemctl restart php8.1-fpm

echo "✅ Configuración completada!"
echo ""
echo "📋 Próximos pasos:"
echo "1. Configurar las credenciales SQL Server en /var/www/html/wuayna/gespro_raviera/.env"
echo "2. Configurar SSL con Let's Encrypt: certbot --nginx -d app.wuayna.com"
echo "3. Probar la aplicación: https://app.wuayna.com"
echo ""
echo "🔧 Comandos útiles:"
echo "- Probar conexión SQL Server: php artisan test:sqlsrv"
echo "- Crear vistas SQL Server: php artisan sqlsrv:create-views"
echo "- Ver logs: tail -f /var/www/html/wuayna/gespro_raviera/storage/logs/laravel.log" 