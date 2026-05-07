#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${PROJECT_DIR:-shopware}"
cd "$PROJECT_DIR"
docker compose exec -T web bash -lc 'APP_ENV=prod bin/console framework:demodata'
docker compose exec -T web bash -lc 'APP_ENV=prod bin/console dal:refresh:index'
