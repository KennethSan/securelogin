#!/bin/bash
set -e

echo "Starting custom entry point script..."

# Create necessary directories and set permissions
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Check if bootstrap/app.php exists
if [ -f /var/www/html/bootstrap/app.php ]; then
  # Make a temporary modification to bootstrap/app.php to bypass Pail loading
  sed -i 's/Laravel\\Pail\\PailServiceProvider/\/\/ Laravel\\Pail\\PailServiceProvider/' /var/www/html/bootstrap/app.php
  echo "Modified bootstrap/app.php to bypass Pail temporarily"
fi

# Generate application key if not set
php artisan key:generate --no-interaction --force

# Wait for database to be ready
echo "Waiting for PostgreSQL..."
until PGPASSWORD=$DB_PASSWORD psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" -c '\q' 2>/dev/null; do
  echo "PostgreSQL is unavailable - sleeping"
  sleep 2
done
echo "PostgreSQL is up - executing command"

# Run migrations
php artisan migrate --force

# Reinstall Laravel Pail properly
composer remove laravel/pail
composer clearcache
composer require laravel/pail

# Start the application
php artisan serve --host=0.0.0.0