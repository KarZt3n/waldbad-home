#!/bin/sh

set -eu

usage() {
    printf 'Aufruf: %s <backup.sql|backup.sql.gz> --confirm-stage\n' "$0" >&2
}

if [ "$#" -ne 2 ] || [ "$2" != '--confirm-stage' ]; then
    usage
    exit 2
fi

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
compose_file="$project_root/deploy/compose.stage.yaml"
backup_file="$1"
stage_backup_directory='/srv/webapps/waldbad-home/backups/database'
timestamp="$(date '+%Y%m%d-%H%M%S')"
stage_backup="$stage_backup_directory/before-import-$timestamp.sql.gz"
stage_backup_sql="$stage_backup_directory/.before-import-$timestamp.sql"
import_sql="$stage_backup_directory/.incoming-$timestamp.sql"

case "$backup_file" in
    /*) ;;
    *) backup_file="$(CDPATH='' cd -- "$(dirname -- "$backup_file")" && pwd)/$(basename -- "$backup_file")" ;;
esac

if [ ! -r "$backup_file" ]; then
    printf 'Das Backup ist nicht lesbar: %s\n' "$backup_file" >&2
    exit 1
fi

case "$backup_file" in
    *.sql.gz) gzip -t "$backup_file" ;;
    *.sql) ;;
    *)
        printf 'Unterstützt werden .sql und .sql.gz.\n' >&2
        exit 1
        ;;
esac

docker compose -p waldbad-home -f "$compose_file" ps database --status running --quiet | grep -q . || {
    printf 'Die Stage-Datenbank läuft nicht.\n' >&2
    exit 1
}

install -d -m 0700 "$stage_backup_directory"

printf 'Sichere die bestehende Stage-Datenbank nach %s ...\n' "$stage_backup"
docker compose -p waldbad-home -f "$compose_file" exec -T database sh -eu -c '
    exec mariadb-dump \
        --user=root \
        --password="$(cat /run/secrets/db_root_password)" \
        --single-transaction \
        --routines \
        --events \
        --triggers \
        --hex-blob \
        "$(cat /run/secrets/db_name)"
' > "$stage_backup_sql"
gzip -9c "$stage_backup_sql" > "$stage_backup"
gzip -t "$stage_backup"
chmod 0600 "$stage_backup"
rm -f -- "$stage_backup_sql"

if [ "${backup_file##*.}" = 'gz' ]; then
    gzip -dc "$backup_file" > "$import_sql"
else
    cp -- "$backup_file" "$import_sql"
fi
chmod 0600 "$import_sql"

printf 'Stoppe Web-App und Worker für den konsistenten Import ...\n'
docker compose -p waldbad-home -f "$compose_file" stop app worker

start_services() {
    docker compose -p waldbad-home -f "$compose_file" up -d app worker >/dev/null 2>&1 || true
}

reset_database() {
    docker compose -p waldbad-home -f "$compose_file" exec -T database sh -eu -c '
    database_name="$(cat /run/secrets/db_name)"
    case "$database_name" in
        *[!A-Za-z0-9_]*) printf "Ungültiger Datenbankname.\n" >&2; exit 1 ;;
    esac
    mariadb --user=root --password="$(cat /run/secrets/db_root_password)" \
        --execute="DROP DATABASE IF EXISTS \`$database_name\`; CREATE DATABASE \`$database_name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
'
}

import_database() {
    source_file="$1"
    docker compose -p waldbad-home -f "$compose_file" exec -T database sh -eu -c '
        exec mariadb \
            --user=root \
            --password="$(cat /run/secrets/db_root_password)" \
            "$(cat /run/secrets/db_name)"
    ' < "$source_file"
}

database_changed=0

handle_failure() {
    exit_code="$?"
    trap - EXIT HUP INT TERM
    rm -f -- "$import_sql" "$stage_backup_sql"

    if [ "$database_changed" -eq 1 ]; then
        printf 'Import fehlgeschlagen. Stelle die vorherige Stage-Datenbank wieder her ...\n' >&2
        docker compose -p waldbad-home -f "$compose_file" stop app worker >/dev/null 2>&1 || true
        if gzip -dc "$stage_backup" > "$stage_backup_sql" \
            && reset_database \
            && import_database "$stage_backup_sql"; then
            printf 'Die vorherige Stage-Datenbank wurde wiederhergestellt.\n' >&2
        else
            printf 'Automatischer Rollback fehlgeschlagen. Manuelles Backup: %s\n' "$stage_backup" >&2
        fi
    fi

    rm -f -- "$stage_backup_sql"
    start_services
    exit "$exit_code"
}

trap handle_failure EXIT
trap 'exit 130' HUP INT TERM

database_changed=1
reset_database

printf 'Importiere %s ...\n' "$backup_file"
import_database "$import_sql"
rm -f -- "$import_sql"

printf 'Starte Stage-App; ausstehende Migrationen werden beim Start ausgeführt ...\n'
docker compose -p waldbad-home -f "$compose_file" up -d app worker

attempt=0
until curl --fail --silent --show-error --max-time 5 http://127.0.0.1:8083/api/public/v1/navigation >/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 45 ]; then
        docker compose -p waldbad-home -f "$compose_file" ps
        docker compose -p waldbad-home -f "$compose_file" logs --tail=100 app database
        printf 'Die Stage-App wurde nach dem Import nicht gesund. Vorher-Backup: %s\n' "$stage_backup" >&2
        exit 1
    fi
    sleep 2
done

database_changed=0
trap - EXIT HUP INT TERM
printf 'Stage-Import abgeschlossen. Vorher-Backup: %s\n' "$stage_backup"
docker compose -p waldbad-home -f "$compose_file" ps
