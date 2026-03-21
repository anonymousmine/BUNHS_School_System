#!/bin/bash
set -e

# Wait for DB if vars set (Railway MySQL)
if [[ -n "$DB_HOST" ]]; then
  echo "Waiting for MySQL at $DB_HOST:$DB_PORT..."
  until nc -z "$DB_HOST" "$DB_PORT"; do
    echo "DB not ready, waiting..."
    sleep 2
  done
  echo "DB ready!"
fi

# Verify mysqli loaded
php -m | grep -q mysqli || { echo "FATAL: mysqli extension not loaded!"; exit 1; }
echo "✅ mysqli extension loaded"

# Ensure PORT exists
PORT=${PORT:-8080}
echo "Starting server on port $PORT..."

# Start FrankenPHP properly
exec frankenphp run --address 0.0.0.0:${PORT}