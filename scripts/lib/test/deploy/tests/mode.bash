#!/usr/bin/env bash
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/mode.sh"

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

printf 'DEPLOY_TARGET=host\n' >"${tmpdir}/.env"
deploy_test_assert_eq "host" "$(deploy_mode_from_env_file "$tmpdir")" "mode from .env"

mkdir -p "${tmpdir}/storage"
printf 'DEPLOY_TARGET=docker\n' >"${tmpdir}/storage/.xflickr-deploy-wizard"
deploy_test_assert_eq "docker" "$(deploy_mode_from_wizard_state "$tmpdir")" "mode from wizard state"

deploy_test_assert_eq "127.0.0.1" "$(deploy_wizard_default_service_host host)" "host default service host"
deploy_test_assert_eq "host.docker.internal" "$(deploy_wizard_default_service_host docker)" "docker default service host"
