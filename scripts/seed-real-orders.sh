#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-shopware}"
DEMODATA_ARGS="${DEMODATA_ARGS:---reset-defaults --customers=10 --products=20 --orders=12 --categories=4 --manufacturers=3 --media=0 --promotions=0 --reviews=0 --flows=0 --users=0 --tags=0 --properties=0 --product-streams=0 --mail-template=0 --mail-header-footer=0 --sales-channel-domain=0 --attribute-sets=0 --rules=0}"

cd "$PROJECT_DIR"

docker compose exec -T web bash -lc "APP_ENV=prod bin/console framework:demodata $DEMODATA_ARGS"
docker compose exec -T web bin/console mh:seed:business --limit=12
docker compose exec -T web bin/console mh:worker:consume --time-limit=10 || true
