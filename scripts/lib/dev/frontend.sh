#!/usr/bin/env bash
# Dev stack frontend container helpers.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-dev.sh"

dev_frontend_restart() {
    echo "==> Recreating frontend container (no DB impact)"
    xf_dev_compose up -d --force-recreate frontend
    xf_dev_compose ps frontend
}

dev_frontend_refresh() {
    echo "==> npm ci in frontend container + restart Vite"
    xf_dev_compose exec -T frontend npm ci
    dev_frontend_restart
}
