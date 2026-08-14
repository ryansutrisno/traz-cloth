# Build PHP dependencies without development packages.
FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Generate Wayfinder routes/actions (needs PHP; the frontend stage has none).
FROM php:8.4-cli-alpine AS wayfinder

WORKDIR /var/www/html

COPY . .

COPY --from=vendor /var/www/html/vendor ./vendor

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && touch .env \
    && php artisan wayfinder:generate --with-form --no-interaction \
    && rm -f .env

# Build the Inertia/React assets with the project's pnpm lockfile.
FROM node:22-alpine AS frontend

WORKDIR /var/www/html

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./

RUN corepack enable && pnpm install --frozen-lockfile --ignore-scripts

COPY . .

COPY --from=wayfinder /var/www/html/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /var/www/html/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /var/www/html/resources/js/wayfinder ./resources/js/wayfinder

ENV WAYFINDER_SKIP_PHP=1

RUN pnpm run build

# Production PHP-FPM runtime.
FROM php:8.4-fpm-alpine AS runtime

WORKDIR /var/www/html

RUN apk add --no-cache curl postgresql-client postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql bcmath opcache \
    && docker-php-ext-enable opcache \
    && apk del postgresql-dev $PHPIZE_DEPS

COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build ./public/build
COPY --chown=www-data:www-data . .

COPY docker/entrypoint.sh /usr/local/bin/traz-cloth-entrypoint
RUN chmod +x /usr/local/bin/traz-cloth-entrypoint \
    && mkdir -p storage/app/backups \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["traz-cloth-entrypoint"]
CMD ["php-fpm", "-F"]
