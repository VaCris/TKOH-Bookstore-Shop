FROM php:8.2-fpm-alpine

# Instalar dependencias necesarias
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    unzip

# Instalar extensiones PHP
RUN docker-php-ext-install opcache pdo pdo_mysql

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurar directorio
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Limpiar caché
RUN php bin/console cache:clear --env=prod --no-warmup || true
RUN php bin/console cache:warmup --env=prod || true

# Configurar Nginx
RUN rm /etc/nginx/http.d/default.conf
COPY <<EOF /etc/nginx/http.d/default.conf
server {
    listen 80;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\.php(/|\$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$document_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }
}
EOF

# Configurar Supervisor
COPY <<EOF /etc/supervisord.conf
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm8 -F
autostart=true
autorestart=true

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
EOF

# Permisos
RUN chown -R nobody:nobody /var/www/html/var
RUN chmod -R 777 /var/www/html/var

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]