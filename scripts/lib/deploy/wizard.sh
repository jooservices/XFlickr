#!/usr/bin/env bash
# Interactive production deploy wizard.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/connectivity.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/ssl-selfsigned.sh"

deploy_prompt() {
    local label="$1"
    local default="${2:-}"
    local value

    if [[ -n "$default" ]]; then
        read -r -p "${label} [${default}]: " value
        echo "${value:-$default}"
    else
        read -r -p "${label}: " value
        echo "$value"
    fi
}

deploy_prompt_secret() {
    local label="$1"
    local value

    read -r -s -p "${label}: " value
    echo >&2
    echo "$value"
}

deploy_prompt_yes_no() {
    local label="$1"
    local default="${2:-n}"
    local value

    read -r -p "${label} [y/N]: " value
    value="${value:-$default}"
    [[ "$value" =~ ^[Yy] ]]
}

deploy_confirm() {
    local value

    read -r -p "Proceed? [y/N]: " value
    [[ "$value" =~ ^[Yy] ]]
}

deploy_wizard_prompt_mysql() {
    while true; do
        echo
        echo "==> MySQL (external)"
        DB_HOST=$(deploy_prompt "MySQL host" "${DB_HOST:-host.docker.internal}")
        DB_PORT=$(deploy_prompt "MySQL port" "${DB_PORT:-3306}")
        DB_DATABASE=$(deploy_prompt "MySQL database" "${DB_DATABASE:-xflickr}")
        DB_USERNAME=$(deploy_prompt "MySQL username" "${DB_USERNAME:-xflickr}")
        DB_PASSWORD=$(deploy_prompt_secret "MySQL password")

        echo "Testing MySQL connection..."
        if deploy_test_mysql "$DB_HOST" "$DB_PORT" "$DB_DATABASE" "$DB_USERNAME" "$DB_PASSWORD"; then
            echo "✓ MySQL connected"
            export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
            return 0
        fi

        echo "MySQL connection failed. Please correct the values and try again."
    done
}

deploy_wizard_prompt_redis() {
    while true; do
        echo
        echo "==> Redis (external)"
        REDIS_HOST=$(deploy_prompt "Redis host" "${REDIS_HOST:-host.docker.internal}")
        REDIS_PORT=$(deploy_prompt "Redis port" "${REDIS_PORT:-6379}")

        if deploy_prompt_yes_no "Does Redis require a password?" "n"; then
            REDIS_PASSWORD=$(deploy_prompt_secret "Redis password")
        else
            REDIS_PASSWORD=""
        fi

        echo "Testing Redis connection..."
        if deploy_test_redis "$REDIS_HOST" "$REDIS_PORT" "$REDIS_PASSWORD"; then
            echo "✓ Redis connected"
            export REDIS_HOST REDIS_PORT REDIS_PASSWORD
            return 0
        fi

        echo "Redis connection failed. Please correct the values and try again."
    done
}

deploy_wizard_prompt_mongodb() {
    while true; do
        echo
        echo "==> MongoDB (external)"
        MONGODB_HOST=$(deploy_prompt "MongoDB host" "${MONGODB_HOST:-host.docker.internal}")
        MONGODB_PORT=$(deploy_prompt "MongoDB port" "${MONGODB_PORT:-27017}")
        MONGODB_DATABASE=$(deploy_prompt "MongoDB database" "${MONGODB_DATABASE:-xflickr}")

        if deploy_prompt_yes_no "Does MongoDB require authentication?" "n"; then
            MONGODB_USERNAME=$(deploy_prompt "MongoDB username" "${MONGODB_USERNAME:-}")
            MONGODB_PASSWORD=$(deploy_prompt_secret "MongoDB password")
        else
            MONGODB_USERNAME=""
            MONGODB_PASSWORD=""
        fi

        MONGODB_URI=$(deploy_build_mongodb_uri \
            "$MONGODB_HOST" "$MONGODB_PORT" "$MONGODB_DATABASE" \
            "$MONGODB_USERNAME" "$MONGODB_PASSWORD")

        echo "Testing MongoDB connection..."
        if deploy_test_mongodb "$MONGODB_URI" "$MONGODB_HOST"; then
            echo "✓ MongoDB connected"
            export MONGODB_HOST MONGODB_PORT MONGODB_DATABASE MONGODB_URI
            export MONGODB_USERNAME MONGODB_PASSWORD
            return 0
        fi

        echo "MongoDB connection failed. Please correct the values and try again."
    done
}

