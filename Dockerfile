# syntax=docker/dockerfile:1

# =====================================================================
# Secure Login — Produktions-Image
#
# Enthaelt PHP-FPM, die benoetigten Erweiterungen und die Abhaengigkeiten
# aus composer. Der Webserver nginx liegt als eigener Container vor und
# spricht ueber das interne Netzwerk mit diesem Dienst.
# =====================================================================

FROM php:8.3-fpm-alpine

# --- PHP-Erweiterungen ------------------------------------------------
# pdo_mysql  : Datenbankzugriff
# mbstring   : Unicode-Funktionen fuer E-Mail-Adressen
# opcache    : Bytecode-Cache
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring opcache \
    && apk del .build-deps \
    && docker-php-ext-enable opcache

# --- Konfiguration ----------------------------------------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# --- Abhaengigkeiten --------------------------------------------------
# composer.json und composer.lock werden zuerst kopiert, damit die
# Installation bei unveraenderter Datei aus dem Cache kommt.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# --- Anwendungscode ---------------------------------------------------
COPY src/ ./src/
COPY templates/ ./templates/
COPY public/ ./public/
COPY bin/ ./bin/

RUN mkdir -p var/cache/twig \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r '$s = @fsockopen("127.0.0.1", 9000); exit($s ? 0 : 1);'

CMD ["php-fpm", "-F"]
