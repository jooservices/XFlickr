#!/usr/bin/env bash
# Deploy target detection: docker or host.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/state.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prod-state.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/common.sh"

DEPLOY_TARGET_DOCKER="docker"
DEPLOY_TARGET_HOST="host"

deploy_mode_from_env_file() {
    local root="$1"
    local env_file="${root}/.env"

    if [[ -f "$env_file" ]]; then
        grep -E '^DEPLOY_TARGET=' "$env_file" 2>/dev/null | head -1 | cut -d= -f2- || true
    fi
}

deploy_mode_from_wizard_state() {
    local root="$1"
    local state_file

    state_file="$(deploy_wizard_state_path "$root")"
    if [[ -f "$state_file" ]]; then
        grep -E '^DEPLOY_TARGET=' "$state_file" 2>/dev/null | head -1 | cut -d= -f2- || true
    fi
}

deploy_can_use_docker_daemon() {
    command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1
}

deploy_prompt_target() {
    local default="${1:-docker}"
    local value

    echo
    echo "Deploy target:"
    echo "  1) Docker (recommended) — nginx + app + horizon + scheduler in containers"
    echo "  2) Host — PHP on Ubuntu 22.04 with nginx, supervisor, and external databases"
    echo

    if [[ "$default" == "$DEPLOY_TARGET_HOST" ]]; then
        read -r -p "Choose [1/2] [2]: " value
        value="${value:-2}"
    else
        read -r -p "Choose [1/2] [1]: " value
        value="${value:-1}"
    fi

    case "$value" in
        2|host|Host|HOST)
            echo "$DEPLOY_TARGET_HOST"
            ;;
        *)
            echo "$DEPLOY_TARGET_DOCKER"
            ;;
    esac
}

deploy_resolve_mode() {
    local root="$1"
    local prompt_if_missing="${2:-0}"
    local mode=""

    mode="$(deploy_mode_from_env_file "$root")"
    if [[ -n "$mode" ]]; then
        echo "$mode"
        return 0
    fi

    mode="$(deploy_mode_from_wizard_state "$root")"
    if [[ -n "$mode" ]]; then
        echo "$mode"
        return 0
    fi

    if [[ "${DEPLOY_TARGET:-}" != "" ]]; then
        echo "${DEPLOY_TARGET}"
        return 0
    fi

    if [[ "$prompt_if_missing" == "1" ]]; then
        local default="$DEPLOY_TARGET_DOCKER"
        if ! deploy_can_use_docker_daemon; then
            default="$DEPLOY_TARGET_HOST"
        fi
        deploy_prompt_target "$default"
        return 0
    fi

    if deploy_can_use_docker_daemon; then
        echo "$DEPLOY_TARGET_DOCKER"
    else
        echo "$DEPLOY_TARGET_HOST"
    fi
}

deploy_assert_mode_consistent() {
    local root="$1"
    local mode="$2"
    local env_mode containers

    env_mode="$(deploy_mode_from_env_file "$root")"
    if [[ -n "$env_mode" && "$env_mode" != "$mode" ]]; then
        echo "ERROR: DEPLOY_TARGET in .env is '${env_mode}' but requested '${mode}'." >&2
        echo "Remove .env or use the configured target." >&2
        return 1
    fi

    if [[ "$mode" == "$DEPLOY_TARGET_DOCKER" ]]; then
        return 0
    fi

    containers="$(docker compose -f docker-compose.prod.yml -p xflickr-prod ps -a -q 2>/dev/null | wc -l | tr -d ' ' || echo 0)"
    if [[ "${containers:-0}" -gt 0 ]]; then
        echo "ERROR: Docker production containers exist but DEPLOY_TARGET=host." >&2
        echo "Run: bash scripts/deploy.sh down   then reinstall with host target." >&2
        return 1
    fi

    return 0
}

deploy_wizard_default_service_host() {
    local mode="${1:-docker}"

    if [[ "$mode" == "$DEPLOY_TARGET_HOST" ]]; then
        echo "127.0.0.1"
    else
        echo "host.docker.internal"
    fi
}

deploy_host_services_running() {
    supervisorctl status xflickr-app xflickr-horizon xflickr-scheduler >/dev/null 2>&1
}

deploy_prod_is_deployed_for_mode() {
    local root="$1"
    local mode="$2"

    if deploy_prod_has_env "$root"; then
        return 0
    fi

    if [[ "$mode" == "$DEPLOY_TARGET_DOCKER" ]]; then
        [[ "$(deploy_prod_container_count)" -gt 0 ]]
        return $?
    fi

    deploy_host_services_running
}
