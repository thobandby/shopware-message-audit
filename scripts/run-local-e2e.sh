#!/usr/bin/env bash
set -euo pipefail

resolve_base_url() {
    local env_file="${PROJECT_DIR:-shopware}/.env.local"

    if [[ -n "${BASE_URL:-}" ]]; then
        printf '%s\n' "$BASE_URL"
        return
    fi

    if [[ -f "$env_file" ]]; then
        local configured_url
        configured_url="$(sed -n 's/^APP_URL=//p' "$env_file" | tail -n 1)"

        if [[ -n "$configured_url" ]]; then
            printf '%s\n' "$configured_url"
            return
        fi
    fi

    printf '%s\n' 'http://127.0.0.1:18000'
}

BASE_URL="$(resolve_base_url)"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-shopware}"

bash ./scripts/sync-plugin.sh
bash ./scripts/install-plugin.sh
bash ./scripts/seed-mh-demo.sh

php tests/E2E/plugin-http-e2e.php \
    --base-url="$BASE_URL" \
    --username="$ADMIN_USER" \
    --password="$ADMIN_PASSWORD"
