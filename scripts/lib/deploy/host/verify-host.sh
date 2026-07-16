#!/usr/bin/env bash
# Post-deploy verification for host production.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/connectivity.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/config.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_verify_host_supervisor() {
    local expected_replicas="$1"
    local ok=0 horizon_count

    if supervisorctl status xflickr-app 2>/dev/null | grep -q RUNNING; then
        echo "  ✓ xflickr-app running"
    else
        echo "  ✗ xflickr-app not running" >&2
        ok=1
    fi

    horizon_count="$(supervisorctl status 'xflickr-horizon:*' 2>/dev/null | grep -c RUNNING || echo 0)"
    if [[ "${horizon_count:-0}" -lt "$expected_replicas" ]]; then
        echo "  ✗ xflickr-horizon: expected ${expected_replicas} running, got ${horizon_count:-0}" >&2
        ok=1
    else
        echo "  ✓ xflickr-horizon running (${horizon_count} replica(s))"
    fi

    if supervisorctl status xflickr-scheduler 2>/dev/null | grep -q RUNNING; then
        echo "  ✓ xflickr-scheduler running"
    else
        echo "  ✗ xflickr-scheduler not running" >&2
        ok=1
    fi

    return "$ok"
}

deploy_verify_host_app() {
    local root ok=0
    root="$(xf_script_root)"

    if [[ -f "${root}/public/build/manifest.json" ]]; then
        echo "  ✓ Frontend build manifest present"
    else
        echo "  ✗ Frontend build manifest missing" >&2
        ok=1
    fi

    if php artisan migrate:status --no-interaction >/dev/null 2>&1; then
        echo "  ✓ Database migrations accessible"
    else
        echo "  ✗ Database migrations check failed" >&2
        ok=1
    fi

    if php artisan xflickr:flickr:doctor --no-interaction; then
        echo "  ✓ Application doctor checks passed"
    else
        echo "  ✗ Application doctor checks failed" >&2
        ok=1
    fi

    return "$ok"
}

deploy_verify_host_web() {
    local port base_url ok=0
    port="${HTTP_PORT:-80}"
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

    return "$ok"
}

deploy_verify_horizon_workers_host() {
    local expected_replicas="$1"
    local ok=0

    if pgrep -f "artisan horizon" >/dev/null 2>&1; then
        echo "  ✓ Horizon master process active"
    else
        echo "  ✗ Horizon master process not detected" >&2
        ok=1
    fi

    if pgrep -f "artisan schedule:work" >/dev/null 2>&1; then
        echo "  ✓ Scheduler process active"
    else
        echo "  ✗ Scheduler process not detected" >&2
        ok=1
    fi

    deploy_verify_host_supervisor "$expected_replicas" || ok=1
    return "$ok"
}

deploy_verify_external_connectivity_host() {
    local ok=0 mongo_host="${MONGODB_HOST:-}"

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

deploy_verify_host() {
    local replicas failures=0
    replicas="${HORIZON_REPLICAS:-1}"

    echo
    echo "==> Post-deploy verification (host)"
    echo

    echo "--- External database connectivity ---"
    deploy_verify_external_connectivity_host || failures=1
    echo

    echo "--- Web endpoints ---"
    deploy_verify_host_web || failures=1
    echo

    echo "--- Application health ---"
    deploy_verify_host_app || failures=1
    echo

    echo "--- Background workers ---"
    deploy_verify_horizon_workers_host "$replicas" || failures=1
    echo

    if [[ "$failures" -ne 0 ]]; then
        echo "ERROR: Post-deploy verification failed." >&2
        echo "Inspect: bash scripts/deploy.sh verify" >&2
        return 1
    fi

    echo "✓ All checks passed — XFlickr is ready to use."
    echo
    echo "Next steps:"
    echo "  1. Sign in at ${APP_URL:-http://127.0.0.1:${HTTP_PORT:-80}}/login"
    echo "  2. Open Settings and add your Flickr API credentials"
    echo "  3. Register Flickr callback: ${FLICKR_CALLBACK_URL:-${APP_URL}/flickr/callback}"
    echo

    return 0
}
