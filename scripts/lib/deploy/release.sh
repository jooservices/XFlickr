#!/usr/bin/env bash
# Detect existing production deployments and run safe release updates.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/wizard.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/bootstrap.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/update.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prod-state.sh"

deploy_prod_is_deployed() {
    local root="$1"
    local mode
    mode="$(deploy_resolve_mode "$root")"
    deploy_prod_is_deployed_for_mode "$root" "$mode"
}

deploy_print_release_summary() {
    local root="$1"
    local mode
    mode="$(deploy_resolve_mode "$root")"

    echo
    echo "==> Existing production deployment detected (target: ${mode})"
    echo

    if deploy_prod_has_env "$root"; then
        echo "  .env: present (DEPLOY_TARGET=${mode})"
    else
        echo "  .env: missing (run install or configure)"
    fi

    if [[ "$mode" == "host" ]]; then
        echo "  Supervisor:"
        supervisorctl status xflickr-app xflickr-horizon xflickr-scheduler 2>/dev/null | sed 's/^/    /' || echo "    (not running)"
    else
        echo "  Containers (all):   $(deploy_prod_container_count)"
        echo "  Containers (running): $(deploy_prod_running_count)"
        echo
        xf_prod_compose ps 2>/dev/null || true
    fi
    echo

    if [[ -d "${root}/.git" ]]; then
        echo "  Current commit: $(git -C "$root" log -1 --oneline 2>/dev/null || echo 'unknown')"

        if git -C "$root" remote get-url origin >/dev/null 2>&1; then
            git -C "$root" fetch -q 2>/dev/null || true
            local behind
            behind="$(git -C "$root" rev-list --count HEAD..@{upstream} 2>/dev/null || echo 0)"
            if [[ "$behind" -gt 0 ]]; then
                echo "  Upstream:       ${behind} commit(s) available"
                echo
                echo "  Incoming changes:"
                git -C "$root" log --oneline "HEAD..@{upstream}" 2>/dev/null | sed 's/^/    /' | head -10 || true
                if [[ "$behind" -gt 10 ]]; then
                    echo "    ... and $((behind - 10)) more"
                fi
            else
                echo "  Upstream:       up to date (or no tracking branch)"
            fi
        fi
    fi

    echo
}

deploy_print_safe_update_policy() {
    cat <<'EOF'
Safe release update will:
  - git pull --ff-only (no history rewrite)
  - rebuild images and frontend assets
  - run php artisan migrate --force (additive schema changes only)
  - clear and rebuild Laravel caches (config, route, view, optimize)
  - gracefully restart Horizon, scheduler, and web services
  - verify health before reporting success

It will NOT:
  - remove Docker volumes or external database data
  - run migrate:fresh, migrate:refresh, or db:wipe
  - stop the stack with down --volumes
EOF
}

deploy_confirm_release_update() {
    deploy_print_safe_update_policy
    echo
    deploy_prompt_yes_no "Deploy new release now?" "y"
}

deploy_run_release_update() {
    local root="$1"

    if ! deploy_prod_has_env "$root"; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    xf_load_root_env "$root"
    deploy_update_stack
}

deploy_dispatch() {
    local root="$1"

    if deploy_prod_is_deployed "$root"; then
        deploy_print_release_summary "$root"

        if deploy_confirm_release_update; then
            deploy_run_release_update "$root" || return 1
        else
            echo "Release update cancelled."
            return 0
        fi
    else
        echo "No existing production deployment found — starting first-time install."
        deploy_wizard_run "$root" install || return 1
        xf_load_root_env "$root"
        deploy_bootstrap_stack || return 1
    fi
}

deploy_install_fresh() {
    local root="$1"
    local mode

    if deploy_prod_has_env "$root"; then
        echo "Configuration found (.env) — finishing install without re-running the wizard."
        deploy_finish_install "$root" || return 1
        return 0
    fi

    mode="$(deploy_resolve_mode "$root" 1)"
    export DEPLOY_TARGET="$mode"
    deploy_assert_mode_consistent "$root" "$mode" || return 1

    if [[ "$mode" == "docker" ]] && [[ "$(deploy_prod_container_count)" -gt 0 ]]; then
        echo "A production deployment already exists on this host."
        deploy_print_release_summary "$root"
        echo "Use: bash scripts/deploy.sh deploy   (or: bash scripts/deploy.sh update)"
        echo "First-time install was not run to avoid affecting existing data."
        return 1
    fi

    deploy_wizard_run "$root" install || return 1
    xf_load_root_env "$root"
    deploy_bootstrap_stack || return 1
}
