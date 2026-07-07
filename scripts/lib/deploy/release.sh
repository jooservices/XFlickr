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

deploy_prod_has_env() {
    local root="$1"
    [[ -f "${root}/.env" ]]
}

deploy_prod_container_count() {
    xf_prod_compose ps -a -q 2>/dev/null | wc -l | tr -d ' '
}

deploy_prod_running_count() {
    xf_prod_compose ps --status running -q 2>/dev/null | wc -l | tr -d ' '
}

deploy_prod_is_deployed() {
    local root="$1"

    if deploy_prod_has_env "$root"; then
        return 0
    fi

  [[ "$(deploy_prod_container_count)" -gt 0 ]]
}

deploy_print_release_summary() {
    local root="$1"

    echo
    echo "==> Existing production deployment detected (project: ${XF_PROD_PROJECT})"
    echo

    if deploy_prod_has_env "$root"; then
        echo "  .env: present"
    else
        echo "  .env: missing (run install or configure)"
    fi

    echo "  Containers (all):   $(deploy_prod_container_count)"
    echo "  Containers (running): $(deploy_prod_running_count)"
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
    xf_prod_compose ps 2>/dev/null || true
    echo
}

deploy_print_safe_update_policy() {
    cat <<'EOF'
Safe release update will:
  - git pull --ff-only (no history rewrite)
  - rebuild images and frontend assets
  - run php artisan migrate --force (additive schema changes only)
  - gracefully restart workers and web containers

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

deploy_graceful_worker_shutdown() {
    if [[ "$(deploy_prod_running_count)" -eq 0 ]]; then
        return 0
    fi

    echo "==> Gracefully stopping Horizon workers (horizon:terminate)"
    xf_prod_compose exec -T app php artisan horizon:terminate --wait 2>/dev/null \
        || xf_prod_compose exec -T app php artisan horizon:terminate 2>/dev/null \
        || true
}

deploy_run_release_update() {
    local root="$1"

    if ! deploy_prod_has_env "$root"; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    xf_load_root_env "$root"
    deploy_graceful_worker_shutdown
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

    if deploy_prod_has_env "$root"; then
        echo "Configuration found (.env) — finishing install without re-running the wizard."
        deploy_finish_install "$root" || return 1
        return 0
    fi

    if [[ "$(deploy_prod_container_count)" -gt 0 ]]; then
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
