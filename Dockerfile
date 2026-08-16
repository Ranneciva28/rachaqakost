FROM composer:2 AS build
WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j$(nproc) pdo_pgsql opcache zip \
    && (a2dismod mpm_event mpm_worker || true) \
    && a2enmod mpm_prefork rewrite headers expires \
    && sed -ri 's!^Listen 80$!Listen ${PORT}!' /etc/apache2/ports.conf \
    && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public PORT=8080
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --from=build /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/rachaqakost-entrypoint
RUN chmod +x /usr/local/bin/rachaqakost-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
WORKDIR /var/www/html
EXPOSE 8080
ENTRYPOINT ["rachaqakost-entrypoint"]
CMD ["apache2-foreground"]
