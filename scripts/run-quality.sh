#!/usr/bin/env bash
set -euo pipefail

composer cs-fix
composer phpstan
composer phpinsights
composer test
