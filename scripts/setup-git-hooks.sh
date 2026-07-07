#!/usr/bin/env bash
# Point this repository at tracked git hooks under .githooks/
set -u
set -o pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

git_hooks_path() {
    git config --get core.hooksPath 2>/dev/null || true
}

git_hooks_installed() {
    [[ "$(git_hooks_path)" == ".githooks" ]] \
        && [[ -x .githooks/pre-commit ]] \
        && [[ -x .githooks/pre-push ]]
}

verify_git_hooks() {
    if git_hooks_installed; then
        return 0
    fi

    printf 'Git hooks are NOT installed.\n' >&2
    printf 'Run: bash scripts/test.sh setup-hooks\n' >&2
    return 1
}

install_git_hooks() {
    git config core.hooksPath .githooks
    chmod +x .githooks/pre-commit .githooks/pre-push 2>/dev/null || true

    printf 'Git hooks installed (core.hooksPath=.githooks)\n'
    printf '  pre-commit: gate (lint + full test)\n'
    printf '  pre-push:   gate:ci\n'
    printf 'Bypass (operator only — not for AI): SKIP_HOOKS=1 git commit|push\n'
}

case "${1:-install}" in
    verify)
        verify_git_hooks
        ;;
    install|setup)
        install_git_hooks
        ;;
    *)
        printf 'Usage: bash scripts/setup-git-hooks.sh [install|verify]\n' >&2
        exit 1
        ;;
esac
