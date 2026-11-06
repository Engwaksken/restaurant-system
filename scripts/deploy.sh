#!/usr/bin/env bash
set -euo pipefail
php artisan down || true
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
printf '\nDeployment complete.\n'