deploy_wizard_write_env() {
    local root="$1"
    local env_file="${root}/.env"
    local existing_key=""

    if [[ -f "$env_file" ]]; then
        existing_key=$(grep -E '^APP_KEY=' "$env_file" | head -1 | cut -d= -f2- || true)
    fi

    if [[ -n "${PRESERVE_APP_KEY:-}" && -n "$existing_key" ]]; then
        APP_KEY="$existing_key"
    fi

    cat >"$env_file" <<EOF
APP_NAME=xflickr
APP_ENV=production
APP_KEY=${APP_KEY:-}
APP_DEBUG=false
APP_URL=${APP_URL}

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

HTTP_PORT=${HTTP_PORT:-80}
HTTPS_PORT=${HTTPS_PORT:-443}
SSL_ENABLED=${SSL_ENABLED:-false}
SSL_CERT_PATH=${SSL_CERT_PATH:-./docker/nginx/ssl-empty}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
CACHE_STORE=database

REDIS_CLIENT=phpredis
REDIS_HOST=${REDIS_HOST}
REDIS_PASSWORD=${REDIS_PASSWORD:-}
REDIS_PORT=${REDIS_PORT:-6379}

MAIL_MAILER=log

VITE_APP_NAME=xflickr
INERTIA_SSR_ENABLED=false

HORIZON_NAME=xflickr-horizon
HORIZON_REPLICAS=${HORIZON_REPLICAS:-1}

FLICKR_CALLBACK_URL=${FLICKR_CALLBACK_URL}

MONGODB_URI=${MONGODB_URI}
MONGODB_DATABASE=${MONGODB_DATABASE:-xflickr}
CONFIG_STORE_CACHE_ENABLED=true
EVENTS_EVENTSOURCING_ENABLED=true
EVENTS_EVENT_LOG_ENABLED=true

ADMIN_EMAIL=${ADMIN_EMAIL}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
EOF

    chmod 600 "$env_file"
    echo "Wrote ${env_file}"
}

