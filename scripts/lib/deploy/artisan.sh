#!/usr/bin/env bash
# Run artisan in production (Docker container or host PHP).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_artisan_mode() {
    echo "${DEPLOY_ARTISAN_MODE:-${DEPLOY_TARGET:-docker}}"
}

deploy_artisan() {
    local mode
    mode="$(deploy_artisan_mode)"

    if [[ "$mode" == "host" ]]; then
        php artisan "$@"
        return $?
    fi

    xf_prod_compose exec -T app php artisan "$@"
}

deploy_artisan_run() {
    local mode
    mode="$(deploy_artisan_mode)"

    if [[ "$mode" == "host" ]]; then
        php artisan "$@"
        return $?
    fi

    xf_prod_compose run --rm --no-deps app php artisan "$@"
}

deploy_artisan_bash_lc() {
    local mode script
    mode="$(deploy_artisan_mode)"
    script="$1"

    if [[ "$mode" == "host" ]]; then
        bash -lc "$script"
        return $?
    fi

    xf_prod_compose run --rm --no-deps app bash -lc "$script"
}
