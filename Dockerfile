# syntax=docker/dockerfile:1.7
#
# Imagen de producción de Mercova para Render (web, worker de colas y cron
# usan esta misma imagen; render.yaml cambia solo el comando de arranque).
# Las variables de entorno llegan en tiempo de ejecución, por eso la caché
# de configuración/rutas se genera al arrancar y no al compilar.

# --- 1. Assets del panel Blade (Vite + Tailwind) -----------------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- 2. Aplicación PHP + Apache ---------------------------------------------
FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'upload_max_filesize=8M'; \
        echo 'post_max_size=10M'; \
        echo 'memory_limit=256M'; \
        echo 'expose_php=Off'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/zz-mercova.ini"

# DocumentRoot en public/ y .htaccess de Laravel habilitado.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

WORKDIR /var/www/html

# Dependencias primero (capa cacheable mientras composer.lock no cambie).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

# Arranque del servicio web. Render indica el puerto en $PORT (10000 por
# defecto). El Persistent Disk se monta en storage/app/public al arrancar,
# por eso los permisos se ajustan aquí y no al compilar.
COPY <<'EOF' /usr/local/bin/mercova-web
#!/bin/sh
set -e
PORT="${PORT:-10000}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
mkdir -p storage/app/public
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
EOF
RUN chmod +x /usr/local/bin/mercova-web

EXPOSE 10000
CMD ["mercova-web"]
