#!/bin/sh
set -e

# =============================================================================
# Node / Vite Entrypoint
# Handles first-run npm install inside the development container.
# =============================================================================

# Install npm dependencies if node_modules does not exist
if [ ! -d "/var/www/node_modules" ] || [ ! -f "/var/www/node_modules/.package-lock.json" ]; then
    echo "[entrypoint] Installing npm dependencies..."
    npm install
fi

echo "[entrypoint] Starting Node dev server..."
exec "$@"
