#!/usr/bin/env bash
# Test stack docker compose invocation (project: xflickr-test).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

XF_TEST_COMPOSE_FILE="${XF_TEST_COMPOSE_FILE:-docker-compose.test.yml}"
XF_TEST_PROJECT="${XF_TEST_PROJECT:-xflickr-test}"

xf_test_compose() {
    docker compose -f "${XF_TEST_COMPOSE_FILE}" -p "${XF_TEST_PROJECT}" "$@"
}

xf_test_load_env() {
    local root="$1"
    xf_load_test_env "$root"
}
