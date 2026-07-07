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
