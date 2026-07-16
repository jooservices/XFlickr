#!/usr/bin/env bash
# XFlickr production deploy — Docker or host (operator only, not for AI agents).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/common.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/compose-prod.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/mode.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/preflight.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/wizard.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/bootstrap.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/update.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/release.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/verify-docker.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/host/verify-host.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/host/bootstrap-host.sh"

xf_load_root_env "$ROOT"

usage() {
    cat <<EOF
  bash scripts/deploy.sh                   Detect existing stack → prompt → safe release update
  bash scripts/deploy.sh deploy            Same as default (recommended for repeat deploys)
  bash scripts/deploy.sh install           First-time install (wizard: Docker or host target)
  bash scripts/deploy.sh finish            Complete install using existing .env (no wizard)
  bash scripts/deploy.sh update            Safe release update (git pull + rebuild + migrate)
  bash scripts/deploy.sh configure         Re-run service wizard (preserves APP_KEY)
  bash scripts/deploy.sh configure-ssl     Update HTTPS certificate settings
  bash scripts/deploy.sh scale <N>         Scale horizon workers to N replicas
  bash scripts/deploy.sh verify            Re-run post-deploy health checks
  bash scripts/deploy.sh restart-nginx     Recreate/reload nginx after SSL changes
  bash scripts/deploy.sh ps|status         Show service status
  bash scripts/deploy.sh logs [service]    Follow logs (Docker: compose; host: supervisor logs)
  bash scripts/deploy.sh down              Stop production (containers or supervisor)

  Deploy target is stored as DEPLOY_TARGET=docker|host in .env (chosen at install).

  Safe updates never run migrate:fresh, db:wipe, or down --volumes.
  Every install/update runs migrate, cache clear/recache, worker restart, and verify before success.

  Local dev:  bash scripts/dev.sh
  Tests/CI:   bash scripts/test.sh gate

  Operator only — AI must use scripts/test.sh, never dev or prod stacks.
EOF
}

deploy_verify_for_mode() {
    local mode
    mode="$(deploy_resolve_mode "$ROOT")"

    case "$mode" in
        host) deploy_verify_host ;;
        *) deploy_verify_stack ;;
    esac
}

cmd="${1:-deploy}"
shift || true

case "$cmd" in
    -h|--help|help)
        usage
        exit 0
        ;;
esac

if deploy_command_needs_target_prompt "$ROOT" "$cmd"; then
    DEPLOY_MODE="$(deploy_resolve_mode "$ROOT" 1)"
    export DEPLOY_TARGET="$DEPLOY_MODE"
else
    DEPLOY_MODE="$(deploy_resolve_mode "$ROOT")"
fi

deploy_preflight "$ROOT" "$DEPLOY_MODE" || exit 1

case "$cmd" in
    deploy|"")
        deploy_dispatch "$ROOT" || exit 1
        ;;
    install)
        deploy_install_fresh "$ROOT" || exit 1
        ;;
    finish|bootstrap)
        deploy_finish_install "$ROOT" || exit 1
        ;;
    configure)
        deploy_wizard_run "$ROOT" configure || exit 1
        echo "Applying configuration..."
        deploy_run_release_update "$ROOT" || exit 1
        ;;
    configure-ssl)
        deploy_wizard_configure_ssl "$ROOT" || exit 1
        xf_load_root_env "$ROOT"
        deploy_restart_nginx
        ;;
    update)
        if ! deploy_prod_has_env "$ROOT" && [[ "$(deploy_prod_container_count)" -eq 0 ]] && ! deploy_host_services_running; then
            echo "ERROR: No production deployment found. Run: bash scripts/deploy.sh install" >&2
            exit 1
        fi

        if deploy_prod_is_deployed "$ROOT"; then
            deploy_print_release_summary "$ROOT"
            if ! deploy_confirm_release_update; then
                echo "Release update cancelled."
                exit 0
            fi
        fi

        deploy_run_release_update "$ROOT" || exit 1
        ;;
    verify)
        if [[ ! -f "${ROOT}/.env" ]]; then
            echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
            exit 1
        fi
        xf_load_root_env "$ROOT"
        deploy_verify_for_mode || exit 1
        ;;
    scale)
        count="${1:-}"
        if [[ -z "$count" ]]; then
            echo "Usage: bash scripts/deploy.sh scale <N>" >&2
            exit 1
        fi
        xf_load_root_env "$ROOT"
        deploy_scale_horizon "$count" || exit 1
        ;;
    restart-nginx)
        deploy_restart_nginx
        ;;
    ps|status)
        if [[ "$DEPLOY_MODE" == "host" ]]; then
            supervisorctl status xflickr-app xflickr-horizon xflickr-scheduler 2>/dev/null || true
        else
            xf_prod_compose ps
        fi
        ;;
    logs)
        if [[ "$DEPLOY_MODE" == "host" ]]; then
            case "${1:-app}" in
                horizon) tail -f /var/log/xflickr/horizon.log ;;
                scheduler) tail -f /var/log/xflickr/scheduler.log ;;
                *) tail -f /var/log/xflickr/app.log ;;
            esac
        elif [[ -n "${1:-}" ]]; then
            xf_prod_compose logs -f "$1"
        else
            xf_prod_compose logs -f
        fi
        ;;
    down)
        if [[ "$DEPLOY_MODE" == "host" ]]; then
            deploy_host_down
        else
            echo "Stopping production stack (containers only — volumes and external databases are kept)."
            xf_prod_compose down
        fi
        ;;
    *)
        echo "Unknown command: ${cmd}" >&2
        usage >&2
        exit 1
        ;;
esac
