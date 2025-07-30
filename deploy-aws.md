# Despliegue en AWS - GesPro Raviera

## Configuración del Servidor AWS

### 1. Instalar dependencias en el servidor AWS

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP 8.1 y extensiones necesarias
sudo apt install php8.1 php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl php8.1-zip php8.1-gd php8.1-bcmath php8.1-odbc php8.1-sqlsrv -y

# Instalar Nginx
sudo apt install nginx -y

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Node.js y NPM
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Instalar MySQL
sudo apt install mysql-server -y
```

### 2. Instalar ODBC Driver para SQL Server

```bash
# Agregar repositorio de Microsoft
curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list > /etc/apt/sources.list.d/mssql-release.list

# Actualizar e instalar
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18
sudo apt-get install -y mssql-tools18 unixodbc-dev
```

### 3. Configurar Nginx

```nginx
# /etc/nginx/sites-available/gespro-raviera
server {
    listen 80;
    server_name tu-subdominio.tudominio.com;
    root /var/www/gespro-raviera/public;

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
```

### 4. Configurar Laravel

```bash
# Clonar el repositorio
cd /var/www
sudo git clone https://github.com/tu-usuario/gespro-raviera.git
sudo chown -R www-data:www-data gespro-raviera
cd gespro-raviera

# Instalar dependencias
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Configurar .env
cp .env.example .env
php artisan key:generate

# Configurar base de datos local (MySQL)
php artisan migrate
php artisan db:seed

# Configurar permisos
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 5. Configurar .env para AWS

```env
APP_NAME="GesPro Raviera"
APP_ENV=production
APP_KEY=base64:tu-key-generado
APP_DEBUG=false
APP_URL=https://tu-subdominio.tudominio.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gespro_raviera
DB_USERNAME=gespro_user
DB_PASSWORD=tu-password-seguro

# Configuración SQL Server externo
SQLSRV_EXTERNAL_HOST=152.231.92.82
SQLSRV_EXTERNAL_PORT=1433
SQLSRV_EXTERNAL_DATABASE=HIGUERA030924
SQLSRV_EXTERNAL_USERNAME=AMANECER
SQLSRV_EXTERNAL_PASSWORD=AMANECER

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
```

### 6. Configurar SSL (Let's Encrypt)

```bash
# Instalar Certbot
sudo apt install certbot python3-certbot-nginx -y

# Obtener certificado SSL
sudo certbot --nginx -d tu-subdominio.tudominio.com

# Configurar renovación automática
sudo crontab -e
# Agregar: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 7. Configurar Supervisor (opcional, para colas)

```bash
sudo apt install supervisor -y

# Crear configuración
sudo nano /etc/supervisor/conf.d/laravel-worker.conf

[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/gespro-raviera/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/www/gespro-raviera/storage/logs/worker.log
stopwaitsecs=3600
```

### 8. Comandos finales

```bash
# Habilitar sitio
sudo ln -s /etc/nginx/sites-available/gespro-raviera /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx

# Reiniciar servicios
sudo systemctl restart php8.1-fpm
sudo systemctl restart supervisor

# Optimizar Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Verificación

1. **Probar conexión SQL Server:**
   ```bash
   php artisan test:sqlsrv
   ```

2. **Crear vistas SQL Server:**
   ```bash
   php artisan sqlsrv:create-views
   ```

3. **Probar dashboard:**
   - Acceder a https://tu-subdominio.tudominio.com
   - Login con: vendedor@gespro.com / password
   - Verificar que se muestren los clientes asignados

## Mantenimiento

```bash
# Actualizar aplicación
cd /var/www/gespro-raviera
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.1-fpm
``` 