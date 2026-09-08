#!/bin/sh

set -eu

usage() {
    printf 'Aufruf: %s [ziel.sql.gz]\n' "$0" >&2
    printf 'Muss auf dem Stage-Server im Deployment-Verzeichnis ausgeführt werden.\n' >&2
}

if [ "$#" -gt 1 ]; then
    usage
    exit 2
fi

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
compose_file="$project_root/deploy/compose.stage.yaml"
backup_directory='/srv/webapps/waldbad-home/backups/database'
timestamp="$(date -u '+%Y%m%d-%H%M%S')"
default_target="$backup_directory/waldbad-home-stage-$timestamp.sql.gz"
target="${1:-$default_target}"

case "$target" in
    /*) ;;
    *) target="$project_root/$target" ;;
esac

target_directory="$(dirname -- "$target")"
temporary_sql="$target_directory/.export-$timestamp.sql"
temporary_target="$target.part"

docker compose -p waldbad-home -f "$compose_file" ps database --status running --quiet | grep -q . || {
    printf 'Die Stage-Datenbank läuft nicht.\n' >&2
    exit 1
}

install -d -m 0700 "$target_directory"
rm -f -- "$temporary_sql" "$temporary_target"

cleanup() {
    rm -f -- "$temporary_sql" "$temporary_target"
}
trap cleanup EXIT HUP INT TERM

printf 'Exportiere die Stage-Datenbank nach %s ...\n' "$target"
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
' > "$temporary_sql"

chmod 0600 "$temporary_sql"
gzip -9c "$temporary_sql" > "$temporary_target"
gzip -t "$temporary_target"
chmod 0600 "$temporary_target"
mv -- "$temporary_target" "$target"
rm -f -- "$temporary_sql"
trap - EXIT HUP INT TERM

if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$target" > "$target.sha256"
elif command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$target" > "$target.sha256"
fi

printf 'Stage-Backup erstellt: %s\n' "$target"
printf 'Zum lokalen Einspielen: scp den Server auf deinen Rechner, dann:\n'
printf '  ddev import-db --file=%s\n' "$(basename -- "$target")"
