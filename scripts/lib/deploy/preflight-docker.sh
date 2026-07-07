#!/usr/bin/env bash
# Docker production preflight checks.
set -u
set -o pipefail

deploy_preflight_docker() {
    local root="$1"
    local missing=0

    for required_cmd in docker git curl; do
        if ! command -v "$required_cmd" >/dev/null 2>&1; then
            echo "ERROR: Required command not found: ${required_cmd}" >&2
            missing=1
        fi
    done

    if ! docker compose version >/dev/null 2>&1; then
        echo "ERROR: docker compose v2 is required." >&2
        missing=1
    fi

    if ! docker info >/dev/null 2>&1; then
        echo "ERROR: Cannot access the Docker daemon." >&2
        echo "  Add your user to the docker group: sudo usermod -aG docker \$USER" >&2
        echo "  Then log out and back in (or run: newgrp docker)." >&2
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
