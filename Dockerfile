# Stage 1: Base PHP image with necessary extensions
FROM php:8.2-fpm-alpine AS base_php

LABEL maintainer="Nama Anda <emailanda@example.com>"
WORKDIR /var/www/html

# Install system dependencies (seperti sebelumnya)
RUN apk update && apk add --no-cache \
    build-base shadow linux-headers autoconf automake libtool pkgconfig \
    curl libzip-dev zip unzip libpng-dev libjpeg-turbo-dev freetype-dev \
    icu-dev oniguruma-dev mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql zip bcmath intl opcache exif sockets pcntl

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set user www-data
USER www-data

# 1. Copy HANYA file composer.json dan composer.lock
COPY --chown=www-data:www-data composer.json composer.lock ./

# 2. Install dependensi TANPA menjalankan scripts DAN TANPA autoloader
RUN composer install --no-interaction --no-autoloader --no-scripts --prefer-dist --no-dev

# RUN cp .env.example .env

# 3. Copy sisa kode aplikasi (termasuk file artisan)
COPY --chown=www-data:www-data . .

# 4. Generate autoloader
RUN composer dump-autoload --optimize --no-dev
#   (Hapus --classmap-authoritative untuk dev jika dirasa perlu)

# 5. Jalankan package:discover
RUN php artisan package:discover --ansi

# Expose port PHP-FPM
EXPOSE 9000

# Default command
CMD ["php-fpm"]