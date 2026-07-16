#!/usr/bin/env bash
# Post-deploy verification — connections, containers, web readiness.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/connectivity.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_verify_containers() {
    local expected_replicas="$1"
    local service running_count
    local -a required=(app nginx scheduler)
    local ok=0

    for service in "${required[@]}"; do
        running_count="$(xf_prod_compose ps --status running "$service" 2>/dev/null | tail -n +2 | wc -l | tr -d ' ')"
        if [[ "${running_count:-0}" -lt 1 ]]; then
            echo "  ✗ ${service}: not running" >&2
            ok=1
        else
            echo "  ✓ ${service} running"
        fi
    done

    running_count="$(xf_prod_compose ps --status running horizon 2>/dev/null | tail -n +2 | wc -l | tr -d ' ')"
    if [[ "${running_count:-0}" -lt "$expected_replicas" ]]; then
        echo "  ✗ horizon: expected ${expected_replicas} running, got ${running_count:-0}" >&2
        ok=1
    else
        echo "  ✓ horizon running (${running_count} replica(s))"
    fi

    return "$ok"
}

deploy_verify_external_connectivity() {
    local ok=0

    if deploy_test_mysql "${DB_HOST}" "${DB_PORT}" "${DB_DATABASE}" "${DB_USERNAME}" "${DB_PASSWORD}"; then
        echo "  ✓ MySQL reachable"
    else
        ok=1
    fi

    if deploy_test_redis "${REDIS_HOST}" "${REDIS_PORT}" "${REDIS_PASSWORD:-}"; then
        echo "  ✓ Redis reachable"
    else
        ok=1
    fi

  local mongo_host="${MONGODB_HOST:-}"
  if [[ -z "$mongo_host" && -n "${MONGODB_URI:-}" ]]; then
      mongo_host="$(echo "$MONGODB_URI" | sed -E 's#^mongodb(\+srv)?://([^/@]+@)?([^:/]+).*#\3#')"
  fi

    if deploy_test_mongodb "${MONGODB_URI}" "${mongo_host}"; then
        echo "  ✓ MongoDB reachable"
    else
        ok=1
    fi

    return "$ok"
}

deploy_verify_app_container() {
    local ok=0

    if xf_prod_compose exec -T app test -f public/build/manifest.json; then
        echo "  ✓ Frontend build manifest present"
    else
        echo "  ✗ Frontend build manifest missing (public/build/manifest.json)" >&2
        ok=1
    fi

    if xf_prod_compose exec -T app php artisan migrate:status --no-interaction >/dev/null 2>&1; then
        echo "  ✓ Database migrations accessible"
    else
        echo "  ✗ Database migrations check failed" >&2
        ok=1
    fi

    if xf_prod_compose exec -T app php artisan xflickr:flickr:doctor --no-interaction; then
        echo "  ✓ Application doctor checks passed"
    else
        echo "  ✗ Application doctor checks failed" >&2
        ok=1
    fi

    return "$ok"
}

deploy_verify_web() {
    local port base_url ok=0
    port="$(xf_prod_http_port)"
    base_url="http://127.0.0.1:${port}"

    if curl -fsS "${base_url}/login" >/dev/null 2>&1; then
        echo "  ✓ Web login page ready (${base_url}/login)"
    else
        echo "  ✗ Web login page not reachable at ${base_url}/login" >&2
        ok=1
    fi

    if curl -fsS "${base_url}/build/manifest.json" >/dev/null 2>&1; then
        echo "  ✓ Frontend assets served via nginx"
    else
        echo "  ✗ Frontend assets not reachable at ${base_url}/build/manifest.json" >&2
        ok=1
    fi

    if [[ "${SSL_ENABLED:-false}" == "true" ]] && [[ -f "${SSL_CERT_PATH:-}/cert.pem" ]]; then
        local https_port="${HTTPS_PORT:-443}" https_url
        https_url="${APP_URL:-https://127.0.0.1}"
        https_url="https://${https_url#*://}"
        if curl --cacert "${SSL_CERT_PATH}/cert.pem" --connect-to "::127.0.0.1:${https_port}" \
            -fsS "${https_url%/}/login" >/dev/null 2>&1; then
            echo "  ✓ HTTPS login page ready (port ${https_port})"
        else
            echo "  ✗ HTTPS login page not reachable on port ${https_port}" >&2
            ok=1
        fi
    fi

    return "$ok"
}

deploy_container_cmdline() {
    local service="$1"
    local pid="${2:-1}"

    xf_prod_compose exec -T "$service" sh -c "tr '\\0' ' ' < /proc/${pid}/cmdline 2>/dev/null || true"
}

deploy_container_has_artisan_command() {
    local service="$1" artisan_subcommand="$2"
    local cmdline

    cmdline="$(deploy_container_cmdline "$service")"
    [[ "$cmdline" == *"artisan ${artisan_subcommand}"* ]]
}

deploy_verify_horizon_workers() {
    local expected_replicas="$1"
    local running_count ok=0 horizon_cmd scheduler_cmd

    running_count="$(xf_prod_compose ps --status running horizon 2>/dev/null | tail -n +2 | wc -l | tr -d ' ')"

    if [[ "${running_count:-0}" -lt "$expected_replicas" ]]; then
        echo "  ✗ Horizon worker containers not fully running" >&2
        return 1
    fi

    if deploy_container_has_artisan_command horizon "horizon"; then
        echo "  ✓ Horizon master process active"
    else
        horizon_cmd="$(deploy_container_cmdline horizon)"
        echo "  ✗ Horizon master process not detected (pid 1: ${horizon_cmd:-unknown})" >&2
        ok=1
    fi

    if deploy_container_has_artisan_command scheduler "schedule:work"; then
        echo "  ✓ Scheduler process active"
    else
        scheduler_cmd="$(deploy_container_cmdline scheduler)"
        echo "  ✗ Scheduler process not detected (pid 1: ${scheduler_cmd:-unknown})" >&2
        ok=1
    fi

    return "$ok"
}

deploy_verify_stack() {
    local replicas failures=0
    replicas="$(xf_prod_horizon_replicas)"

    echo
    echo "==> Post-deploy verification"
    echo

    echo "--- Docker services ---"
    deploy_verify_containers "$replicas" || failures=1
    echo

    echo "--- External database connectivity ---"
    deploy_verify_external_connectivity || failures=1
    echo

    echo "--- Waiting for web application ---"
    if ! xf_prod_wait_for_app; then
        echo "  ✗ Application did not become ready in time" >&2
        failures=1
    else
        echo "  ✓ Application HTTP endpoint responding"
    fi
    echo

    echo "--- Web endpoints ---"
    deploy_verify_web || failures=1
    echo

    echo "--- Application health ---"
    deploy_verify_app_container || failures=1
    echo

    echo "--- Background workers ---"
    deploy_verify_horizon_workers "$replicas" || failures=1
    echo

    if [[ "$failures" -ne 0 ]]; then
        echo "ERROR: Post-deploy verification failed." >&2
        echo "Inspect logs: bash scripts/deploy.sh logs" >&2
        return 1
    fi

    echo "✓ All checks passed — XFlickr is ready to use."
    echo
    echo "Next steps:"
    echo "  1. Sign in at ${APP_URL:-http://127.0.0.1:$(xf_prod_http_port)}/login"
    echo "  2. Open Settings and add your Flickr API credentials"
    echo "  3. Register Flickr callback: ${FLICKR_CALLBACK_URL:-${APP_URL}/flickr/callback}"
    echo

    return 0
}
