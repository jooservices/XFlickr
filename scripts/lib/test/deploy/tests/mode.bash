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

unset DEPLOY_TARGET
prompt_root="$(mktemp -d)"
deploy_test_assert_eq "yes" "$(deploy_command_needs_target_prompt "$prompt_root" install && echo yes || echo no)" "fresh install prompts for target"
deploy_test_assert_eq "no" "$(deploy_command_needs_target_prompt "$prompt_root" verify && echo yes || echo no)" "non-install command does not prompt for target"
printf 'DEPLOY_TARGET=host\n' >"${prompt_root}/.env"
deploy_test_assert_eq "no" "$(deploy_command_needs_target_prompt "$prompt_root" install && echo yes || echo no)" "configured install reuses target"
rm -rf "$prompt_root"

if deploy_validate_mode invalid >/dev/null 2>&1; then
    deploy_test_assert_eq "invalid" "accepted" "invalid deploy target is rejected"
else
    deploy_test_assert_eq "invalid" "invalid" "invalid deploy target is rejected"
fi

replica_root="$(mktemp -d)"
touch "${replica_root}/.env"
deploy_set_horizon_replicas "$replica_root" 3
deploy_test_assert_contains "$(cat "${replica_root}/.env")" "HORIZON_REPLICAS=3" "shared replica helper writes value"
deploy_set_horizon_replicas "$replica_root" 2
deploy_test_assert_eq "HORIZON_REPLICAS=2" "$(cat "${replica_root}/.env")" "shared replica helper replaces value"
if deploy_set_horizon_replicas "$replica_root" 0 >/dev/null 2>&1; then
    deploy_test_assert_eq "invalid" "accepted" "shared replica helper rejects zero"
else
    deploy_test_assert_eq "invalid" "invalid" "shared replica helper rejects zero"
fi
rm -rf "$replica_root"