deploy_wizard_run() {
    local root="$1"
    local mode="${2:-install}"

    echo "XFlickr production setup wizard"
    echo "External MySQL, Redis, and MongoDB are required."
    echo

    if [[ "$mode" == "configure" && -f "${root}/.env" ]]; then
        # shellcheck disable=SC1091
        set -a
        source "${root}/.env"
        set +a
        PRESERVE_APP_KEY=1
        echo "Re-configuring production services (APP_KEY preserved)."
    fi

    if [[ "$mode" == "install" ]]; then
        if [[ -d "${root}/.git" ]]; then
            if deploy_prompt_yes_no "Run git pull before install?" "y"; then
                git -C "$root" pull --ff-only
            fi
        else
            echo "Not a git repository — skipping git pull."
        fi
    fi

    APP_URL=$(deploy_prompt "Public APP_URL (http://ip-or-host:port)" "${APP_URL:-http://127.0.0.1}")
    HTTP_PORT=$(deploy_prompt "HTTP port (nginx)" "${HTTP_PORT:-80}")
    FLICKR_CALLBACK_URL="${APP_URL%/}/flickr/callback"

    deploy_wizard_prompt_mysql
    deploy_wizard_prompt_redis
    deploy_wizard_prompt_mongodb

    if ! deploy_test_all_services; then
        echo "ERROR: Final connectivity check failed." >&2
        return 1
    fi

    ADMIN_EMAIL=$(deploy_prompt "Admin email" "${ADMIN_EMAIL:-admin@local}")
    while [[ -z "${ADMIN_PASSWORD:-}" ]]; do
        ADMIN_PASSWORD=$(deploy_prompt_secret "Admin password (required)")
        if [[ -z "$ADMIN_PASSWORD" ]]; then
            echo "Admin password cannot be empty."
        fi
    done

    SSL_ENABLED=false
    SSL_CERT_PATH="./docker/nginx/ssl-empty"
    if deploy_prompt_yes_no "Enable HTTPS?" "n"; then
        SSL_ENABLED=true
        if deploy_prompt_yes_no "Generate a self-signed certificate?" "y"; then
            local cn cert_dir
            cn=$(deploy_prompt "Certificate common name (IP or hostname)" "$(echo "$APP_URL" | sed -E 's#https?://([^/:]+).*#\1#')")
            cert_dir="${root}/storage/prod-ssl"
            deploy_generate_self_signed_cert "$cert_dir" "$cn"
            SSL_CERT_PATH="$cert_dir"
        else
            SSL_CERT_PATH=$(deploy_prompt "Path to directory containing cert.pem and key.pem" "${SSL_CERT_PATH}")
            if [[ ! -f "${SSL_CERT_PATH}/cert.pem" || ! -f "${SSL_CERT_PATH}/key.pem" ]]; then
                echo "ERROR: cert.pem and key.pem not found in ${SSL_CERT_PATH}" >&2
                return 1
            fi
        fi
    fi

    HORIZON_REPLICAS=$(deploy_prompt "Horizon container replicas" "${HORIZON_REPLICAS:-1}")

    echo
    echo "==> Summary"
    echo "  APP_URL:           ${APP_URL}"
    echo "  HTTP port:         ${HTTP_PORT}"
    echo "  MySQL:             ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE}"
    echo "  Redis:             ${REDIS_HOST}:${REDIS_PORT}"
    echo "  MongoDB:           ${MONGODB_URI}"
    echo "  Admin:             ${ADMIN_EMAIL}"
    echo "  HTTPS:             ${SSL_ENABLED}"
    echo "  Horizon replicas:  ${HORIZON_REPLICAS}"
    echo "  Flickr callback:   ${FLICKR_CALLBACK_URL}"
    echo

    if ! deploy_confirm; then
        echo "Aborted."
        return 1
    fi

    deploy_wizard_write_env "$root"
}

deploy_wizard_configure_ssl() {
    local root="$1"

    if [[ ! -f "${root}/.env" ]]; then
        echo "ERROR: .env not found. Run: bash scripts/deploy.sh install" >&2
        return 1
    fi

    # shellcheck disable=SC1091
    set -a
    source "${root}/.env"
    set +a

    if deploy_prompt_yes_no "Enable HTTPS?" "${SSL_ENABLED:-false}"; then
        SSL_ENABLED=true
        if deploy_prompt_yes_no "Generate a self-signed certificate?" "y"; then
            local cn cert_dir
            cn=$(deploy_prompt "Certificate common name" "$(echo "${APP_URL:-localhost}" | sed -E 's#https?://([^/:]+).*#\1#')")
            cert_dir="${root}/storage/prod-ssl"
            deploy_generate_self_signed_cert "$cert_dir" "$cn"
            SSL_CERT_PATH="$cert_dir"
        else
            SSL_CERT_PATH=$(deploy_prompt "SSL cert directory" "${SSL_CERT_PATH:-./docker/nginx/ssl-empty}")
        fi
    else
        SSL_ENABLED=false
        SSL_CERT_PATH="./docker/nginx/ssl-empty"
    fi

    deploy_wizard_write_env "$root"
    echo "SSL settings updated. Restart nginx: bash scripts/deploy.sh restart-nginx"
}
