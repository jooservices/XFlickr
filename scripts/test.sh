#!/usr/bin/env bash
# XFlickr quality gates + isolated test Docker stack (AI/CI only).
set -u
set -o pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/common.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/compose-test.sh"
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/test/gate.sh"

usage() {
    cat <<'EOF'
  bash scripts/test.sh gate:lint              Lint only (host — no PHPUnit Docker)
  bash scripts/test.sh gate:test              Lint + PHPUnit (Docker) + Vitest
  bash scripts/test.sh gate:deploy-scripts     Deploy shell scripts (Ubuntu 22.04 container)
  bash scripts/test.sh gate:ci                Canonical CI/pre-push gate
  bash scripts/test.sh gate                   Alias for gate:lint + gate:test
  bash scripts/test.sh setup-hooks            Install .githooks (pre-commit / pre-push)
  bash scripts/test.sh ensure-hooks           Install hooks if missing (run once per clone)
  bash scripts/test.sh verify-hooks           Fail if hooks not installed
  bash scripts/test.sh up                     Start test stack services
  bash scripts/test.sh down                   Stop test stack
  bash scripts/test.sh down --volumes         Stop test stack and remove volumes

Workflow:
  Clone      → ensure-hooks (once)
  Say done   → gate:lint + gate:test
  Commit     → gate (pre-commit hook runs this too)
  Push       → gate:ci (pre-push hook runs this too)

AI agents: this script is the ONLY permitted Docker/test entry point.
EOF
}

cmd="${1:-gate}"
shift || true

case "$cmd" in
    gate:lint)
        test_gate_lint
        ;;
    gate:test)
        test_gate_test
        ;;
    gate:deploy-scripts)
        # shellcheck disable=SC1091
        source "${ROOT}/scripts/lib/test/deploy-gate.sh"
        test_gate_deploy_scripts
        ;;
    gate:ci)
        test_gate_ci
        ;;
    gate)
        test_gate_lint && test_gate_test
        ;;
    setup-hooks)
        bash "${ROOT}/scripts/setup-git-hooks.sh" install
        ;;
    ensure-hooks)
        bash "${ROOT}/scripts/setup-git-hooks.sh" verify || bash "${ROOT}/scripts/setup-git-hooks.sh" install
        ;;
    verify-hooks)
        bash "${ROOT}/scripts/setup-git-hooks.sh" verify
        ;;
    up)
        xf_test_load_env "$ROOT"
        xf_test_compose up -d --build --wait
        xf_test_compose ps
        ;;
    down)
        if [[ "${1:-}" == "--volumes" ]]; then
            xf_test_compose down -v
        else
            xf_test_compose down
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
