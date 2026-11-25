#!/usr/bin/env bash
# Run on the server after uploading new code:  bash deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

php artisan down --retry=30 || true
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund
npm run build
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
chown -R www-data:www-data storage bootstrap/cache
php artisan up
echo "Deployed ✔"
