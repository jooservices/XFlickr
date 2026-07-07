#!/usr/bin/env bash
# Production Docker stack update.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prepare-docker.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/finalize.sh"

deploy_update_docker_stack() {
    local root
    root="$(xf_script_root)"

    if [[ -d "${root}/.git" ]]; then
        echo "==> git pull"
        git -C "$root" pull --ff-only
    fi

    deploy_prepare_production_app || return 1
    deploy_finalize_release docker update
}
