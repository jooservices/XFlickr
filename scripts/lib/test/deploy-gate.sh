#!/usr/bin/env bash
# Quality gate for production deploy shell scripts (Ubuntu 22.04 container).
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/common.sh"

test_deploy_gate_shellcheck() {
    local root file missing=0
    root="$(xf_script_root)"

    if ! command -v shellcheck >/dev/null 2>&1; then
        echo "shellcheck not available on host — will run inside deploy-test container" >&2
        return 0
    fi

    while IFS= read -r file; do
        if ! shellcheck -S error -x "$file"; then
            missing=1
        fi
    done < <(find "${root}/scripts/deploy.sh" "${root}/scripts/lib/deploy" -name '*.sh' -type f | sort)

    return "$missing"
}

test_deploy_gate_in_container() {
    local root image
    root="$(xf_script_root)"

    if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
        echo "Docker required for deploy-script tests (ubuntu:22.04 harness)." >&2
        return 1
    fi

    image="xflickr-deploy-test:local"
    docker build -f "${root}/docker/deploy-test/Dockerfile" -t "$image" "${root}/docker/deploy-test"

    docker run --rm -v "${root}:/app" -w /app "$image" bash -lc '
        set -u
        set -o pipefail
        apt-get update -qq
        apt-get install -y -qq shellcheck >/dev/null
        while IFS= read -r file; do
            shellcheck -S error -x "$file" || exit 1
        done < <(find /app/scripts/deploy.sh /app/scripts/lib/deploy -name "*.sh" -type f | sort)
        bash /app/scripts/lib/test/deploy/run.sh
    '
}

test_gate_deploy_scripts() {
    local root
    root="$(xf_script_root)"
    cd "$root"

    printf 'XFlickr deploy-scripts gate (Ubuntu 22.04 container)\n'

    if test_deploy_gate_in_container; then
        printf '\nDeploy-scripts gate PASSED\n'
        return 0
    fi

    printf '\nDeploy-scripts gate FAILED\n'
    return 1
}
