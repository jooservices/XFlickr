#!/usr/bin/env bash
# XFlickr production Docker deploy — operator only (not for AI agents).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/common.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/compose-prod.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/preflight.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/wizard.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/bootstrap.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/update.sh"

xf_load_root_env "$ROOT"

usage() {
    cat <<EOF
  bash scripts/deploy.sh install           First-time wizard + start production stack
  bash scripts/deploy.sh update            git pull + rebuild + migrate + restart
  bash scripts/deploy.sh configure         Re-run service wizard (preserves APP_KEY)
  bash scripts/deploy.sh configure-ssl     Update HTTPS certificate settings
  bash scripts/deploy.sh scale <N>         Scale horizon containers to N replicas
  bash scripts/deploy.sh restart-nginx     Recreate nginx after SSL changes
  bash scripts/deploy.sh ps|status         Show container status
  bash scripts/deploy.sh logs [service]    Follow logs (default: all)
  bash scripts/deploy.sh down              Stop production stack

  Production stack only (project: xflickr-prod, compose: docker-compose.prod.yml).
  Local dev:  bash scripts/dev.sh
  Tests/CI:   bash scripts/test.sh gate

  Operator only — AI must use scripts/test.sh, never dev or prod stacks.
EOF
}

cmd="${1:-install}"
shift || true

deploy_preflight "$ROOT" || exit 1

case "$cmd" in
    install)
        if [[ ! -f "${ROOT}/.env" ]]; then
            deploy_wizard_run "$ROOT" install || exit 1
            xf_load_root_env "$ROOT"
        else
            echo "Found existing .env — starting stack. Use 'configure' to change settings."
        fi
        deploy_bootstrap_stack
        ;;
    configure)
        deploy_wizard_run "$ROOT" configure || exit 1
        xf_load_root_env "$ROOT"
        echo "Restarting stack to apply configuration..."
        deploy_update_stack
        ;;
    configure-ssl)
        deploy_wizard_configure_ssl "$ROOT" || exit 1
        xf_load_root_env "$ROOT"
        deploy_restart_nginx
        ;;
    update)
        if [[ ! -f "${ROOT}/.env" ]]; then
            echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
            exit 1
        fi
        deploy_update_stack
        ;;
    scale)
        count="${1:-}"
        if [[ -z "$count" ]]; then
            echo "Usage: bash scripts/deploy.sh scale <N>" >&2
            exit 1
        fi
        xf_load_root_env "$ROOT"
        deploy_scale_horizon "$count"
        ;;
    restart-nginx)
        deploy_restart_nginx
        ;;
    ps|status)
        xf_prod_compose ps
        ;;
    logs)
        if [[ -n "${1:-}" ]]; then
            xf_prod_compose logs -f "$1"
        else
            xf_prod_compose logs -f
        fi
        ;;
    down)
        xf_prod_compose down
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        echo "Unknown command: ${cmd}" >&2
        usage >&2
        exit 1
        ;;
esac
