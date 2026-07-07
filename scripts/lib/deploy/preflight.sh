#!/usr/bin/env bash
# Production deploy preflight dispatcher (Docker or host).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/preflight-docker.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/host/preflight-host.sh"

deploy_preflight() {
    local root="$1"
    local mode="${2:-}"

    if [[ -z "$mode" ]]; then
        mode="$(deploy_resolve_mode "$root")"
    fi

    case "$mode" in
        host)
            deploy_preflight_host "$root"
            ;;
        docker|*)
            deploy_preflight_docker "$root"
            ;;
    esac
}
