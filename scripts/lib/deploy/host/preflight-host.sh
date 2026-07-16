#!/usr/bin/env bash
# Host production preflight checks.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/install-prereqs.sh"

deploy_host_required_php_extensions() {
    echo pdo_mysql redis mongodb intl mbstring xml curl zip bcmath pcntl
}

deploy_host_check_php_extension() {
    local ext="$1"
    php -m 2>/dev/null | grep -qi "^${ext}$"
}

deploy_host_check_prereqs() {
    local missing=0 ext

    for required_cmd in git curl php composer node npm nginx supervisorctl; do
        if ! command -v "$required_cmd" >/dev/null 2>&1; then
            echo "Missing required command: ${required_cmd}" >&2
            missing=1
        fi
    done

    if command -v php >/dev/null 2>&1; then
        if ! php -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);' 2>/dev/null; then
            echo "PHP 8.5+ required; found: $(php -r 'echo PHP_VERSION;')" >&2
            missing=1
        fi

        for ext in $(deploy_host_required_php_extensions); do
            if ! deploy_host_check_php_extension "$ext"; then
                echo "Missing PHP extension: ${ext}" >&2
                missing=1
            fi
        done
    fi

    return "$missing"
}

deploy_preflight_host() {
    local root="$1"
    local auto_install="${DEPLOY_HOST_AUTO_INSTALL:-1}"
    local answer

    if deploy_host_check_prereqs; then
        return 0
    fi

    if [[ "$auto_install" == "1" ]] && deploy_host_is_ubuntu_2204; then
        if [[ "${DEPLOY_NONINTERACTIVE:-}" == "1" ]]; then
            deploy_host_install_prereqs || return 1
        else
            read -r -p "Install missing host prerequisites (Ubuntu 22.04)? [Y/n]: " answer
            answer="${answer:-y}"
            if [[ "$answer" =~ ^[Yy] ]]; then
                deploy_host_install_prereqs || return 1
            else
                return 1
            fi
        fi
    elif [[ "$auto_install" == "1" ]]; then
        echo "ERROR: Automatic prerequisite install supports Ubuntu 22.04 only." >&2
        echo "Install PHP 8.5+, extensions, Composer, Node 22+, nginx, and supervisor manually." >&2
        return 1
    fi

    deploy_host_check_prereqs || return 1
    return 0
}
