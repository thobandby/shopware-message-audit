#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-shopware}"

if [[ ! -d "$PROJECT_DIR" ]]; then
    echo "Shopware project directory not found: $PROJECT_DIR" >&2
    exit 1
fi

cd "$PROJECT_DIR"

if ! docker compose exec -T web composer show shopware/dev-tools >/dev/null 2>&1; then
    echo "shopware/dev-tools is not installed."
    exit 0
fi

docker compose exec -T web composer remove --dev shopware/dev-tools
docker compose exec -T web bin/console cache:clear
docker compose exec -T web bin/console cache:clear --env=prod --no-warmup

echo "shopware/dev-tools removed from local Shopware workspace."
