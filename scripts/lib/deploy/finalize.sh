#!/usr/bin/env bash
# Mandatory deploy finalize gate — verify before success message.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/app-release.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/app-init.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/common.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_finalize_restart_docker_services() {
    local context replicas
    context="${1:-update}"
    replicas="$(xf_prod_horizon_replicas)"

    if [[ "$context" == "install" ]]; then
        echo "==> Starting production stack (horizon replicas: ${replicas})"
        xf_prod_compose up -d --build --scale "horizon=${replicas}" app horizon scheduler reverb nginx || return 1
    else
        echo "==> Restarting containers (rolling — data volumes preserved)"
        xf_prod_compose up -d --build --scale "horizon=${replicas}" app horizon scheduler reverb nginx || return 1
    fi

    xf_prod_compose ps || return 1
}

deploy_finalize_restart_host_services() {
    local context="${1:-update}"

    if [[ "$context" == "install" ]]; then
        supervisorctl reread >/dev/null 2>&1 || true
        supervisorctl update >/dev/null 2>&1 || true
    fi

    supervisorctl restart xflickr-app xflickr-horizon xflickr-scheduler >/dev/null 2>&1 \
        || supervisorctl start xflickr-app xflickr-horizon xflickr-scheduler >/dev/null 2>&1 \
        || return 1

    if command -v nginx >/dev/null 2>&1; then
        nginx -t >/dev/null 2>&1 || return 1
        systemctl reload nginx >/dev/null 2>&1 || return 1
    fi
}

deploy_finalize_release() {
    local mode="$1"
    local context="${2:-update}"
    local failures=0

    export DEPLOY_ARTISAN_MODE="$mode"
    export DEPLOY_TARGET="$mode"

    echo
    echo "==> Finalizing production release (${mode}, ${context})"

    deploy_app_migrate || return 1

    if [[ "$context" == "install" ]]; then
        deploy_app_first_boot || return 1
    fi

    deploy_app_optimize_refresh || return 1
    deploy_app_graceful_worker_shutdown || true

    if [[ "$mode" == "host" ]]; then
        deploy_finalize_restart_host_services "$context" || failures=1
        # shellcheck disable=SC1091
        source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/host/verify-host.sh"
        deploy_verify_host || failures=1
    else
        deploy_finalize_restart_docker_services "$context" || failures=1
        # shellcheck disable=SC1091
        source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify-docker.sh"
        deploy_verify_stack || failures=1
    fi

    if [[ "$failures" -ne 0 ]]; then
        echo "ERROR: Deploy did not pass verification." >&2
        echo "Inspect: bash scripts/deploy.sh verify" >&2
        return 1
    fi

    if [[ "$mode" == "host" ]]; then
        deploy_host_print_urls
    else
        xf_prod_print_urls
    fi

    if [[ "$context" == "update" ]]; then
        echo "Production update complete."
    fi

    return 0
}

deploy_finalize_workers_only() {
    local mode="$1"
    local replicas="${2:-1}"
    local failures=0

    export DEPLOY_ARTISAN_MODE="$mode"
    export DEPLOY_TARGET="$mode"

    deploy_app_graceful_worker_shutdown || true

    if [[ "$mode" == "host" ]]; then
        supervisorctl restart xflickr-horizon >/dev/null 2>&1 || return 1
        # shellcheck disable=SC1091
        source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/host/verify-host.sh"
        deploy_verify_horizon_workers_host "$replicas" || failures=1
    else
        xf_prod_compose up -d --scale "horizon=${replicas}" horizon
        xf_prod_compose ps horizon
        # shellcheck disable=SC1091
        source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify-docker.sh"
        deploy_verify_horizon_workers "$replicas" || failures=1
    fi

    if [[ "$failures" -ne 0 ]]; then
        echo "ERROR: Horizon scaling did not pass verification." >&2
        return 1
    fi

    echo "Horizon scaled to ${replicas} replica(s) — verification passed."
    return 0
}
