FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader --ignore-platform-reqs

FROM php:8.3-apache

# pdo_mysql für die Datenbank; sodium und mbstring bringt das Image mit
RUN docker-php-ext-install pdo_mysql opcache \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Keine PHP-Fehler im Browser, Fehler ins Docker-Log, nur selbst vergebene Sitzungs-IDs
RUN printf '%s\n' 'display_errors=Off' 'error_log=/dev/stderr' 'expose_php=Off' 'session.use_strict_mode=1' \
    > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY src/ ./src/
COPY templates/ ./templates/
COPY public/ ./public/

EXPOSE 80
