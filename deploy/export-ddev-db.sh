#!/bin/sh

set -eu

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
timestamp="$(date '+%Y%m%d-%H%M%S')"
default_target="$project_root/var/backups/ddev/waldbad-home-ddev-$timestamp.sql.gz"
target="${1:-$default_target}"

case "$target" in
    /*) ;;
    *) target="$project_root/$target" ;;
esac

target_directory="$(dirname -- "$target")"
temporary_target="$target.part"

command -v ddev >/dev/null 2>&1 || {
    printf 'DDEV ist nicht installiert oder nicht im PATH.\n' >&2
    exit 1
}

install -d -m 0700 "$target_directory"
rm -f -- "$temporary_target"

cleanup() {
    rm -f -- "$temporary_target"
}
trap cleanup EXIT HUP INT TERM

printf 'Exportiere die DDEV-Datenbank nach %s ...\n' "$target"
(
    cd "$project_root"
    ddev export-db --database=db --file="$temporary_target"
)

gzip -t "$temporary_target"
chmod 0600 "$temporary_target"
mv -- "$temporary_target" "$target"
trap - EXIT HUP INT TERM

if command -v shasum >/dev/null 2>&1; then
    shasum -a 256 "$target" > "$target.sha256"
elif command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$target" > "$target.sha256"
fi

printf 'DDEV-Backup erstellt: %s\n' "$target"
