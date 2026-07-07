#!/usr/bin/env bash
# Dev stack docker compose invocation (project: xflickr-dev).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

XF_DEV_COMPOSE_FILE="${XF_DEV_COMPOSE_FILE:-docker-compose.dev.yml}"
XF_DEV_PROJECT="${XF_DEV_PROJECT:-xflickr-dev}"

xf_dev_compose() {
    docker compose -f "${XF_DEV_COMPOSE_FILE}" -p "${XF_DEV_PROJECT}" "$@"
}

# Backward-compatible alias for dev operator scripts.
xf_compose() {
    xf_dev_compose "$@"
}
