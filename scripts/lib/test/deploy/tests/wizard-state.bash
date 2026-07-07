#!/usr/bin/env bash
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/state.sh"

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

DEPLOY_WIZARD_ROOT="$tmpdir"
DEPLOY_TARGET=host
APP_URL=http://example.test
DB_HOST=127.0.0.1
deploy_wizard_state_save "$tmpdir"

deploy_test_assert_contains "$(cat "${tmpdir}/storage/.xflickr-deploy-wizard")" "DEPLOY_TARGET=" "wizard state saves DEPLOY_TARGET"
deploy_test_assert_contains "$(cat "${tmpdir}/storage/.xflickr-deploy-wizard")" "host" "wizard state DEPLOY_TARGET value"

# shellcheck disable=SC1090
source "${tmpdir}/storage/.xflickr-deploy-wizard"
deploy_test_assert_eq "host" "${DEPLOY_TARGET}" "wizard state loads DEPLOY_TARGET"
