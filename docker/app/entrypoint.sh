#!/bin/sh
set -e

# =============================================================================
# Laravel PHP-FPM Entrypoint
# Handles first-run setup inside the development container.
# =============================================================================

# Ensure storage and cache directories exist and are writable
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/framework/testing
mkdir -p /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true

# Install composer dependencies if vendor directory does not exist
if [ ! -d "/var/www/vendor" ] || [ ! -f "/var/www/vendor/autoload.php" ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Generate application key if .env exists but APP_KEY is missing/empty
if [ -f "/var/www/.env" ]; then
    APP_KEY_VALUE=$(grep -E '^APP_KEY=' /var/www/.env | cut -d '=' -f2- | tr -d '"' || true)
    if [ -z "$APP_KEY_VALUE" ]; then
        echo "[entrypoint] APP_KEY is empty. Generating application key..."
        php artisan key:generate --no-interaction || true
    fi
else
    echo "[entrypoint] WARNING: /var/www/.env not found. Laravel may not function correctly."
fi

# Run migrations automatically on first boot if the database is reachable
# Use a 5-second timeout to avoid hanging if MySQL isn't ready yet
if php -r "
try {
    \$pdo = new PDO('mysql:host=db;port=3306;dbname=car_maintenance', 'root', 'secret', [PDO::ATTR_TIMEOUT => 3]);
    echo 'DB_READY';
} catch (Exception \$e) {
    echo 'DB_NOT_READY';
}
" 2>/dev/null | grep -q "DB_READY"; then
    echo "[entrypoint] Database is reachable."
else
    echo "[entrypoint] Database not yet reachable — skipping migrations (run them manually with: docker-compose exec app php artisan migrate)"
fi

echo "[entrypoint] Starting php-fpm..."
exec "$@"
