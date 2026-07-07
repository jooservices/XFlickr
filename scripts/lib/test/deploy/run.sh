#!/usr/bin/env bash
# Deploy script test harness — runs inside ubuntu:22.04 container.
set -u
set -o pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
cd "$ROOT"
export ROOT

TESTS_DIR="${ROOT}/scripts/lib/test/deploy/tests"
FAILURES=0
RAN=0

deploy_test_assert_eq() {
    local expected="$1"
    local actual="$2"
    local label="$3"

    RAN=$((RAN + 1))
    if [[ "$expected" == "$actual" ]]; then
        printf '[PASS] %s\n' "$label"
    else
        printf '[FAIL] %s (expected=%q actual=%q)\n' "$label" "$expected" "$actual"
        FAILURES=$((FAILURES + 1))
    fi
}

deploy_test_assert_contains() {
    local haystack="$1"
    local needle="$2"
    local label="$3"

    RAN=$((RAN + 1))
    if [[ "$haystack" == *"$needle"* ]]; then
        printf '[PASS] %s\n' "$label"
    else
        printf '[FAIL] %s (missing %q in output)\n' "$label" "$needle"
        FAILURES=$((FAILURES + 1))
    fi
}

deploy_test_run_file() {
    local file="$1"
    printf '\n==> %s\n' "$(basename "$file")"
    # shellcheck disable=SC1090
    source "$file"
}

if [[ -d "$TESTS_DIR" ]]; then
    for test_file in "$TESTS_DIR"/*.bash; do
        [[ -f "$test_file" ]] || continue
        deploy_test_run_file "$test_file"
    done
fi

printf '\nDeploy script tests: %s run, %s failed\n' "$RAN" "$FAILURES"
exit "$FAILURES"
