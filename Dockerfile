FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    nginx \
    nodejs \
    npm \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Directorio de trabajo
WORKDIR /app

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN npm ci && npm run build

# Crear directorios necesarios
RUN mkdir -p /var/log/nginx /var/cache/nginx /app/var/cache /app/var/log && \
    chown -R www-data:www-data /app/var

# Configurar Nginx para servir desde /app/public
RUN echo 'server { \n\
    listen $PORT default_server; \n\
    root /app/public; \n\
    index index.php; \n\
    location / { \n\
        try_files $uri /index.php$is_args$args; \n\
    } \n\
    location ~ ^/index\.php(/|$) { \n\
        fastcgi_pass 127.0.0.1:9000; \n\
        fastcgi_split_path_info ^(.+\.php)(/.*)$; \n\
        include fastcgi_params; \n\
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; \n\
        fastcgi_param DOCUMENT_ROOT $realpath_root; \n\
        internal; \n\
    } \n\
    location ~ \.php$ { \n\
        return 404; \n\
    } \n\
}' > /etc/nginx/sites-available/default

# Script de inicio
RUN echo '#!/bin/bash\n\
set -e\n\
php-fpm -D\n\
export PORT=${PORT:-10000}\n\
sed -i "s/listen \$PORT/listen $PORT/g" /etc/nginx/sites-available/default\n\
nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

EXPOSE $PORT

CMD ["/start.sh"]
