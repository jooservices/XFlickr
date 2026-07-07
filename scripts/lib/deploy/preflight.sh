#!/usr/bin/env bash
# Production deploy preflight checks.
set -u
set -o pipefail

deploy_preflight() {
    local root="$1"
    local missing=0

    for cmd in docker git; do
        if ! command -v "$cmd" >/dev/null 2>&1; then
            echo "ERROR: Required command not found: ${cmd}" >&2
            missing=1
        fi
    done

    if ! docker compose version >/dev/null 2>&1; then
        echo "ERROR: docker compose v2 is required." >&2
        missing=1
    fi

    if [[ ! -f "${root}/docker-compose.prod.yml" ]]; then
        echo "ERROR: docker-compose.prod.yml not found in ${root}" >&2
        missing=1
    fi

    if [[ $missing -ne 0 ]]; then
        return 1
    fi

    chmod +x "${root}/docker/entrypoint-prod.sh" \
        "${root}/docker/nginx/entrypoint.sh" 2>/dev/null || true

    return 0
}
