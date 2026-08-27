#!/bin/sh

set -eu

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
secret_dir='/srv/webapps/waldbad-home/secrets'
shared_dir='/srv/webapps/waldbad-home/shared'
shared_media_dir="$shared_dir/media"
compose_file="$project_root/deploy/compose.stage.yaml"

install -d -m 0700 "$secret_dir"
install -d -m 0755 "$shared_dir"
if [ ! -d "$shared_media_dir" ]; then
    install -d -m 0775 "$shared_media_dir"
fi

legacy_media_volume='waldbad-home_media_data'
legacy_import_marker="$shared_media_dir/.legacy-volume-imported"
if [ ! -e "$legacy_import_marker" ] && docker volume inspect "$legacy_media_volume" >/dev/null 2>&1; then
    docker run --rm \
        -v "$legacy_media_volume:/legacy-media:ro" \
        -v "$shared_media_dir:/shared-media" \
        nginx:1.28-alpine \
        sh -c 'cp -a /legacy-media/. /shared-media/ && touch /shared-media/.legacy-volume-imported'
fi

ensure_literal_secret() {
    secret_name="$1"
    secret_value="$2"
    secret_path="$secret_dir/$secret_name"

    if [ ! -f "$secret_path" ]; then
        umask 077
        printf '%s' "$secret_value" > "$secret_path"
    fi
}

ensure_random_secret() {
    secret_name="$1"
    secret_path="$secret_dir/$secret_name"

    if [ ! -f "$secret_path" ]; then
        umask 077
        openssl rand -hex 32 > "$secret_path"
    fi
}

ensure_literal_secret db_name waldbad_home
ensure_literal_secret db_user waldbad
ensure_random_secret db_password
ensure_random_secret db_root_password
ensure_random_secret app_secret
ensure_literal_secret membership_integration_token ''

if [ ! -s "$secret_dir/stage_htpasswd" ]; then
    printf 'Es ist noch kein Stage-Zugangsbenutzer eingerichtet.\n' >&2
    printf 'Führen Sie zuerst ./deploy/stage-access-user.sh add <benutzername> aus.\n' >&2
    exit 1
fi

chmod 0600 "$secret_dir"/*

if ! docker network inspect web >/dev/null 2>&1; then
    docker network create web >/dev/null
fi

docker compose -f "$compose_file" build --pull app
docker compose -f "$compose_file" up -d --remove-orphans

attempt=0
until curl --fail --silent --show-error --max-time 5 http://127.0.0.1:8083/api/public/v1/navigation >/dev/null; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        docker compose -f "$compose_file" ps
        docker compose -f "$compose_file" logs --tail=100 app database
        printf 'Waldbad-home wurde nicht rechtzeitig gesund.\n' >&2
        exit 1
    fi
    sleep 2
done

if [ "${WALDBAD_INITIALIZE_SITE:-0}" = '1' ]; then
    docker compose -f "$compose_file" run --rm --no-deps -e RUN_MIGRATIONS=0 app php bin/console app:site:initialize --no-interaction
fi
docker compose -f "$compose_file" run --rm --no-deps -e RUN_MIGRATIONS=0 app php bin/console app:media:synchronize-metadata --no-interaction
docker compose -f "$compose_file" ps
