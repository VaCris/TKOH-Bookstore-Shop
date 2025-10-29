FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    nginx

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install opcache

# Copiar Composer desde imagen oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www/symfony

# Copiar todos los archivos del proyecto
COPY . /var/www/symfony

# Instalar dependencias de Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader

# Limpiar caché de Symfony en modo producción
RUN php bin/console cache:clear --env=prod

# Copiar configuración de Nginx
COPY nginx/vhost.conf /etc/nginx/sites-available/default

# Dar permisos a directorio var
RUN chmod -R 777 var/

# Comando para iniciar PHP-FPM y Nginx
CMD service php8.2-fpm start && nginx -g 'daemon off;'

# Exponer puerto 80
EXPOSE 80
