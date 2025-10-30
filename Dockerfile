FROM php:8.2-fpm

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    nginx \
    nodejs \
    npm \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /app

# Copiar archivos
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Instalar dependencias Node y build
RUN npm ci && npm run build

# Configurar Nginx
RUN echo 'server { \
    listen $PORT; \
    root /app/public; \
    index index.php; \
    location / { \
        try_files $uri /index.php$is_args$args; \
    } \
    location ~ ^/index\.php(/|$) { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_split_path_info ^(.+\.php)(/.*)$; \
        include fastcgi_params; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
    } \
}' > /etc/nginx/sites-available/default

# Script de inicio
RUN echo '#!/bin/bash\n\
php-fpm -D\n\
envsubst '\''$PORT'\'' < /etc/nginx/sites-available/default > /etc/nginx/sites-enabled/default\n\
nginx -g "daemon off;"' > /start.sh && chmod +x /start.sh

CMD ["/start.sh"]