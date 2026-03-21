#!/bin/bash
set -e

echo "=== BUNHS Startup ==="

# Wait for DB if vars set (Railway MySQL)
if [[ -n "$DB_HOST" ]]; then
  echo "Waiting for MySQL at $DB_HOST:${DB_PORT:-3306}..."
  until nc -z "$DB_HOST" "${DB_PORT:-3306}"; do
    echo "DB not ready, waiting..."
    sleep 2
  done
  echo "✅ DB ready!"
fi

# Verify mysqli loaded (critical)
echo "Checking PHP extensions..."
php -m | grep mysqli >/dev/null && echo "✅ mysqli extension LOADED" || { echo "❌ FATAL: mysqli extension MISSING! Run: php -m"; php -m | head -20; exit 1; }

# Ensure PORT exists
PORT="${PORT:-8080}"
echo "Starting PHP server on 0.0.0.0:${PORT}..."

# Start PHP built-in server (Nixpacks/Railway standard)
exec php -S "0.0.0.0:${PORT}" -t .
