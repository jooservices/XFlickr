#!/usr/bin/env bash
# Persist in-progress wizard answers so install can resume after interruption.
set -u
set -o pipefail

deploy_wizard_state_path() {
    local root="$1"
    echo "${root}/storage/.xflickr-deploy-wizard"
}

deploy_wizard_state_load() {
    local root="$1"
    local state_file

    DEPLOY_WIZARD_ROOT="$root"
    state_file="$(deploy_wizard_state_path "$root")"

    if [[ ! -f "$state_file" ]]; then
        return 0
    fi

    echo "Resuming saved wizard progress from ${state_file}"
    # shellcheck disable=SC1090
    source "$state_file"
}

deploy_wizard_state_save() {
    local root="${DEPLOY_WIZARD_ROOT:-$1}"
    local state_file

    state_file="$(deploy_wizard_state_path "$root")"
    mkdir -p "$(dirname "$state_file")"

    {
        printf 'WIZARD_COMPLETED_STEPS=%q\n' "${WIZARD_COMPLETED_STEPS:-}"
        printf 'APP_URL=%q\n' "${APP_URL:-}"
        printf 'HTTP_PORT=%q\n' "${HTTP_PORT:-}"
        printf 'FLICKR_CALLBACK_URL=%q\n' "${FLICKR_CALLBACK_URL:-}"
        printf 'DB_HOST=%q\n' "${DB_HOST:-}"
        printf 'DB_PORT=%q\n' "${DB_PORT:-}"
        printf 'DB_DATABASE=%q\n' "${DB_DATABASE:-}"
        printf 'DB_USERNAME=%q\n' "${DB_USERNAME:-}"
        printf 'DB_PASSWORD=%q\n' "${DB_PASSWORD:-}"
        printf 'REDIS_HOST=%q\n' "${REDIS_HOST:-}"
        printf 'REDIS_PORT=%q\n' "${REDIS_PORT:-}"
        printf 'REDIS_PASSWORD=%q\n' "${REDIS_PASSWORD:-}"
        printf 'REDIS_REQUIRES_PASSWORD=%q\n' "${REDIS_REQUIRES_PASSWORD:-}"
        printf 'MONGODB_HOST=%q\n' "${MONGODB_HOST:-}"
        printf 'MONGODB_PORT=%q\n' "${MONGODB_PORT:-}"
        printf 'MONGODB_DATABASE=%q\n' "${MONGODB_DATABASE:-}"
        printf 'MONGODB_USERNAME=%q\n' "${MONGODB_USERNAME:-}"
        printf 'MONGODB_PASSWORD=%q\n' "${MONGODB_PASSWORD:-}"
        printf 'MONGODB_URI=%q\n' "${MONGODB_URI:-}"
        printf 'MONGODB_REQUIRES_AUTH=%q\n' "${MONGODB_REQUIRES_AUTH:-}"
        printf 'ADMIN_EMAIL=%q\n' "${ADMIN_EMAIL:-}"
        printf 'ADMIN_PASSWORD=%q\n' "${ADMIN_PASSWORD:-}"
        printf 'SSL_ENABLED=%q\n' "${SSL_ENABLED:-}"
        printf 'SSL_CERT_PATH=%q\n' "${SSL_CERT_PATH:-}"
        printf 'HORIZON_REPLICAS=%q\n' "${HORIZON_REPLICAS:-}"
    } >"$state_file"

    chmod 600 "$state_file"
}

deploy_wizard_state_clear() {
    local root="$1"
    local state_file

    state_file="$(deploy_wizard_state_path "$root")"
    rm -f "$state_file"
}

deploy_wizard_step_done() {
    local step="$1"

    if [[ ",${WIZARD_COMPLETED_STEPS:-}," != *",${step},"* ]]; then
        if [[ -n "${WIZARD_COMPLETED_STEPS:-}" ]]; then
            WIZARD_COMPLETED_STEPS="${WIZARD_COMPLETED_STEPS},${step}"
        else
            WIZARD_COMPLETED_STEPS="$step"
        fi
    fi

    deploy_wizard_state_save "${DEPLOY_WIZARD_ROOT:-}"
}

deploy_wizard_step_is_done() {
    local step="$1"
    [[ ",${WIZARD_COMPLETED_STEPS:-}," == *",${step},"* ]]
}

deploy_normalize_app_url() {
    local url="$1"

    if [[ ! "$url" =~ ^https?:// ]]; then
        url="http://${url}"
    fi

    echo "${url%/}"
}
