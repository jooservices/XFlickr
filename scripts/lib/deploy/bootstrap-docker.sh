#!/usr/bin/env bash
# Production Docker stack bootstrap.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prepare-docker.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/finalize.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/app-init.sh"

deploy_bootstrap_docker_stack() {
    deploy_prepare_production_app || return 1
    deploy_finalize_release docker install
}

deploy_finish_install_docker() {
    local root="$1"

    if [[ ! -f "${root}/.env" ]]; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    echo "==> Finishing production install (using existing .env — wizard skipped)"
    xf_load_root_env "$root"
    deploy_bootstrap_docker_stack
}

xf_wait_for_app_prod() {
    xf_prod_wait_for_app
}

deploy_scale_horizon_docker() {
    local count="$1"
    local root
    root="$(xf_script_root)"

    deploy_set_horizon_replicas "$root" "$count" || return 1
    export HORIZON_REPLICAS="$count"
    deploy_finalize_workers_only docker "$count"
}

deploy_restart_nginx_docker() {
    xf_prod_compose up -d --force-recreate nginx
}
