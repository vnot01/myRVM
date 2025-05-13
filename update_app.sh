#!/bin/bash
echo ">>> Starting application update from GitHub..."
# Navigasi ke direktori proyek Anda
cd Documents/mysuperapps/myRVM || { echo "Failed to cd to project directory"; exit 1; }

echo ">>> Pulling latest changes from origin main..."
git pull origin master

echo ">>> Installing/updating Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
# --no-interaction: Jangan tanya apa pun
# --prefer-dist: Lebih cepat, download zip
# --no-dev: Abaikan require-dev

# echo ">>> Running database migrations..."
# php artisan migrate --force # --force agar tidak ada prompt konfirmasi

echo ">>> Clearing and Caching optimizations..."
php artisan optimize:clear # Menghapus semua cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Jika Anda menggunakan Vite/Mix untuk aset frontend
echo ">>> Building frontend assets..."
npm install
npm run build

echo ">>> Application update script finished."
# Jika Anda menggunakan php artisan serve dengan supervisor/pm2, restart di sini
# Contoh: sudo supervisorctl restart laravel-worker:* (jika worker)
# Contoh: pm2 restart my-laravel-app