#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${PROJECT_DIR:-shopware}"
PLUGIN_NAME="${PLUGIN_NAME:-bit_status_audit}"
cd "$PROJECT_DIR"
docker compose exec -T web bin/console plugin:refresh
docker compose exec -T web bin/console plugin:install --activate "$PLUGIN_NAME"
docker compose exec -T web bin/console database:migrate "$PLUGIN_NAME" --all
docker compose exec -T web bin/console cache:clear
docker compose exec -T web bin/console cache:clear --env=prod --no-warmup
