#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${PROJECT_DIR:-shopware}"
cd "$PROJECT_DIR"
docker compose exec -T web bin/console plugin:refresh
docker compose exec -T web bin/console plugin:install --activate MessengerHistoryDashboard
docker compose exec -T web bin/console database:migrate MessengerHistoryDashboard --all
docker compose exec -T web bin/console cache:clear
docker compose exec -T web bin/console cache:clear --env=prod --no-warmup
