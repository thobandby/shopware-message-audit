#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="${PLUGIN_DIR:-plugin/MessengerHistoryDashboard}"
DIST_DIR="${DIST_DIR:-dist}"
PLUGIN_NAME="MessengerHistoryDashboard"
BUILD_ADMIN="${BUILD_ADMIN:-0}"

if [[ ! -d "$PLUGIN_DIR" ]]; then
    echo "Plugin directory not found: $PLUGIN_DIR" >&2
    exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
    echo "zip command is required to build the release archive." >&2
    exit 1
fi

VERSION="$(sed -n 's/  "version": "\(.*\)",/\1/p' "$PLUGIN_DIR/composer.json" | head -n 1)"

if [[ -z "$VERSION" ]]; then
    echo "Could not determine plugin version from $PLUGIN_DIR/composer.json" >&2
    exit 1
fi

if [[ "$BUILD_ADMIN" == "1" ]]; then
    bash ./scripts/build-administration-local.sh
fi

if ! find "$PLUGIN_DIR/src/Resources/public/administration/assets" -maxdepth 1 -type f -name '*.js' | grep -q .; then
    echo "No built administration assets found under $PLUGIN_DIR/src/Resources/public/administration/assets" >&2
    exit 1
fi

mkdir -p "$DIST_DIR"

ARCHIVE_PATH="$DIST_DIR/${PLUGIN_NAME}-${VERSION}.zip"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

mkdir -p "$TMP_DIR/$PLUGIN_NAME"
cp -R "$PLUGIN_DIR"/. "$TMP_DIR/$PLUGIN_NAME/"
rm -rf "$TMP_DIR/$PLUGIN_NAME/.git" "$TMP_DIR/$PLUGIN_NAME/node_modules"

rm -f "$ARCHIVE_PATH"

(
    cd "$TMP_DIR"
    zip -qr "$OLDPWD/$ARCHIVE_PATH" "$PLUGIN_NAME"
)

echo "Created $ARCHIVE_PATH"
