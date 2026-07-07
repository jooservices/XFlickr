#!/usr/bin/env bash
# XFlickr local dev Docker stack — operator only (not for AI agents).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/common.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/compose-dev.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/dev/bootstrap.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/dev/frontend.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/dev/reset-data.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/operator/reload.sh"

xf_load_root_env "$ROOT"

usage() {
    cat <<EOF
  bash scripts/dev.sh up                  Start stack + migrate only (no seed)
  bash scripts/dev.sh seed                Same as up + seed admin user
  bash scripts/dev.sh quick               Start services only (skip composer/npm/migrate)
  bash scripts/dev.sh refresh             Reset MySQL schema (migrate:fresh) + admin seed only
  bash scripts/dev.sh reset-data          Stop stack + wipe ALL dev volumes
  bash scripts/dev.sh down                Stop dev stack (keep volumes)
  bash scripts/dev.sh down --volumes      Stop dev stack and remove volumes (same as reset-data)
  bash scripts/dev.sh reload              Build assets, clear caches, restart workers (no migrations)
  bash scripts/dev.sh restart-frontend    Recreate frontend container (no DB impact)
  bash scripts/dev.sh refresh-frontend    npm ci in frontend container + restart Vite
  bash scripts/dev.sh ps                  Show container status
  bash scripts/dev.sh logs [service]      Follow logs (default: all services)

  Operator only — AI must use scripts/test.sh (test stack), never dev.

  Host ports (override in .env — see .env.example):
    app      ${APP_HOST_PORT:-8082}
    frontend ${FRONTEND_HOST_PORT:-5174}
    mysql    ${MYSQL_HOST_PORT:-3308}

  Shortcut: ./scripts/docker-up.sh  (same as: bash scripts/dev.sh up)
EOF
}

cmd="${1:-up}"
shift || true

case "$cmd" in
    up)
        xf_dev_up
        ;;
    seed)
        xf_dev_seed
        ;;
    quick|start)
        xf_dev_quick
        ;;
    refresh)
        xf_dev_ensure_env "$ROOT"
        xf_dev_start_stack
        xf_dev_bootstrap_refresh
        xf_wait_for_app
        xf_wait_for_frontend
        xf_print_urls
        ;;
    reset-data)
        dev_reset_data
        ;;
    down)
        if [[ "${1:-}" == "--volumes" ]]; then
            dev_reset_data
        else
            xf_dev_compose down
        fi
        ;;
    reload)
        dev_reload_stack
        ;;
    restart-frontend)
        dev_frontend_restart
        ;;
    refresh-frontend)
        dev_frontend_refresh
        ;;
    ps|status)
        xf_dev_compose ps
        ;;
    logs)
        if [[ -n "${1:-}" ]]; then
            xf_dev_compose logs -f "$1"
        else
            xf_dev_compose logs -f
        fi
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        usage
        exit 1
        ;;
esac
