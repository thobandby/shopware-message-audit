#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:18000}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-shopware}"

bash ./scripts/sync-plugin.sh
bash ./scripts/install-plugin.sh
bash ./scripts/seed-mh-demo.sh

php tests/E2E/plugin-http-e2e.php \
    --base-url="$BASE_URL" \
    --username="$ADMIN_USER" \
    --password="$ADMIN_PASSWORD"
