#!/usr/bin/env bash
set -euo pipefail
PROJECT_DIR="${PROJECT_DIR:-shopware}"
PLUGIN_SRC="$(pwd)/plugin/MessengerHistoryDashboard"
PLUGIN_DST="$(pwd)/$PROJECT_DIR/custom/plugins/MessengerHistoryDashboard"
mkdir -p "$(dirname "$PLUGIN_DST")"
rm -rf "$PLUGIN_DST"
cp -R "$PLUGIN_SRC" "$PLUGIN_DST"
echo "Plugin synchronisiert."
