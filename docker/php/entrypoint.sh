#!/bin/sh
set -eu

cd /var/www/html

# Laravel artisan expects a physical .env file. Docker env_file supplies the
# runtime values, while the named runtime volume keeps the generated APP_KEY
# persistent when the application container is recreated.
mkdir -p /var/www/html/runtime
if [ ! -f /var/www/html/runtime/.env ]; then
    if [ -f .env.example ]; then
        cp .env.example /var/www/html/runtime/.env
    else
        : > /var/www/html/runtime/.env
    fi
fi
if [ -e /var/www/html/.env ] && [ ! -L /var/www/html/.env ]; then
    rm -f /var/www/html/.env
fi
ln -sf /var/www/html/runtime/.env /var/www/html/.env

# Populate the shared public volume on first startup without masking the image assets.
if [ -d /opt/smart-gate-public ]; then
    cp -a /opt/smart-gate-public/. /var/www/html/public/
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Keep the runtime .env synchronized with Docker-provided configuration.
set_env() {
    key="$1"
    value="$2"
    temp_file=".env.tmp"

    # Write every managed value as a quoted dotenv value. This prevents
    # values such as "Smart Gate" from becoming invalid dotenv syntax.
    grep -v "^${key}=" .env > "$temp_file" || true
    escaped=$(printf '%s' "$value" | sed 's/\\/\\\\/g; s/"/\\\"/g')
    printf '%s="%s"\n' "$key" "$escaped" >> "$temp_file"
    mv "$temp_file" .env
}

set_env APP_NAME "${APP_NAME:-Smart Gate}"
set_env APP_ENV "${APP_ENV:-production}"
set_env APP_DEBUG "${APP_DEBUG:-false}"
set_env APP_URL "${APP_URL:-https://gate.kunehobatumbakal.site/}"
set_env DB_CONNECTION "${DB_CONNECTION:-mysql}"
set_env DB_HOST "${DB_HOST:-mysql}"
set_env DB_PORT "${DB_PORT:-3306}"
set_env DB_DATABASE "${DB_DATABASE:-smart_gate}"
set_env DB_USERNAME "${DB_USERNAME:-smart_gate}"
set_env DB_PASSWORD "${DB_PASSWORD:-change-me}"

if [ -z "$(grep '^APP_KEY=' .env | cut -d= -f2-)" ]; then
    php artisan key:generate --force --no-interaction
fi

# Laravel's configuration reads process environment before .env. The Compose
# env_file intentionally does not contain APP_KEY, so the generated value can
# be loaded from the physical .env file for every subsequent Artisan command.
APP_KEY_VALUE=$(grep '^APP_KEY=' .env | cut -d= -f2-)
if [ -z "$APP_KEY_VALUE" ]; then
    echo 'ERROR: APP_KEY was not generated.' >&2
    exit 1
fi

# Fail early with a useful message if the Laravel application key is still unavailable.
# Do not use Laravel Tinker here: it is a development-only package and is not
# installed in this production image.
php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class); if (!getenv('APP_KEY') && !is_file('.env')) { fwrite(STDERR, 'ERROR: Laravel environment is unavailable.\\n'); exit(1); }"

php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan smart-gate:cleanup-guests --no-interaction || true
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
