#!/usr/bin/env bash
# Host production stack update.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prepare-host.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/config.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/finalize.sh"

deploy_update_host_stack() {
    local root
    root="$(xf_script_root)"

    if [[ -d "${root}/.git" ]]; then
        echo "==> git pull"
        git -C "$root" pull --ff-only || return 1
    fi

    deploy_prepare_host_app || return 1
    deploy_host_install_configs "$root" || return 1
    deploy_finalize_release host update
}
