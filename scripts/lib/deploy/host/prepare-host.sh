#!/usr/bin/env bash
# Install Composer/npm assets on host production.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/install-prereqs.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/common.sh"

deploy_prepare_host_app() {
    local root
    root="$(xf_script_root)"

    if [[ ! -f "${root}/.env" ]]; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    cd "$root"

    echo "==> Installing PHP dependencies from Packagist and building frontend assets"
    if [[ -d vendor ]] && [[ ! -f vendor/autoload.php ]]; then
        echo "Removing broken vendor/ tree from a previous failed install..."
        rm -rf vendor
    fi

    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    npm ci --no-audit --no-fund
    npm run build

    if [[ -d storage ]]; then
        deploy_host_sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    fi

    echo "==> Application dependencies ready"
}
