#!/usr/bin/env bash

deploy_test_prepare_docker_fails_with_compose() (
    local tmpdir
    tmpdir="$(mktemp -d)"
    trap 'rm -rf "$tmpdir"' EXIT
    touch "${tmpdir}/.env"

    # shellcheck disable=SC1091
    source "${ROOT}/scripts/lib/deploy/prepare-docker.sh" || return 1
    xf_script_root() { echo "$tmpdir"; }
    xf_prod_compose() {
        [[ "${1:-}" == "build" ]] && return 0
        return 42
    }

    deploy_prepare_production_app >/dev/null 2>&1
)

if deploy_test_prepare_docker_fails_with_compose; then
    deploy_test_assert_eq "failure" "success" "Docker dependency failure propagates"
else
    deploy_test_assert_eq "failure" "failure" "Docker dependency failure propagates"
fi

deploy_test_prepare_host_stops_after_composer_failure() (
    local tmpdir npm_marker
    tmpdir="$(mktemp -d)"
    npm_marker="${tmpdir}/npm-called"
    trap 'rm -rf "$tmpdir"' EXIT
    touch "${tmpdir}/.env"

    # shellcheck disable=SC1091
    source "${ROOT}/scripts/lib/deploy/host/prepare-host.sh" || return 1
    xf_script_root() { echo "$tmpdir"; }
    composer() { return 42; }
    npm() { touch "$npm_marker"; }

    if deploy_prepare_host_app >/dev/null 2>&1; then
        return 1
    fi
    [[ ! -e "$npm_marker" ]]
)

if deploy_test_prepare_host_stops_after_composer_failure; then
    deploy_test_assert_eq "protected" "protected" "Host dependency failure propagates"
else
    deploy_test_assert_eq "protected" "masked" "Host dependency failure propagates"
fi

deploy_test_host_update_stops_after_pull_failure() (
    local tmpdir prepare_marker
    tmpdir="$(mktemp -d)"
    prepare_marker="${tmpdir}/prepare-called"
    trap 'rm -rf "$tmpdir"' EXIT
    mkdir -p "${tmpdir}/.git"

    # shellcheck disable=SC1091
    source "${ROOT}/scripts/lib/deploy/host/update-host.sh" || return 1
    xf_script_root() { echo "$tmpdir"; }
    git() { return 42; }
    deploy_prepare_host_app() { touch "$prepare_marker"; }

    if deploy_update_host_stack >/dev/null 2>&1; then
        return 1
    fi
    [[ ! -e "$prepare_marker" ]]
)

if deploy_test_host_update_stops_after_pull_failure; then
    deploy_test_assert_eq "protected" "protected" "Host update pull failure propagates"
else
    deploy_test_assert_eq "protected" "masked" "Host update pull failure propagates"
fi

deploy_test_host_update_reinstalls_service_config() (
    local tmpdir config_marker
    tmpdir="$(mktemp -d)"
    config_marker="${tmpdir}/config-installed"
    trap 'rm -rf "$tmpdir"' EXIT

    # shellcheck disable=SC1091
    source "${ROOT}/scripts/lib/deploy/host/update-host.sh" || return 1
    xf_script_root() { echo "$tmpdir"; }
    deploy_prepare_host_app() { return 0; }
    deploy_host_install_configs() { touch "$config_marker"; }
    deploy_finalize_release() { return 0; }

    deploy_update_host_stack >/dev/null 2>&1
    [[ -e "$config_marker" ]]
)

if deploy_test_host_update_reinstalls_service_config; then
    deploy_test_assert_eq "installed" "installed" "Host update reinstalls service config"
else
    deploy_test_assert_eq "installed" "missing" "Host update reinstalls service config"
fi

deploy_test_docker_bootstrap_avoids_host_php_waiter() (
    # shellcheck disable=SC1091
    source "${ROOT}/scripts/lib/deploy/bootstrap-docker.sh" || return 1
    deploy_prepare_production_app() { return 0; }
    deploy_app_wait_for_services() { return 42; }
    deploy_finalize_release() { return 0; }

    deploy_bootstrap_docker_stack >/dev/null 2>&1
)

if deploy_test_docker_bootstrap_avoids_host_php_waiter; then
    deploy_test_assert_eq "container" "container" "Docker bootstrap avoids host PHP waiter"
else
    deploy_test_assert_eq "container" "host-php" "Docker bootstrap avoids host PHP waiter"
fi

if grep -R -E 'curl .*-[A-Za-z]*k' "${ROOT}/scripts/lib/deploy" >/dev/null 2>&1; then
    deploy_test_assert_eq "secure" "insecure" "Deploy health checks never disable TLS verification"
else
    deploy_test_assert_eq "secure" "secure" "Deploy health checks never disable TLS verification"
fi
