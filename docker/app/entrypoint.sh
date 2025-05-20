#!/bin/sh
set -e # Exit immediately if a command exits with a non-zero status.

# Cek apakah direktori vendor ada, jika tidak, jalankan composer install
# Ini berguna jika Anda tidak me-mount vendor dari host atau build awal gagal sebagian
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Vendor directory not found or incomplete. Running composer install..."
    composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-dev
fi

# Atur permission untuk Laravel (dijalankan sebagai root karena entrypoint biasanya root)
# Pastikan direktori ada sebelum chown/chmod
mkdir -p /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

# Pastikan file .env ada dan writable oleh www-data jika key:generate perlu dijalankan
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env file from .env.example"
    cp /var/www/html/.env.example /var/www/html/.env
    chown www-data:www-data /var/www/html/.env # Beri kepemilikan ke www-data
fi
# Beri izin tulis ke .env untuk www-data (sementara jika key perlu digenerate)
# Hati-hati dengan ini di produksi.
chmod u+w /var/www/html/.env

# Jalankan perintah artisan yang dibutuhkan HANYA jika belum diinisialisasi
# Kita bisa menggunakan file "flag" untuk menandai inisialisasi
if [ ! -f /var/www/html/storage/initialized.flag ]; then
    echo "Running initial Laravel setup (key:generate, migrate, seed, storage:link)..."
    php artisan key:generate --force
    php artisan migrate --seed --force
    php artisan storage:link
    # Untuk development, kita mungkin tidak mau cache config/route di sini
    # php artisan config:cache
    # php artisan route:cache
    # php artisan view:cache
    php artisan optimize:clear
    php artisan ziggy:generate
    composer dump-autoload --no-dev --optimize # Jalankan lagi setelah semua ada

    touch /var/www/html/storage/initialized.flag # Buat file flag
    chown www-data:www-data /var/www/html/storage/initialized.flag
    echo "Initial Laravel setup complete."
else
    echo "Laravel already initialized. Skipping initial setup."
    # Mungkin jalankan optimize:clear saja untuk memastikan
    php artisan optimize:clear
    php artisan ziggy:generate # Tetap generate ziggy jika rute berubah
fi

# Kembalikan permission .env ke lebih aman setelah setup (jika perlu)
# chmod u-w /var/www/html/.env

echo "Starting PHP-FPM..."
# Jalankan perintah CMD dari Dockerfile (yaitu, php-fpm)
exec "$@"