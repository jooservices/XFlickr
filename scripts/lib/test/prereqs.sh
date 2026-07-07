#!/usr/bin/env bash
# Host toolchain checks for quality gates.
set -u
set -o pipefail

test_prereqs_host_toolchain() {
    local root fail=0
    root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"

    if ! command -v php >/dev/null 2>&1; then
        echo "Missing required host tool: php (need PHP 8.5+)" >&2
        fail=1
    elif ! php -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);' 2>/dev/null; then
        echo "PHP 8.5+ required; found: $(php -r 'echo PHP_VERSION;')" >&2
        fail=1
    fi

    if ! command -v node >/dev/null 2>&1; then
        echo "Missing required host tool: node (need Node.js 22+)" >&2
        fail=1
    else
        local node_major
        node_major="$(node -p "process.versions.node.split('.')[0]")"
        if [[ "${node_major}" -lt 22 ]]; then
            echo "Node.js 22+ required; found: $(node -v)" >&2
            fail=1
        fi
    fi

    if ! command -v npm >/dev/null 2>&1; then
        echo "Missing required host tool: npm" >&2
        fail=1
    fi

    if [[ ! -f "${root}/vendor/autoload.php" ]]; then
        echo "PHP dependencies missing. Run: composer install" >&2
        fail=1
    fi

    if [[ "$fail" -ne 0 ]]; then
        return 1
    fi

    return 0
}

test_require_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        echo "Docker is required for PHPUnit (test stack). Install Docker and retry." >&2
        return 1
    fi
    if ! docker info >/dev/null 2>&1; then
        echo "Docker daemon is not running." >&2
        return 1
    fi
    return 0
}
