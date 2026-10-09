# VoxPilot POS — production image (TastyIgniter 4.x + VoxPilot extension).
# Self-contained: source baked in, vendor installed, Apache on :80, Reverb on :6001.
# Coolify builds this from the voxpilot-pos-core repo root.
FROM php:8.3-apache AS base

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
      libicu-dev libonig-dev supervisor \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli gd intl zip bcmath exif opcache pcntl \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf 'upload_max_filesize=32M\npost_max_size=32M\nmemory_limit=512M\nmax_execution_time=120\n' \
      > /usr/local/etc/php/conf.d/voxpilot-pos.ini

# ---------- Build stage: install dependencies ----------
FROM base AS build

WORKDIR /var/www/html
COPY composer.json composer.lock* ./
# Scripts and the autoloader need the app (artisan): install the packages first (cached layer),
# then dump the autoloader once the code is copied.
RUN composer install --no-interaction --prefer-dist --no-progress --no-dev --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------- Production image ----------
FROM base AS production

WORKDIR /var/www/html

COPY --from=build /var/www/html /var/www/html

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
             storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/voxpilot.conf
COPY docker/entrypoint.prod.sh /usr/local/bin/pos-entrypoint
RUN chmod +x /usr/local/bin/pos-entrypoint

EXPOSE 80 6001

ENTRYPOINT ["pos-entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/voxpilot.conf"]
