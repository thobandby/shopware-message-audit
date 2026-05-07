#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${PROJECT_DIR:-shopware}"
cd "$PROJECT_DIR"
docker compose exec -T web bin/console mh:demo:seed
docker compose exec -T web bin/console messenger:consume async --time-limit=5 --no-debug || true
