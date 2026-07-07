#!/usr/bin/env bash
# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/host/config.sh"

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

export HTTP_PORT=8080
export HORIZON_REPLICAS=2
export SSL_ENABLED=false
export DEPLOY_HOST_APP_PORT=8000

output="$(deploy_host_render_supervisor_conf "$tmpdir")"
deploy_test_assert_contains "$output" "directory=${tmpdir}" "supervisor template project root"
deploy_test_assert_contains "$output" "numprocs=2" "supervisor horizon replicas"

nginx_out="$(deploy_host_render_nginx_site "$tmpdir")"
deploy_test_assert_contains "$nginx_out" "listen 8080" "nginx http port"
deploy_test_assert_contains "$nginx_out" "127.0.0.1:8000" "nginx app upstream"
