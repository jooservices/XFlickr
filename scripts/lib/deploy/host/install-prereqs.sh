#!/usr/bin/env bash
# Install PHP, Composer, Node, nginx, supervisor on Ubuntu 22.04.
set -u
set -o pipefail

deploy_host_is_ubuntu_2204() {
    if [[ ! -f /etc/os-release ]]; then
        return 1
    fi
    # shellcheck disable=SC1091
    source /etc/os-release
    [[ "${ID:-}" == "ubuntu" && "${VERSION_ID:-}" == "22.04" ]]
}

deploy_host_sudo() {
    if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
        "$@"
    else
        sudo "$@"
    fi
}

deploy_host_install_prereqs() {
    if ! deploy_host_is_ubuntu_2204; then
        echo "ERROR: Automatic prerequisite install supports Ubuntu 22.04 only." >&2
        echo "Install PHP 8.5+, extensions, Composer, Node 22+, nginx, and supervisor manually." >&2
        return 1
    fi

    echo "==> Installing host prerequisites (Ubuntu 22.04)"
    deploy_host_sudo apt-get update -qq || return 1
    deploy_host_sudo apt-get install -y -qq \
        ca-certificates curl git gnupg lsb-release software-properties-common unzip || return 1

    if ! apt-cache show php8.5-cli >/dev/null 2>&1; then
        deploy_host_sudo add-apt-repository -y ppa:ondrej/php || return 1
        deploy_host_sudo apt-get update -qq || return 1
    fi

    deploy_host_sudo apt-get install -y -qq \
        php8.5-cli php8.5-bcmath php8.5-curl php8.5-intl php8.5-mbstring \
        php8.5-mysql php8.5-xml php8.5-zip php8.5-redis php8.5-mongodb \
        nginx supervisor || return 1

    if ! command -v composer >/dev/null 2>&1; then
        curl -fsSL https://getcomposer.org/installer | php || return 1
        deploy_host_sudo mv composer.phar /usr/local/bin/composer || return 1
        deploy_host_sudo chmod +x /usr/local/bin/composer || return 1
    fi

    if ! command -v node >/dev/null 2>&1; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | deploy_host_sudo bash - || return 1
        deploy_host_sudo apt-get install -y -qq nodejs || return 1
    fi

    echo "✓ Host prerequisites installed"
    return 0
}
