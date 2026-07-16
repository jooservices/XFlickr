#!/usr/bin/env bash
# Install Composer/npm assets before starting production containers.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-prod.sh"

deploy_prepare_production_app() {
    local root
    root="$(xf_script_root)"

    if [[ ! -f "${root}/.env" ]]; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    echo "==> Building production app image"
    xf_prod_compose build app || return 1

    echo "==> Installing PHP dependencies from Packagist and building frontend assets"
    xf_prod_compose run --rm --no-deps app bash -lc \
        'git config --global --add safe.directory /var/www/html 2>/dev/null || true
set -e
if [[ -d vendor ]] && [[ ! -f vendor/autoload.php ]]; then
  echo "Removing broken vendor/ tree from a previous failed install..."
  rm -rf vendor
fi
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --no-audit --no-fund
npm run build' || return 1

    echo "==> Application dependencies ready"
}
