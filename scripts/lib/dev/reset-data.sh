#!/usr/bin/env bash
# Dev stack destructive reset (wipe all volumes).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-dev.sh"

dev_reset_data() {
    echo "WARNING: Stopping dev stack and removing ALL xflickr-dev volumes (mysql, mongo, redis, node_modules)."
    xf_dev_compose down -v
    echo "Dev data volumes removed."
}
