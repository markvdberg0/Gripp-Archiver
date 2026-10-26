# Werkbank — productie-image (PHP 8.4 + Apache). Zie docs/installatie.md.
# Bouwen:  docker build -t werkbank .

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Het composer-image heeft niet alle PHP-extensies; die zitten in het runtime-image hieronder.
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --ignore-platform-reqs

FROM php:8.4-apache

# PHP-extensies (installer: https://github.com/mlocati/docker-php-extension-installer, MIT)
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql pdo_pgsql intl zip gd bcmath opcache \
    && a2enmod rewrite headers \
    && apt-get update && apt-get install -y --no-install-recommends sqlite3 \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/werkbank.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/werkbank-entrypoint
RUN chmod +x /usr/local/bin/werkbank-entrypoint \
    && rm -f .env \
    && mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs database/data \
    && chown -R www-data:www-data storage bootstrap/cache database

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/database/data/werkbank.sqlite

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s CMD curl -fsS http://localhost/up || exit 1
ENTRYPOINT ["werkbank-entrypoint"]
CMD ["apache2-foreground"]
