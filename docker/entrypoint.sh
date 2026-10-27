#!/bin/sh
set -e

# Substitute PORT in Nginx configuration
PORT="${PORT:-8080}"
echo "Configuring Nginx for port ${PORT}..."
sed -i "s/PORT_PLACEHOLDER/${PORT}/g" /etc/nginx/http.d/default.conf

cd /var/www/html

# Ensure storage directories exist
mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache \
         storage/logs \
         bootstrap/cache \
         database

# Configure database
DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONNECTION" = "sqlite" ]; then
    DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    if [ ! -f "$DB_DATABASE" ]; then
        echo "Creating SQLite database file at $DB_DATABASE..."
        touch "$DB_DATABASE"
    fi
    chown -R www-data:www-data "$DB_DATABASE"
    chmod 664 "$DB_DATABASE"
elif [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection to $DB_HOST:${DB_PORT:-3306}..."
    for i in $(seq 1 30); do
        php -r '
            $host = getenv("DB_HOST");
            $port = getenv("DB_PORT") ?: 3306;
            $conn = @fsockopen($host, (int)$port, $errno, $errstr, 2);
            if ($conn) { fclose($conn); exit(0); }
            exit(1);
        ' && break || sleep 2
    done
fi

# Ensure APP_KEY exists
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force --isolated || php artisan migrate --force

# Seed database if users table is empty or RUN_SEEDER is true
RUN_SEEDER="${RUN_SEEDER:-true}"
if [ "$RUN_SEEDER" = "true" ]; then
    echo "Checking seed data..."
    php artisan db:seed --force || echo "Seeding skipped or already applied."
fi

# Cache configuration, routes, and views for production performance
echo "Caching Laravel configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache

echo "Application ready. Starting Supervisord on port ${PORT}..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
