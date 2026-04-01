#!/bin/bash
set -e

echo "=== BUNHS Startup (Railway-safe) ==="

# Wait for DB if vars set (Railway MySQL)
if [[ -n "$DB_HOST" ]]; then
  echo "Waiting for MySQL at $DB_HOST:${DB_PORT:-3306}..."
  until nc -z "$DB_HOST" "${DB_PORT:-3306}"; do
    echo "DB not ready, waiting..."
    sleep 2
  done
  echo "✅ DB ready!"
fi

# CRITICAL: RUNTIME mysqli verification (Railway/FrankenPHP-specific)
echo "=== Runtime PHP mysqli check ==="
php -m | grep mysqli >/dev/null && echo "✅ mysqli LOADED (runtime)" || { 
    echo "❌ FATAL: mysqli MISSING (runtime)"; 
    php --version; 
    php -m | head -20; 
    exit 1; 
}

# Ensure PORT exists
PORT="${PORT:-8080}"
echo "Starting PHP server on 0.0.0.0:${PORT}..."

# Start PHP built-in server (Railway/Nixpacks standard)
exec php -S "0.0.0.0:${PORT}" -t .
