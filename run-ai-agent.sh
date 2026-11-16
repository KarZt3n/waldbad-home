#!/usr/bin/env bash
set -Eeuo pipefail

# ==============================================================================
# 🤖 AI-Agent Container Sandbox Launcher (dataservice)
# ==============================================================================
# Ablauf beim Container-Start:
# 1. Mountet das aktuelle Arbeitsverzeichnis (dataservice) nach /workspace
# 2. Maskiert sensible Dateien (.env, keys, secrets, optivo.yml) via tmpfs & /dev/null
# 3. Der Container-Entrypoint führt automatisch den Re-Sync & Safe-Merge
#    der zentralen Sicherheits-Baseline mit den dataservice-Projektregeln aus.
# ==============================================================================

IMAGE="${IMAGE:-ai-agents}"
WORKSPACE_DIR="${WORKSPACE_DIR:-$PWD}"
HOME_VOLUME="${HOME_VOLUME:-ai-agents}"
OLLAMA_API_BASE="${OLLAMA_API_BASE:-http://host.docker.internal:11434}"

# --- Schritt 1: Basis-Mounts (Projekt-Code & Persistentes Home) ---
mounts=(
  --mount "type=bind,source=$WORKSPACE_DIR,target=/workspace"
  --mount "type=volume,source=$HOME_VOLUME,target=/home/agent"
)

# --- Schritt 2: Sensible Verzeichnisse & Skripte maskieren (tmpfs) ---
for secret_dir in "config/secrets" "config-docker" "secrets" "scripts/temporary" ".ssh"; do
  if [ -d "$WORKSPACE_DIR/$secret_dir" ]; then
    mounts+=(
      --mount "type=tmpfs,target=/workspace/$secret_dir"
    )
  fi
done

# --- Schritt 3: Bekannte projektspezifische Einzeldateien maskieren ---
for secret_file in \
  "config/packages/optivo.yml" "config/packages/docker/optivo.yml" "config/packages/dev/optivo.yml" "config/packages/int/optivo.yml" "config/packages/stage/optivo.yml" "config/packages/prod/optivo.yml"; do
  if [ -f "$WORKSPACE_DIR/$secret_file" ]; then
    mounts+=(
      --mount "type=bind,source=/dev/null,target=/workspace/$secret_file,ro"
    )
  fi
done

# --- Schritt 4: Sensible Dateien & Schlüssel (.env*, *.key, *.pem, *.kdbx etc.) maskieren ---
while IFS= read -r -d '' file; do
  rel_path="${file#$WORKSPACE_DIR/}"
  mounts+=(
    --mount "type=bind,source=/dev/null,target=/workspace/$rel_path,ro"
  )
done < <(find "$WORKSPACE_DIR" -type f \( \
  -path '*/scripts/temporary/*' -o \
  -name '.env*' -o \
  -name '*.pem' -o \
  -name '*.key' -o \
  -name '*.p12' -o \
  -name '*.kdbx' -o \
  -name '*.pfx' \
\) -print0)

# --- Schritt 5: Container-Start mit Entrypoint-Enforcement ---
exec docker run --rm -it \
  --network bridge \
  --add-host=host.docker.internal:host-gateway \
  --env "OLLAMA_API_BASE=$OLLAMA_API_BASE" \
  "${mounts[@]}" \
  "$IMAGE"
