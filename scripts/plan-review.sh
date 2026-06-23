#!/usr/bin/env bash
# Cursor helper: run Grok + Codex plan audits for ai/plans/plan.md
# Usage:
#   bash scripts/plan-review.sh              # Grok + Codex debate
#   bash scripts/plan-review.sh --grok-only
#   bash scripts/plan-review.sh --codex-only
# Requires: grok and/or codex on PATH; ai/plans/plan.md must exist
# Workflow: docs/04-development/ai-development-workflow.md

set -euo pipefail

GROK_ONLY=0
CODEX_ONLY=0
for arg in "$@"; do
    case "${arg}" in
        --grok-only) GROK_ONLY=1 ;;
        --codex-only) CODEX_ONLY=1 ;;
        -h|--help)
            echo "Usage: bash scripts/plan-review.sh [--grok-only | --codex-only]"
            exit 0
            ;;
    esac
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLAN="${ROOT}/ai/plans/plan.md"
STANDARDS="${ROOT}/.cursor/prompts/plan-audit-standards.md"
GROK_PROMPT="${ROOT}/.cursor/prompts/plan-audit-grok.md"
DEBATE_PROMPT="${ROOT}/.cursor/prompts/plan-debate-codex.md"
OUT_GROK="${ROOT}/ai/plans/plan_grok.md"
OUT_CODEX="${ROOT}/ai/plans/plan_codex_debate.md"
TMP_PROMPT="/tmp/xflickr-plan-audit-prompt.txt"

die() {
    echo "plan-review: $*" >&2
    exit 1
}

build_grok_prompt() {
    cat "${STANDARDS}"
    echo ""
    cat "${GROK_PROMPT}"
    echo ""
    echo "---"
    echo "## Plan under review"
    echo ""
    cat "${PLAN}"
}

run_grok() {
    command -v grok >/dev/null 2>&1 || die "grok CLI not found on PATH"
    [[ -f "${STANDARDS}" ]] || die "missing ${STANDARDS}"
    [[ -f "${GROK_PROMPT}" ]] || die "missing ${GROK_PROMPT}"

    echo "plan-review: running Grok audit -> ${OUT_GROK}"
    build_grok_prompt > "${TMP_PROMPT}"
    grok --prompt-file "${TMP_PROMPT}" \
        --output-format plain \
        --permission-mode plan \
        2>"${ROOT}/ai/plans/plan_grok.stderr.log" | sed 's/\x1b\[[0-9;]*m//g' \
        > "${OUT_GROK}"
}

run_codex_debate() {
    command -v codex >/dev/null 2>&1 || die "codex CLI not found on PATH"
    [[ -f "${DEBATE_PROMPT}" ]] || die "missing ${DEBATE_PROMPT}"

    echo "plan-review: running Codex debate -> ${OUT_CODEX}"
    {
        cat "${DEBATE_PROMPT}"
        echo ""
        echo "---"
        echo "## plan.md (document under debate)"
        echo ""
        cat "${PLAN}"
        if [[ -f "${OUT_GROK}" ]]; then
            echo ""
            echo "---"
            echo "## plan_grok.md (Grok review — context for debate)"
            echo ""
            cat "${OUT_GROK}"
        fi
    } | codex exec -C "${ROOT}" --sandbox read-only - \
        2>"${ROOT}/ai/plans/plan_codex_debate.stderr.log" | sed 's/\x1b\[[0-9;]*m//g' \
        > "${OUT_CODEX}"
}

[[ -f "${PLAN}" ]] || die "missing ${PLAN} — write plan.md first"
mkdir -p "${ROOT}/ai/plans"

if [[ "${CODEX_ONLY}" -eq 1 ]]; then
    run_codex_debate
    echo "plan-review: done"
    echo "  - ${OUT_CODEX}"
    echo "Next: Cursor evidence-gates ai/plans/final_plan.md"
    exit 0
fi

run_grok

if [[ "${GROK_ONLY}" -eq 0 ]]; then
    run_codex_debate
else
    echo "plan-review: skipping Codex debate (--grok-only)"
fi

echo "plan-review: done"
echo "  - ${OUT_GROK}"
if [[ "${GROK_ONLY}" -eq 0 ]]; then
    echo "  - ${OUT_CODEX}"
fi
echo "Next: Cursor evidence-gates ai/plans/final_plan.md"
