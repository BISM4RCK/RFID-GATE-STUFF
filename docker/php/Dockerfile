# syntax=docker/dockerfile:1.7
# Smart Gate production image: Laravel/PHP 8.4 + React/Vite/Tailwind build.

FROM node:22-alpine AS frontend-build
WORKDIR /build

COPY backend/package.json ./package.json
RUN npm install --no-audit --no-fund --legacy-peer-deps

COPY backend/ ./
ENV NODE_ENV=production
ENV NODE_OPTIONS=--max-old-space-size=4096
RUN npm run build

FROM composer:2 AS composer-deps
WORKDIR /app
COPY backend/composer.json ./composer.json
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM php:8.4-fpm-alpine AS app
WORKDIR /var/www/html

RUN apk add --no-cache icu-libs libzip libxml2 oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring intl opcache xml \
    && apk del .build-deps

COPY --from=composer-deps /app/vendor ./vendor
COPY backend/ ./
COPY --from=frontend-build /build/public/build ./public/build
RUN cp public/build/index.html public/index.html

RUN php -r '$route=file_get_contents("routes/api.php"); $controller=file_get_contents("app/Http/Controllers/Api/AuthController.php"); if (strpos($route, "/auth/login") === false || strpos($controller, "function login") === false) { fwrite(STDERR, "ERROR: login endpoint missing\\n"); exit(1); }'

RUN find app database routes -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/php-lint.log && cat /tmp/php-lint.log

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && cp -a public /opt/smart-gate-public

COPY docker/php/entrypoint.sh /usr/local/bin/smart-gate-entrypoint
RUN chmod +x /usr/local/bin/smart-gate-entrypoint

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

EXPOSE 9000
ENTRYPOINT ["smart-gate-entrypoint"]
CMD ["php-fpm", "-F"]
