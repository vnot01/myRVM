# Stage 1: Base PHP image with necessary extensions
FROM php:8.2-fpm-alpine AS base_php

LABEL maintainer="VnoT <noreply.vnot@gmail.com>"
WORKDIR /var/www/html

# Install system dependencies (seperti sebelumnya)
RUN apk update && apk add --no-cache \
    build-base \
    shadow \
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
    # Dependensi untuk Imagick
    imagemagick-dev \
    imagemagick \
    # Ghostscript mungkin diperlukan untuk beberapa format seperti PDF
    ghostscript \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    # Instal Imagick menggunakan pecl
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    # Instal ekstensi lain yang dibutuhkan
    && docker-php-ext-install pdo pdo_mysql zip bcmath intl opcache exif sockets pcntl \
    # Bersihkan cache apk setelah instalasi
    && rm -rf /var/cache/apk/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Set user www-data
USER www-data

COPY --chown=www-data:www-data ./docker/app/entrypoint.sh /usr/local/bin/docker-app-entrypoint.sh
COPY --chown=www-data:www-data . .

# Beri izin eksekusi ke entrypoint script
# Sementara untuk chmod
USER root 
RUN chmod +x /usr/local/bin/docker-app-entrypoint.sh
# Kembali ke www-data
USER www-data 

# 1. Copy HANYA file composer.json dan composer.lock
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN chmod +x /usr/local/bin/composer
# COPY --chown=www-data:www-data composer.json composer.lock ./

# 2. Install dependensi TANPA menjalankan scripts DAN TANPA autoloader
RUN composer install --no-interaction --no-autoloader --no-scripts --prefer-dist --no-dev

# Jika ada file .env.example, bisa disalin ke .env
# RUN cp .env.example .env

# 3. Copy sisa kode aplikasi (termasuk file artisan)
# COPY --chown=www-data:www-data . .

# 4. Generate autoloader
RUN composer dump-autoload --optimize --no-dev
#   (Hapus --classmap-authoritative untuk dev jika dirasa perlu)

RUN php artisan key:generate --ansi
RUN php artisan migrate --seed --force
RUN php artisan storage:link --ansi
# 5. Jalankan package:discover
RUN php artisan package:discover --ansi
RUN php artisan config:clear
RUN php artisan package:discover --ansi
RUN php artisan optimize:clear && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan config:clear && \
    php artisan cache:clear && \
    php artisan ziggy:generate && \
    composer dump-autoload --optimize --no-dev

# Expose port PHP-FPM
EXPOSE 9000
# Tentukan entrypoint
ENTRYPOINT ["docker-app-entrypoint.sh"]
# Default command (akan diteruskan sebagai argumen ke entrypoint)
# Default command
CMD ["php-fpm"]