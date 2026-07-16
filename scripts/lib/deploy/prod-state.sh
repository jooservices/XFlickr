#!/usr/bin/env bash
# Production deployment state helpers (Docker + host).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_prod_has_env() {
    local root="$1"
    [[ -f "${root}/.env" ]]
}

deploy_prod_container_count() {
    xf_prod_compose ps -a -q 2>/dev/null | wc -l | tr -d ' '
}

deploy_prod_running_count() {
    xf_prod_compose ps --status running -q 2>/dev/null | wc -l | tr -d ' '
}

deploy_set_horizon_replicas() {
    local root="$1"
    local count="$2"
    local env_file="${root}/.env"

    if [[ ! -f "$env_file" ]]; then
        echo "ERROR: .env not found." >&2
        return 1
    fi

    if [[ ! "$count" =~ ^[0-9]+$ ]] || [[ "$count" -lt 1 ]]; then
        echo "ERROR: Replica count must be a positive integer." >&2
        return 1
    fi

    if grep -q '^HORIZON_REPLICAS=' "$env_file"; then
        sed -i.bak "s|^HORIZON_REPLICAS=.*|HORIZON_REPLICAS=${count}|" "$env_file" || return 1
        rm -f "${env_file}.bak" || return 1
    else
        echo "HORIZON_REPLICAS=${count}" >>"$env_file" || return 1
    fi
}
