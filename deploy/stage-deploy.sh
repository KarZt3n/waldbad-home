#!/bin/sh

set -eu

project_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
deploy_dir='/srv/webapps/waldbad-home'
releases_dir="$deploy_dir/releases"
current_link="$deploy_dir/current"
secret_dir="$deploy_dir/secrets"
shared_dir="$deploy_dir/shared"
shared_media_dir="$shared_dir/media"

if ! git -C "$project_root" rev-parse --verify HEAD >/dev/null 2>&1; then
    printf 'Das Stage-Deployment muss aus einem Git-Checkout gestartet werden.\n' >&2
    exit 1
fi

if ! git -C "$project_root" diff-index --quiet HEAD --; then
    printf 'Das Stage-Deployment enthält nicht eingecheckte Änderungen.\n' >&2
    printf 'Committen oder verwerfen Sie diese Änderungen vor dem Deployment.\n' >&2
    exit 1
fi

commit="$(git -C "$project_root" rev-parse --short=12 HEAD)"
release_prefix="$(date -u '+%Y%m%d%H%M%S')-$commit"

install -d -m 0700 "$secret_dir"
install -d -m 0755 "$shared_dir"
install -d -m 0755 "$releases_dir"
if [ ! -d "$shared_media_dir" ]; then
    install -d -m 0775 "$shared_media_dir"
fi

if [ -e "$current_link" ] && [ ! -L "$current_link" ]; then
    printf '%s existiert, ist aber kein Symlink. Das Deployment wurde abgebrochen.\n' "$current_link" >&2
    exit 1
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

release_dir="$(mktemp -d "$releases_dir/$release_prefix.XXXXXX")"
release_name="$(basename -- "$release_dir")"
git -C "$project_root" archive --format=tar HEAD | tar -xf - -C "$release_dir"
touch "$release_dir/.waldbad-release"
compose_file="$release_dir/deploy/compose.stage.yaml"

printf 'Deploye Release %s aus Commit %s.\n' "$release_name" "$commit"
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

next_current="$deploy_dir/.current-$release_name"
ln -s "releases/$release_name" "$next_current"
mv -Tf "$next_current" "$current_link"

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
            "$releases_dir"/*)
                ;;
            *)
                printf 'Unsicherer Release-Pfad wurde nicht entfernt: %s\n' "$old_release_dir" >&2
                exit 1
                ;;
        esac

        if [ "$old_release_dir" != "$release_dir" ] && [ -f "$old_release_dir/.waldbad-release" ]; then
            rm -rf -- "$old_release_dir"
        fi
    done

printf 'Release %s ist jetzt unter %s aktiv.\n' "$release_name" "$current_link"
