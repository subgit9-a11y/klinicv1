# Klinic 360 — production image
# Multi-stage build: composer + npm in builder stages, slim runtime image.
FROM php:8.4-fpm-alpine AS base

# Runtime PHP extensions required by Laravel 13 + app deps
RUN apk add --no-cache \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    curl \
    git \
    mysql-client \
    redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mysqli gd zip xml mbstring intl bcmath opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del --no-cache libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
        libzip-dev libxml2-dev oniguruma-dev icu-dev

# ------------------------------------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize

# ------------------------------------------------------------------
FROM base AS runtime
WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html
COPY --from=frontend /var/www/html/public/build /var/www/html/public/build
COPY . .

# Nginx + Supervisor config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Storage & bootstrap cache permissions
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/build

ENV PHP_FPM_LISTEN=/var/run/php-fpm.sock
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
