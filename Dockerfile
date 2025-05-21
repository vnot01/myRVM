# Stage 1: Base PHP image with necessary extensions
FROM php:8.2-fpm-alpine AS base_php

LABEL maintainer="VnoT <noreply.vnot@gmail.com>"
WORKDIR /var/www/html

# Install system dependencies (termasuk su-exec sebagai alternatif gosu yang lebih reliable di Alpine)
RUN apk update && apk add --no-cache \
    build-base \
    linux-headers \
    autoconf \
    automake \
    libtool \
    pkgconfig \
    curl \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    mariadb-client \
    imagemagick-dev \
    imagemagick \
    ghostscript \
    su-exec \ 
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-install pdo pdo_mysql zip bcmath intl opcache exif sockets pcntl \
    && rm -rf /var/cache/apk/*

# Install Composer
USER root
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version
# Copy entrypoint script dan jadikan executable (SEBAGAI ROOT)
COPY ./docker/app/entrypoint.sh /usr/local/bin/docker-app-entrypoint.sh
COPY ./docker/php/zz-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-fpm-pool.conf
RUN chmod +x /usr/local/bin/docker-app-entrypoint.sh

# APLIKASI DAN DEPENDENSI AKAN DIBUAT SEBAGAI WWW-DATA
USER www-data

# 1. Copy HANYA file composer.json dan composer.lock
COPY --chown=www-data:www-data composer.json composer.lock ./

# 2. Install dependensi (vendor directory akan dimiliki oleh www-data)
#    Jalankan composer install dengan --ignore-platform-reqs jika ada isu PHP version di lock file vs image
RUN composer install --no-interaction --no-autoloader --no-scripts --prefer-dist --no-dev --ignore-platform-reqs

# 3. Copy sisa kode aplikasi (file .env dari host akan di-copy di sini)
COPY --chown=www-data:www-data . .

# 4. Generate autoloader (sebagai www-data)
RUN composer dump-autoload --optimize --no-dev --no-scripts

# KEMBALI KE ROOT UNTUK ENTRYPOINT
USER root

EXPOSE 9000
ENTRYPOINT ["docker-app-entrypoint.sh"]
CMD ["php-fpm"]