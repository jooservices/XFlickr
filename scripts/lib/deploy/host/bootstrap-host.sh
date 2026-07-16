#!/usr/bin/env bash
# Host production stack bootstrap.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prepare-host.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/config.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/app-init.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/finalize.sh"

deploy_bootstrap_host_stack() {
    local root
    root="$(xf_script_root)"

    deploy_prepare_host_app || return 1
    deploy_app_wait_for_services || return 1
    deploy_host_install_configs "$root" || return 1

    deploy_finalize_release host install
}

deploy_finish_install_host() {
    local root="$1"

    if [[ ! -f "${root}/.env" ]]; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    echo "==> Finishing host production install (using existing .env — wizard skipped)"
    xf_load_root_env "$root"
    deploy_bootstrap_host_stack
}

deploy_scale_horizon_host() {
    local count="$1"
    local root
    root="$(xf_script_root)"

    deploy_set_horizon_replicas "$root" "$count" || return 1
    export HORIZON_REPLICAS="$count"
    xf_load_root_env "$root"
    deploy_host_install_configs "$root" || return 1
    deploy_finalize_workers_only host "$count"
}

deploy_restart_nginx_host() {
    deploy_host_sudo nginx -t
    deploy_host_sudo systemctl reload nginx
}

deploy_host_down() {
    supervisorctl stop xflickr-app xflickr-horizon xflickr-scheduler 2>/dev/null || true
    if [[ -f /etc/nginx/sites-enabled/xflickr ]]; then
        deploy_host_sudo rm -f /etc/nginx/sites-enabled/xflickr
        deploy_host_sudo nginx -t && deploy_host_sudo systemctl reload nginx
    fi
    echo "Host production services stopped (external databases unchanged)."
}
