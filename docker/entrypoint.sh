#!/bin/sh

set -eu

read_secret() {
    secret_path="$1"
    if [ ! -r "$secret_path" ]; then
        printf 'Erforderliches Secret ist nicht lesbar: %s\n' "$secret_path" >&2
        exit 1
    fi

    tr -d '\r\n' < "$secret_path"
}

db_name="$(read_secret /run/secrets/db_name)"
db_user="$(read_secret /run/secrets/db_user)"
db_password="$(read_secret /run/secrets/db_password)"

export APP_SECRET="$(read_secret /run/secrets/app_secret)"
export DATABASE_URL="mysql://${db_user}:${db_password}@database:3306/${db_name}?serverVersion=11.4.0-MariaDB&charset=utf8mb4"

if [ -r /run/secrets/membership_integration_token ]; then
    export MEMBERSHIP_INTEGRATION_TOKEN="$(read_secret /run/secrets/membership_integration_token)"
fi

mkdir -p var/cache var/log public/uploads/media

if [ "$(id -u)" = '0' ]; then
    chown -R www-data:www-data var public/uploads/media
fi

php bin/console cache:clear --no-warmup --no-interaction
php bin/console cache:warmup --no-interaction

if [ "${RUN_MIGRATIONS:-0}" = '1' ]; then
    attempt=0
    until php -r '$connection = @fsockopen("database", 3306, $errorCode, $errorMessage, 2); if ($connection === false) { exit(1); } fclose($connection);'; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge 30 ]; then
            printf 'Die Datenbank ist nach 60 Sekunden nicht erreichbar.\n' >&2
            exit 1
        fi
        sleep 2
    done

    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

if [ "${DROP_TO_WWW_DATA:-0}" = '1' ] && [ "$(id -u)" = '0' ]; then
    exec gosu www-data "$@"
fi

exec "$@"
