#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-shopware}"
INSTALL_DEV_TOOLS="${INSTALL_DEV_TOOLS:-1}"

if [[ ! -d "$PROJECT_DIR" ]]; then
    echo "Shopware project directory not found: $PROJECT_DIR" >&2
    exit 1
fi

if [[ ! -f "$PROJECT_DIR/Makefile" ]]; then
    echo "Shopware Makefile not found in $PROJECT_DIR" >&2
    exit 1
fi

ensure_dev_tools() {
    if [[ "$INSTALL_DEV_TOOLS" != "1" ]]; then
        return
    fi

    if docker compose exec -T web composer show shopware/dev-tools >/dev/null 2>&1; then
        return
    fi

    echo "Installing shopware/dev-tools in local Shopware workspace..."
    docker compose exec -T web composer require --dev shopware/dev-tools
}

cd "$PROJECT_DIR"

make up
ensure_dev_tools

cd ..
bash ./scripts/sync-plugin.sh
bash ./scripts/install-plugin.sh

cd "$PROJECT_DIR"
make build-administration

echo "Administration build completed."
