#!/bin/bash
# Läuft auf dem Telekom-Webspace (per `ssh prod 'bash -s' -- <release> < deploy/prod-deploy-remote.sh`).
# Erwartet das von GitHub Actions gebaute Paket unter ~/waldbad-home/incoming/<release>.tar.gz.

set -euo pipefail

release_name="${1:-}"
if ! [[ "$release_name" =~ ^[A-Za-z0-9._-]+$ ]]; then
    printf 'Ungültiger Release-Name: %s\n' "$release_name" >&2
    exit 1
fi

php='php8.4'
deploy_dir="$HOME/waldbad-home"
releases_dir="$deploy_dir/releases"
shared_dir="$deploy_dir/shared"
backup_dir="$deploy_dir/backups/database"
current_link="$deploy_dir/current"
archive="$deploy_dir/incoming/$release_name.tar.gz"
release_dir="$releases_dir/$release_name"
# Der Document-Root des Webspace ist fest auf public_html eingestellt und lässt sich nicht auf
# public/ eines Releases umbiegen. Er enthält deshalb eine Kopie der öffentlichen Dateien und einen
# index.php-Stub, der auf das aktive Release zeigt; die Medien liegen dauerhaft direkt darin.
docroot="$HOME/public_html"
media_dir="$docroot/uploads/media"

export APP_ENV=prod APP_DEBUG=0

if [ ! -s "$shared_dir/.env.local" ]; then
    printf 'Es fehlt die Prod-Konfiguration %s (siehe deployment.md).\n' "$shared_dir/.env.local" >&2
    exit 1
fi
if grep -q 'BITTE_EINTRAGEN' "$shared_dir/.env.local"; then
    printf 'In %s sind noch Platzhalter (BITTE_EINTRAGEN) zu ersetzen.\n' "$shared_dir/.env.local" >&2
    exit 1
fi
if [ ! -f "$archive" ]; then
    printf 'Release-Paket %s wurde nicht gefunden.\n' "$archive" >&2
    exit 1
fi
if [ -e "$release_dir" ]; then
    printf 'Release %s existiert bereits.\n' "$release_dir" >&2
    exit 1
fi

install -d -m 0755 "$releases_dir" "$media_dir"
install -d -m 0700 "$shared_dir" "$backup_dir"
chmod 0600 "$shared_dir/.env.local"

mkdir "$release_dir"
tar -xzf "$archive" -C "$release_dir"
rm -f "$archive"
touch "$release_dir/.waldbad-release"

ln -s "$shared_dir/.env.local" "$release_dir/.env.local"
rm -rf "$release_dir/public/uploads/media"
ln -s "$media_dir" "$release_dir/public/uploads/media"
mkdir -p "$release_dir/var"

cd "$release_dir"
printf 'Bereite Release %s vor.\n' "$release_name"
"$php" bin/console cache:clear --no-warmup --no-interaction
"$php" bin/console cache:warmup --no-interaction

# Sicherung vor den Migrationen. Die Zugangsdaten gehen über eine temporäre Optionsdatei an
# mysqldump, damit das Passwort nicht in der Prozessliste steht.
mysql_options="$(mktemp)"
trap 'rm -f "$mysql_options"' EXIT
chmod 0600 "$mysql_options"
database_name="$("$php" -r '
    require "vendor/autoload.php";
    (new Symfony\Component\Dotenv\Dotenv())->bootEnv(".env");
    $url = parse_url((string) ($_SERVER["DATABASE_URL"] ?? ""));
    if ($url === false || !isset($url["host"], $url["user"], $url["path"])) {
        fwrite(STDERR, "DATABASE_URL ist ungültig.\n");
        exit(1);
    }
    $quote = static fn (string $value): string => "\"".addcslashes($value, "\\\"")."\"";
    file_put_contents($argv[1], sprintf(
        "[client]\nhost=%s\nport=%d\nuser=%s\npassword=%s\n",
        $quote($url["host"]),
        $url["port"] ?? 3306,
        $quote(rawurldecode($url["user"])),
        $quote(rawurldecode($url["pass"] ?? "")),
    ));
    echo rawurldecode(ltrim($url["path"], "/"));
' "$mysql_options")"
backup_file="$backup_dir/$release_name.sql.gz"
mysqldump --defaults-extra-file="$mysql_options" --single-transaction --no-tablespaces --routines "$database_name" | gzip > "$backup_file"
chmod 0600 "$backup_file"
rm -f "$mysql_options"
printf 'Datenbank gesichert: %s\n' "$backup_file"

"$php" bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
"$php" bin/console app:media:synchronize-metadata --no-interaction

# Neue Assets haben gehashte Dateinamen und können vor dem Umschalten bereitliegen.
rsync -a --chmod=D755,F644 --exclude=/index.php --exclude=/uploads/ "$release_dir/public/" "$docroot/"
install -m 0644 "$release_dir/deploy/prod/public.htaccess" "$docroot/.htaccess"
install -m 0644 "$release_dir/deploy/prod/uploads.htaccess" "$docroot/uploads/.htaccess"

# Umschalten: Der Stub wird erst vollständig geschrieben und dann atomar ersetzt.
cat > "$docroot/.index.php.next" <<PHP
<?php

use App\Kernel;

require_once '$release_dir/vendor/autoload_runtime.php';

return static function (array \$context) {
    return new Kernel(\$context['APP_ENV'], (bool) \$context['APP_DEBUG']);
};
PHP
chmod 0644 "$docroot/.index.php.next"
mv -f "$docroot/.index.php.next" "$docroot/index.php"

ln -s "releases/$release_name" "$deploy_dir/.current-$release_name"
mv -Tf "$deploy_dir/.current-$release_name" "$current_link"

# Erst jetzt veraltete öffentliche Dateien entfernen; Medien, Stub und .htaccess bleiben.
rsync -a --chmod=D755,F644 --delete --exclude=/index.php --exclude=/.htaccess --exclude=/uploads/ --exclude=/.well-known/ \
    "$release_dir/public/" "$docroot/"

for release_marker in "$releases_dir"/*/.waldbad-release; do
    if [ -f "$release_marker" ]; then
        dirname "$release_marker"
    fi
done \
    | LC_ALL=C sort -r \
    | awk -v active_release="$release_dir" '$0 != active_release' \
    | sed -n '3,$p' \
    | while IFS= read -r old_release_dir; do
        case "$old_release_dir" in
            "$releases_dir"/*) rm -rf -- "$old_release_dir" ;;
            *) printf 'Unsicherer Release-Pfad wurde nicht entfernt: %s\n' "$old_release_dir" >&2; exit 1 ;;
        esac
    done

ls -1t "$backup_dir"/*.sql.gz 2>/dev/null | sed -n '11,$p' | while IFS= read -r old_backup; do
    rm -f -- "$old_backup"
done

printf 'Release %s ist jetzt aktiv.\n' "$release_name"
