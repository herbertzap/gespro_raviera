# Usa la imagen base de PHP con las extensiones necesarias
FROM php:8.0-fpm

# Instala dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Instala extensiones de PHP requeridas por Laravel
RUN docker-php-ext-install pdo pdo_mysql

# Configura el directorio de trabajo
WORKDIR /var/www

# Copia el código fuente
COPY . .

# Ajusta permisos para el usuario web
RUN chown -R www-data:www-data /var/www

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Ejecuta Composer para instalar las dependencias de Laravel
RUN composer install

# Exponer el puerto
EXPOSE 9000

# Comando por defecto
CMD ["php-fpm"]
