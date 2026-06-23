#!/usr/bin/env bash
# Cursor helper: run Copilot CLI implementation review after green test gate.
# Usage: bash scripts/implementation-review.sh
# Requires: copilot on PATH; ai/plans/final_plan.md; git diff available
# Prerequisites: composer test:docker must have passed

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FINAL_PLAN="${ROOT}/ai/plans/final_plan.md"
STANDARDS="${ROOT}/.cursor/prompts/implementation-audit-standards.md"
COPILOT_PROMPT="${ROOT}/.cursor/prompts/implementation-audit-copilot.md"
OUT_REVIEW="${ROOT}/ai/plans/implementation_review_copilot.md"
TMP_PROMPT="/tmp/xflickr-implementation-audit-prompt.txt"
TMP_DIFF="/tmp/xflickr-implementation-diff.txt"

die() {
    echo "implementation-review: $*" >&2
    exit 1
}

command -v copilot >/dev/null 2>&1 || die "copilot CLI not found on PATH"
[[ -f "${FINAL_PLAN}" ]] || die "missing ${FINAL_PLAN} — write final_plan.md first"
[[ -f "${STANDARDS}" ]] || die "missing ${STANDARDS}"
[[ -f "${COPILOT_PROMPT}" ]] || die "missing ${COPILOT_PROMPT}"

mkdir -p "${ROOT}/ai/plans"

build_prompt() {
    cat "${STANDARDS}"
    echo ""
    cat "${COPILOT_PROMPT}"
    echo ""
    echo "---"
    echo "## Approved final_plan.md"
    echo ""
    cat "${FINAL_PLAN}"
    echo ""
    echo "---"
    echo "## Git diff (implementation changes)"
    echo ""
    echo '```diff'
    cat "${TMP_DIFF}"
    echo '```'
}

git -C "${ROOT}" diff > "${TMP_DIFF}" 2>/dev/null || true
if [[ ! -s "${TMP_DIFF}" ]]; then
    git -C "${ROOT}" diff --cached > "${TMP_DIFF}" 2>/dev/null || true
fi
if [[ ! -s "${TMP_DIFF}" ]]; then
    die "no git diff found — implement changes before review"
fi

echo "implementation-review: running Copilot audit -> ${OUT_REVIEW}"
build_prompt > "${TMP_PROMPT}"

copilot -p "Follow the instructions above exactly. Output the Copilot Implementation Review markdown only." \
    --mode plan \
    --silent \
    --no-color \
    --excluded-tools=write,edit,apply_patch,create \
    < "${TMP_PROMPT}" \
    > "${OUT_REVIEW}" 2> "${ROOT}/ai/plans/implementation_review_copilot.stderr.log" || {
    status=$?
    echo "implementation-review: copilot exited ${status}" >&2
    exit "${status}"
}

echo "implementation-review: done"
echo "  - ${OUT_REVIEW}"
