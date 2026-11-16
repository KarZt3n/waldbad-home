#!/bin/bash
# Nächtliche Komplettsicherung auf dem Telekom-Webspace. Läuft per GitHub Actions
# (`.github/workflows/backup-prod.yaml`), da der Webspace kein crontab hat, und zwar aus dem aktiven
# Release (`~/waldbad-home/current/deploy/prod-backup-remote.sh`), damit immer die Version des deployten
# Tags läuft. Von Hand: ssh prod 'bash ~/waldbad-home/current/deploy/prod-backup-remote.sh'
#
# Je Tag ein Ordner ~/waldbad-home/backups/nightly/JJJJ-MM-TT mit
#   database.sql.gz  – komplette Datenbank (alle Tabellen)
#   mitglieder/      – Mitglieder-Export (mitglieder-json.zip, mitglieder-csv.zip); jede Datei lässt sich
#                      im Admin über „Mitglieder importieren“ 1:1 wiederherstellen
#   uploads/         – alle Uploads (Mediathek, Fotos); unveränderte Dateien sind Hardlinks auf den
#                      Vortag und belegen keinen zusätzlichen Platz
#   config/          – .env.local und ggf. der Schlüssel zum Entschlüsseln der Symfony-Secrets
#   SHA256SUMS       – Prüfsummen von Datenbank, Mitglieder-Export und Konfiguration
# Sicherungen älter als BACKUP_RETENTION_DAYS (Standard 90) werden gelöscht.

set -euo pipefail
umask 077

php="${PHP_BIN:-php8.4}"
retention_days="${BACKUP_RETENTION_DAYS:-90}"
deploy_dir="$HOME/waldbad-home"
current_dir="$deploy_dir/current"
shared_dir="$deploy_dir/shared"
uploads_dir="$HOME/public_html/uploads"
nightly_dir="$deploy_dir/backups/nightly"
lock_dir="$nightly_dir/.lock"

if ! [[ "$retention_days" =~ ^[1-9][0-9]*$ ]]; then
    printf 'Ungültige Aufbewahrungsdauer: %s\n' "$retention_days" >&2
    exit 1
fi
if [ ! -f "$current_dir/deploy/prod/mysql-options.php" ]; then
    printf 'Im aktiven Release fehlt deploy/prod/mysql-options.php – bitte zuerst ein aktuelles Release deployen.\n' >&2
    exit 1
fi

install -d -m 0700 "$deploy_dir/backups" "$nightly_dir"
# Verhindert, dass sich zwei Läufe (z. B. geplant und von Hand) gegenseitig die Sicherung zerschießen.
if ! mkdir "$lock_dir" 2>/dev/null; then
    printf 'Es läuft bereits eine Sicherung (%s). Bei einem abgebrochenen Lauf den Ordner löschen.\n' "$lock_dir" >&2
    exit 1
fi

today="$(date '+%Y-%m-%d')"
target_dir="$nightly_dir/$today"
work_dir="$nightly_dir/.in-progress-$today"
mysql_options="$(mktemp)"
cleanup() {
    rm -f "$mysql_options"
    rm -rf -- "$work_dir"
    rmdir "$lock_dir" 2>/dev/null || true
}
trap cleanup EXIT

# Letzte vollständige Sicherung (nicht die von heute, falls der Tag erneut gesichert wird) als
# Grundlage für die Hardlinks der Uploads.
previous_dir="$(find "$nightly_dir" -mindepth 1 -maxdepth 1 -type d -name '????-??-??' ! -name "$today" -print | sort | tail -n 1)"

rm -rf -- "$work_dir"
install -d -m 0700 "$work_dir" "$work_dir/config" "$work_dir/mitglieder"

printf 'Sichere Datenbank …\n'
database_name="$(cd "$current_dir" && "$php" deploy/prod/mysql-options.php "$mysql_options")"
mysqldump --defaults-extra-file="$mysql_options" --single-transaction --quick --no-tablespaces --routines \
    "$database_name" | gzip -9 > "$work_dir/database.sql.gz"
gzip -t "$work_dir/database.sql.gz"

printf 'Sichere Mitglieder-Export …\n'
(cd "$current_dir" && APP_ENV="${APP_ENV:-prod}" APP_DEBUG=0 "$php" bin/console app:membership:export-members "$work_dir/mitglieder" --no-interaction)

printf 'Sichere Konfiguration …\n'
cp -p "$shared_dir/.env.local" "$work_dir/config/.env.local"
if [ -f "$shared_dir/prod.decrypt.private.php" ]; then
    cp -p "$shared_dir/prod.decrypt.private.php" "$work_dir/config/prod.decrypt.private.php"
fi

printf 'Sichere Uploads …\n'
link_dest=()
if [ -n "$previous_dir" ] && [ -d "$previous_dir/uploads" ]; then
    link_dest=(--link-dest="$previous_dir/uploads")
fi
rsync -a --delete ${link_dest[@]+"${link_dest[@]}"} "$uploads_dir/" "$work_dir/uploads/"

(cd "$work_dir" && find database.sql.gz mitglieder config -type f -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS)

# Erst wenn alles vollständig ist, wird der Ordner unter seinem Datum sichtbar; eine erneute
# Sicherung desselben Tages ersetzt die vorherige.
rm -rf -- "$target_dir"
mv "$work_dir" "$target_dir"
chmod 0700 "$target_dir"
printf 'Sicherung abgeschlossen: %s (%s)\n' "$target_dir" "$(du -sh "$target_dir" | cut -f1)"

cutoff="$("$php" -r 'echo date("Y-m-d", strtotime("-".$argv[1]." days"));' "$retention_days")"
find "$nightly_dir" -mindepth 1 -maxdepth 1 -type d -name '????-??-??' -print | sort | while IFS= read -r old_dir; do
    if [[ "$(basename "$old_dir")" < "$cutoff" ]]; then
        rm -rf -- "$old_dir"
        printf 'Alte Sicherung entfernt: %s\n' "$old_dir"
    fi
done

printf 'Sicherungen: %s Tage, belegt insgesamt %s.\n' \
    "$(find "$nightly_dir" -mindepth 1 -maxdepth 1 -type d -name '????-??-??' | wc -l | tr -d ' ')" \
    "$(du -sh "$nightly_dir" | cut -f1)"
