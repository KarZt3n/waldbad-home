#!/bin/bash
# Stellt eine nächtliche Sicherung (siehe deploy/prod-backup-remote.sh) auf dem Telekom-Webspace wieder her.
# Wird aus dem aktiven Release aufgerufen, damit Skript und Code-Stand zusammenpassen:
#
#   ssh prod 'RESTORE_CONFIRM=ja bash ~/waldbad-home/current/deploy/prod-restore-remote.sh 2026-11-16 database'
#   ssh prod 'RESTORE_CONFIRM=ja bash ~/waldbad-home/current/deploy/prod-restore-remote.sh 2026-11-16 uploads'
#   ssh prod 'RESTORE_CONFIRM=ja bash ~/waldbad-home/current/deploy/prod-restore-remote.sh 2026-11-16 database uploads'
#
# Vor dem Einspielen der Datenbank wird der aktuelle Stand unter backups/database/vor-restore-*.sql.gz
# gesichert. Die Konfiguration (config/) wird bewusst nicht automatisch zurückgespielt.

set -euo pipefail
umask 077

php="${PHP_BIN:-php8.4}"
deploy_dir="$HOME/waldbad-home"
current_dir="$deploy_dir/current"
uploads_dir="$HOME/public_html/uploads"
snapshot="${1:-}"
shift || true

if ! [[ "$snapshot" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] || [ "$#" -eq 0 ]; then
    printf 'Aufruf: … -- JJJJ-MM-TT database|uploads [database|uploads]\n' >&2
    exit 1
fi
source_dir="$deploy_dir/backups/nightly/$snapshot"
if [ ! -d "$source_dir" ]; then
    printf 'Keine Sicherung vom %s gefunden.\n' "$snapshot" >&2
    exit 1
fi
if [ "${RESTORE_CONFIRM:-}" != 'ja' ]; then
    printf 'Zur Bestätigung RESTORE_CONFIRM=ja setzen – die aktuellen Daten werden überschrieben.\n' >&2
    exit 1
fi

restore_database=false
restore_uploads=false
for part in "$@"; do
    case "$part" in
        database) restore_database=true ;;
        uploads) restore_uploads=true ;;
        *) printf 'Unbekannter Teil: %s\n' "$part" >&2; exit 1 ;;
    esac
done

(cd "$source_dir" && sha256sum --check --quiet SHA256SUMS)

if [ "$restore_database" = true ]; then
    mysql_options="$(mktemp)"
    trap 'rm -f "$mysql_options"' EXIT
    database_name="$(cd "$current_dir" && "$php" deploy/prod/mysql-options.php "$mysql_options")"
    install -d -m 0700 "$deploy_dir/backups/database"
    safety_file="$deploy_dir/backups/database/vor-restore-$(date '+%Y%m%d%H%M%S').sql.gz"
    mysqldump --defaults-extra-file="$mysql_options" --single-transaction --no-tablespaces --routines "$database_name" | gzip > "$safety_file"
    printf 'Aktueller Stand gesichert: %s\n' "$safety_file"
    gunzip -c "$source_dir/database.sql.gz" | mysql --defaults-extra-file="$mysql_options" "$database_name"
    printf 'Datenbank vom %s wiederhergestellt.\n' "$snapshot"
    (cd "$current_dir" && APP_ENV=prod APP_DEBUG=0 "$php" bin/console cache:clear --no-interaction)
fi

if [ "$restore_uploads" = true ]; then
    rsync -a --delete --exclude=/.htaccess "$source_dir/uploads/" "$uploads_dir/"
    printf 'Uploads vom %s wiederhergestellt.\n' "$snapshot"
fi
