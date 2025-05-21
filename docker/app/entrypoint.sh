#!/bin/sh
set -e # Exit immediately if a command exits with a non-zero status.

# Variabel untuk user dan grup target
TARGET_USER="www-data"
TARGET_GROUP="www-data"
APP_DIR="/var/www/html"

echo "Entrypoint: Script started. Current user: $(id -u):$(id -g)"

# Hanya jalankan setup permission dan inisialisasi jika dijalankan sebagai root
if [ "$(id -u)" = '0' ]; then
    echo "Entrypoint: Running as root. Setting up environment for user $TARGET_USER..."

    # 1. Pastikan direktori aplikasi ada dan dimiliki oleh user target
    # Ini penting karena volume mount dari host bisa memiliki owner berbeda.
    echo "Entrypoint: Chowning entire $APP_DIR to $TARGET_USER:$TARGET_GROUP..."
    chown -R $TARGET_USER:$TARGET_GROUP $APP_DIR
    echo "Entrypoint: Chown complete."

    # 2. Buat direktori Laravel yang krusial JIKA BELUM ADA, sebagai user target
    echo "Entrypoint: Ensuring Laravel directories exist..."
    su-exec $TARGET_USER mkdir -p $APP_DIR/storage/framework/sessions
    su-exec $TARGET_USER mkdir -p $APP_DIR/storage/framework/views
    su-exec $TARGET_USER mkdir -p $APP_DIR/storage/framework/cache/data
    su-exec $TARGET_USER mkdir -p $APP_DIR/storage/logs
    su-exec $TARGET_USER mkdir -p $APP_DIR/bootstrap/cache
    echo "Entrypoint: Laravel directories ensured."

    # 3. Atur permission yang benar untuk direktori storage dan bootstrap/cache
    # Ini dijalankan setelah chown seluruh APP_DIR, jadi path sudah dimiliki $TARGET_USER
    echo "Entrypoint: Setting writable permissions for storage and bootstrap/cache..."
    chmod -R ug+rwx $APP_DIR/storage
    chmod -R ug+rwx $APP_DIR/bootstrap/cache
    echo "Entrypoint: Writable permissions set."
    # Pastikan log file PHP-FPM bisa ditulis oleh user target
    # Ini penting untuk menghindari error permission denied saat PHP-FPM mencoba menulis log.
    if [ ! -f "/var/www/html/storage/logs/php-fpm.log" ]; then
        echo "Entrypoint: PHP-FPM log file not found in storage. Creating..."
        su-exec www-data touch /var/www/html/storage/logs/php-fpm.log
    fi
    if [ ! -w "/var/www/html/storage/logs/php-fpm.log" ]; then
        echo "Entrypoint: PHP-FPM log file not writable in storage. Changing permissions..."
        su-exec www-data chmod 666 /var/www/html/storage/logs/php-fpm.log
    fi
    # echo "Entrypoint: Ensuring PHP-FPM log file exists and is writable..."
    # su-exec www-data touch /var/www/html/storage/logs/php-fpm.log

    # 4. Tangani file .env
    if [ ! -f "$APP_DIR/.env" ]; then
        echo "Entrypoint: .env file not found. Copying from .env.example..."
        su-exec $TARGET_USER cp $APP_DIR/.env.example $APP_DIR/.env
    fi
    # Pastikan .env bisa ditulis oleh user target untuk key:generate
    chmod u+w $APP_DIR/.env
    echo "Entrypoint: .env file ensured and made writable for setup."

    # 5. Jalankan Composer install jika vendor belum ada (sebagai user target)
    if [ ! -d "$APP_DIR/vendor" ] || [ ! -f "$APP_DIR/vendor/autoload.php" ]; then
        echo "Entrypoint: Vendor directory not found or incomplete. Running composer install as $TARGET_USER..."
        su-exec $TARGET_USER composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-dev --ignore-platform-reqs
        echo "Entrypoint: Composer install finished."
    fi
    # Pastikan vendor juga dimiliki oleh user target setelah install (jika composer membuat file sebagai root)
    # chown -R $TARGET_USER:$TARGET_GROUP $APP_DIR/vendor # Seharusnya tidak perlu jika composer dijalankan sebagai $TARGET_USER

    # 6. Jalankan perintah inisialisasi Laravel HANYA SEKALI (sebagai user target)
    if [ ! -f "$APP_DIR/storage/initialized.flag" ]; then
        echo "Entrypoint: Running initial Laravel setup as $TARGET_USER..."
        su-exec $TARGET_USER php artisan key:generate --force --ansi
        su-exec $TARGET_USER php artisan config:clear --ansi
        su-exec $TARGET_USER php artisan cache:clear --ansi
        su-exec $TARGET_USER php artisan route:clear --ansi
        su-exec $TARGET_USER php artisan view:clear --ansi
        echo "Entrypoint: Attempting to migrate and seed database..."
        su-exec $TARGET_USER php artisan migrate --seed --force --ansi
        echo "Entrypoint: Migration and seeding complete."
        su-exec $TARGET_USER php artisan storage:link --ansi
        su-exec $TARGET_USER php artisan optimize:clear --ansi # Clear lagi semua cache
        su-exec $TARGET_USER php artisan ziggy:generate resources/js/ziggy.js --ansi
        # su-exec $TARGET_USER composer dump-autoload --no-dev --optimize # Seharusnya sudah saat build

        su-exec $TARGET_USER touch $APP_DIR/storage/initialized.flag
        echo "Entrypoint: Initial Laravel setup complete. Flag file created."
    else
        echo "Entrypoint: Laravel already initialized. Running routine clears..."
        su-exec $TARGET_USER php artisan optimize:clear --ansi
        su-exec $TARGET_USER php artisan ziggy:generate resources/js/ziggy.js --ansi
    fi

    # Amankan kembali file .env (jadikan read-only untuk owner)
    chmod u-w $APP_DIR/.env
    echo "Entrypoint: .env file secured (read-only for owner)."

else
    # Jika entrypoint tidak dijalankan sebagai root, ini adalah masalah besar untuk setup awal.
    echo "Entrypoint: CRITICAL WARNING - Not running as root (UID: $(id -u)). Cannot reliably set permissions or run initial setup."
    echo "Entrypoint: PHP-FPM might fail due to permission issues."
    # Kita tetap coba jalankan CMD, tapi kemungkinan besar akan error.
fi

# Jalankan perintah CMD dari Dockerfile (yaitu, php-fpm) sebagai TARGET_USER
echo "Entrypoint: Executing CMD (php-fpm) as $TARGET_USER..."
exec su-exec $TARGET_USER "$@"