#!/usr/bin/env bash
# Backward-compatible bootstrap router (Docker + host).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/bootstrap-docker.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/host/bootstrap-host.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"

deploy_bootstrap_stack() {
    local mode
    mode="$(deploy_resolve_mode "$(xf_script_root)")"

    case "$mode" in
        host) deploy_bootstrap_host_stack ;;
        *) deploy_bootstrap_docker_stack ;;
    esac
}

deploy_finish_install() {
    local root="$1"
    local mode
    mode="$(deploy_resolve_mode "$root")"

    case "$mode" in
        host) deploy_finish_install_host "$root" ;;
        *) deploy_finish_install_docker "$root" ;;
    esac
}

deploy_scale_horizon() {
    local count="$1"
    local mode
    mode="$(deploy_resolve_mode "$(xf_script_root)")"

    case "$mode" in
        host) deploy_scale_horizon_host "$count" ;;
        *) deploy_scale_horizon_docker "$count" ;;
    esac
}

deploy_restart_nginx() {
    local mode
    mode="$(deploy_resolve_mode "$(xf_script_root)")"

    case "$mode" in
        host) deploy_restart_nginx_host ;;
        *) deploy_restart_nginx_docker ;;
    esac
}
