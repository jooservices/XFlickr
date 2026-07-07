#!/usr/bin/env bash
# Production stack bootstrap.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prepare.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify.sh"

deploy_bootstrap_stack() {
    local root replicas
    root="$(xf_script_root)"
    replicas="$(xf_prod_horizon_replicas)"

    deploy_prepare_production_app || return 1

    echo "==> Starting production stack (horizon replicas: ${replicas})"
    xf_prod_compose up -d --build --scale "horizon=${replicas}" app horizon scheduler nginx
    xf_prod_compose ps

    if ! deploy_verify_stack; then
        return 1
    fi

    xf_prod_print_urls
}

deploy_finish_install() {
    local root="$1"

    if [[ ! -f "${root}/.env" ]]; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    echo "==> Finishing production install (using existing .env — wizard skipped)"
    xf_load_root_env "$root"
    deploy_bootstrap_stack
}

xf_wait_for_app_prod() {
    xf_prod_wait_for_app
}

deploy_scale_horizon() {
    local count="$1"
    local root env_file

    root="$(xf_script_root)"
    env_file="${root}/.env"

    if [[ ! -f "$env_file" ]]; then
        echo "ERROR: .env not found." >&2
        return 1
    fi

    if [[ ! "$count" =~ ^[0-9]+$ ]] || [[ "$count" -lt 1 ]]; then
        echo "ERROR: Replica count must be a positive integer." >&2
        return 1
    fi

    if grep -q '^HORIZON_REPLICAS=' "$env_file"; then
        sed -i.bak "s|^HORIZON_REPLICAS=.*|HORIZON_REPLICAS=${count}|" "$env_file"
        rm -f "${env_file}.bak"
    else
        echo "HORIZON_REPLICAS=${count}" >>"$env_file"
    fi

    export HORIZON_REPLICAS="$count"
    echo "==> Scaling horizon to ${count} replica(s)"
    xf_prod_compose up -d --scale "horizon=${count}" horizon
    xf_prod_compose ps horizon
}

deploy_restart_nginx() {
    xf_prod_compose up -d --force-recreate nginx
}
