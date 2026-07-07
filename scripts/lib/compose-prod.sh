#!/usr/bin/env bash
# Production stack docker compose invocation (project: xflickr-prod).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

XF_PROD_COMPOSE_FILE="${XF_PROD_COMPOSE_FILE:-docker-compose.prod.yml}"
XF_PROD_PROJECT="${XF_PROD_PROJECT:-xflickr-prod}"

xf_prod_compose() {
    docker compose -f "${XF_PROD_COMPOSE_FILE}" -p "${XF_PROD_PROJECT}" "$@"
}

xf_prod_horizon_replicas() {
    echo "${HORIZON_REPLICAS:-1}"
}

xf_prod_http_port() {
    echo "${HTTP_PORT:-80}"
}

xf_prod_wait_for_app() {
    local port tries=0
    port="$(xf_prod_http_port)"

    until curl -fsS "http://127.0.0.1:${port}/login" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [[ $tries -ge 90 ]]; then
            echo "Production app did not become ready on http://127.0.0.1:${port}" >&2
            return 1
        fi
        sleep 2
    done
}

xf_prod_print_urls() {
    local port replicas app_url
    port="$(xf_prod_http_port)"
    replicas="$(xf_prod_horizon_replicas)"
    app_url="${APP_URL:-http://127.0.0.1:${port}}"

    echo
    echo "XFlickr production stack is running (project: ${XF_PROD_PROJECT}):"
    echo "  App:      ${app_url}"
    echo "  Login:    ${app_url}/login"
    echo "  Horizon:  ${app_url}/horizon"
    echo "  Settings: ${app_url}/settings"
    echo
    echo "  HTTP port:         ${port}"
    echo "  Horizon replicas:  ${replicas}"
    echo "  Flickr callback:   ${FLICKR_CALLBACK_URL:-${app_url}/flickr/callback}"
    echo
    echo "  Update:  bash scripts/deploy.sh update"
    echo "  Logs:    bash scripts/deploy.sh logs"
    echo "  Scale:   bash scripts/deploy.sh scale <N>"
    echo
}
