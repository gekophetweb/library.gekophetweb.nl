# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Website image for library.gekophetweb.nl (Laravel).
# One image serves three roles in docker-compose.yml: web, queue and scheduler.
# The database runs in its own container (see docker-compose.yml).
# ---------------------------------------------------------------------------

# --- 1. PHP dependencies ----------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# --- 2. Front-end assets (Vite) ----------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- 3. Runtime: nginx + PHP-FPM ---------------------------------------------
FROM serversideup/php:8.4-fpm-nginx AS runtime

# Extra extensions on top of the image defaults (pdo_mysql, redis, zip, opcache, ...).
# intl: locale-aware sorting/formatting of Japanese and other non-Latin titles.
USER root
RUN install-php-extensions intl bcmath
USER www-data

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

RUN php artisan package:discover --ansi

EXPOSE 8080
