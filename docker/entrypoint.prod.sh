#!/bin/sh
# VoxPilot POS — production entrypoint.
# Runs once at container start: waits for DB, runs migrations, sets permissions.
set -e
cd /var/www/html

# Wait for MySQL
echo "[pos] waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}"
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }'; do
  sleep 2
done

# First deploy: install TastyIgniter schema + seed admin. Subsequent: no-op.
php artisan igniter:install --no-interaction || echo "[pos] igniter:install exited $? (already installed?)"

# Run pending migrations. `migrate` only sees the app's own migrations; the TastyIgniter core and
# extension migrations (VoxPilot SPEC-011: voxpilot_auth_codes, voxpilot_installations) need igniter:up.
php artisan migrate --force --no-interaction
php artisan igniter:up --force --no-interaction

# Publish admin + theme assets into public/vendor (gitignored, so not in the image).
# Without this the storefront and admin render unstyled.
php artisan vendor:publish --tag=laravel-assets --force --no-interaction
# VoxPilot branding: admin favicon (published by TastyIgniter core) and the site name used in
# browser titles and email sender names.
cp public/voxpilot/favicon.svg public/vendor/igniter/images/favicon.svg
php artisan voxpilot:brand --no-interaction

# Ensure writable dirs
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache public/vendor 2>/dev/null || true

# Clear caches for fresh config
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "[pos] ready"
exec "$@"
