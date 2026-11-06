# syntax=docker/dockerfile:1

# 1. Build the frontend (Vue and Tailwind) into public/build.
FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources resources
RUN npm run build

# 2. Download the PHP packages. Only the production ones are needed. The composer image has its own
#    PHP, so the version check is skipped here; the final image does the check when the app starts.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --ignore-platform-reqs

# 3. The image that runs: PHP 8.5 with Apache. composer.json asks for 8.5.12 or newer, and the
#    8.5 tag always points at the newest 8.5 release.
FROM php:8.5-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor vendor
COPY --from=frontend /app/public/build public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

# Render sends its traffic to the port in the PORT variable, which is 10000 for web services.
EXPOSE 10000
ENTRYPOINT ["entrypoint"]
