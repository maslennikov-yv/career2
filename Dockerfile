# ── stage 1: PHP production dependencies ─────────────────────────────────────
FROM composer:2 AS composer-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-interaction --no-progress \
    --prefer-dist --optimize-autoloader --no-scripts

# ── stage 2: generate Wayfinder TypeScript bindings ───────────────────────────
FROM php:8.3-cli-bookworm AS wayfinder
WORKDIR /app
COPY --from=composer-deps /app/vendor ./vendor
COPY . .
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    APP_ENV=production \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/tmp/wayfinder.sqlite \
    php artisan wayfinder:generate --with-form

# ── stage 3: frontend assets ─────────────────────────────────────────────────
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder
# PHP недоступен в node-стейдже; файлы уже сгенерированы в wayfinder-стейдже
ENV WAYFINDER_COMMAND=true
RUN npm run build

# ── stage 4: production image ─────────────────────────────────────────────────
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor libpq-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql pcntl bcmath zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=composer-deps /app/vendor ./vendor
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build

RUN rm -f /etc/nginx/sites-enabled/default
COPY deploy/nginx.conf /etc/nginx/conf.d/app.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY deploy/entrypoint.sh /entrypoint.sh

RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
