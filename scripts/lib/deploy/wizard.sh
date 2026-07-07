#!/usr/bin/env bash
# Interactive production deploy wizard.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/connectivity.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/state.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/ssl-selfsigned.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/mode.sh"

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

deploy_prompt_secret_optional() {
    local label="$1"
    local saved="${2:-}"
    local value

    if [[ -n "$saved" ]]; then
        read -r -s -p "${label} [Enter to keep saved]: " value
        echo >&2
        if [[ -z "$value" ]]; then
            echo "$saved"
            return 0
        fi
        echo "$value"
        return 0
    fi

    deploy_prompt_secret "$label"
}

deploy_prompt_yes_no() {
    local label="$1"
    local default="${2:-n}"
    local value prompt_suffix="y/N"

    if [[ "$default" =~ ^[Yy] ]]; then
        prompt_suffix="Y/n"
    fi

    read -r -p "${label} [${prompt_suffix}]: " value
    value="${value:-$default}"
    [[ "$value" =~ ^[Yy] ]]
}

deploy_confirm() {
    local value

    read -r -p "Proceed? [y/N]: " value
    [[ "$value" =~ ^[Yy] ]]
}

deploy_wizard_step_target() {
    local default=docker

    if [[ -n "${DEPLOY_TARGET:-}" ]]; then
        echo "✓ Deploy target: ${DEPLOY_TARGET}"
        return 0
    fi

    if ! deploy_can_use_docker_daemon; then
        default=host
    fi

    DEPLOY_TARGET=$(deploy_prompt_target "$default")
    export DEPLOY_TARGET
    deploy_wizard_step_done target
}

deploy_wizard_step_general() {
    APP_URL=$(deploy_prompt "Public APP_URL (http://ip-or-host:port)" "${APP_URL:-http://127.0.0.1}")
    APP_URL=$(deploy_normalize_app_url "$APP_URL")
    HTTP_PORT=$(deploy_prompt "HTTP port (nginx)" "${HTTP_PORT:-80}")
    FLICKR_CALLBACK_URL="${APP_URL%/}/flickr/callback"

    export APP_URL HTTP_PORT FLICKR_CALLBACK_URL
    deploy_wizard_state_save
}

deploy_wizard_prompt_mysql() {
    while true; do
        echo
        echo "==> MySQL (external)"
        DB_HOST=$(deploy_prompt "MySQL host" "${DB_HOST:-$(deploy_wizard_default_service_host "${DEPLOY_TARGET:-docker}")}")
        DB_PORT=$(deploy_prompt "MySQL port" "${DB_PORT:-3306}")
        DB_DATABASE=$(deploy_prompt "MySQL database" "${DB_DATABASE:-xflickr}")
        DB_USERNAME=$(deploy_prompt "MySQL username" "${DB_USERNAME:-xflickr}")
        DB_PASSWORD=$(deploy_prompt_secret_optional "MySQL password" "${DB_PASSWORD:-}")

        deploy_wizard_state_save

        echo "Testing MySQL connection..."
        if deploy_test_mysql "$DB_HOST" "$DB_PORT" "$DB_DATABASE" "$DB_USERNAME" "$DB_PASSWORD"; then
            echo "✓ MySQL connected"
            export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
            deploy_wizard_step_done mysql
            return 0
        fi

        echo "MySQL connection failed. Please correct the values and try again."
    done
}

deploy_wizard_prompt_redis() {
    local redis_password_default="n"

    if [[ "${REDIS_REQUIRES_PASSWORD:-}" == "y" ]]; then
        redis_password_default="y"
    fi

    while true; do
        echo
        echo "==> Redis (external)"
        REDIS_HOST=$(deploy_prompt "Redis host" "${REDIS_HOST:-$(deploy_wizard_default_service_host "${DEPLOY_TARGET:-docker}")}")
        REDIS_PORT=$(deploy_prompt "Redis port" "${REDIS_PORT:-6379}")

        if deploy_prompt_yes_no "Does Redis require a password?" "$redis_password_default"; then
            REDIS_REQUIRES_PASSWORD="y"
            REDIS_PASSWORD=$(deploy_prompt_secret_optional "Redis password" "${REDIS_PASSWORD:-}")
        else
            REDIS_REQUIRES_PASSWORD="n"
            REDIS_PASSWORD=""
        fi

        deploy_wizard_state_save

        echo "Testing Redis connection..."
        if deploy_test_redis "$REDIS_HOST" "$REDIS_PORT" "$REDIS_PASSWORD"; then
            echo "✓ Redis connected"
            export REDIS_HOST REDIS_PORT REDIS_PASSWORD REDIS_REQUIRES_PASSWORD
            deploy_wizard_step_done redis
            return 0
        fi

        echo "Redis connection failed. Please correct the values and try again."
    done
}

deploy_wizard_prompt_mongodb() {
    local mongodb_auth_default="n"

    if [[ "${MONGODB_REQUIRES_AUTH:-}" == "y" ]]; then
        mongodb_auth_default="y"
    fi

    while true; do
        echo
        echo "==> MongoDB (external)"
        MONGODB_HOST=$(deploy_prompt "MongoDB host" "${MONGODB_HOST:-$(deploy_wizard_default_service_host "${DEPLOY_TARGET:-docker}")}")
        MONGODB_PORT=$(deploy_prompt "MongoDB port" "${MONGODB_PORT:-27017}")
        MONGODB_DATABASE=$(deploy_prompt "MongoDB database" "${MONGODB_DATABASE:-xflickr}")

        if deploy_prompt_yes_no "Does MongoDB require authentication?" "$mongodb_auth_default"; then
            MONGODB_REQUIRES_AUTH="y"
            MONGODB_USERNAME=$(deploy_prompt "MongoDB username" "${MONGODB_USERNAME:-}")
            MONGODB_PASSWORD=$(deploy_prompt_secret_optional "MongoDB password" "${MONGODB_PASSWORD:-}")
        else
            MONGODB_REQUIRES_AUTH="n"
            MONGODB_USERNAME=""
            MONGODB_PASSWORD=""
        fi

        MONGODB_URI=$(deploy_build_mongodb_uri \
            "$MONGODB_HOST" "$MONGODB_PORT" "$MONGODB_DATABASE" \
            "$MONGODB_USERNAME" "$MONGODB_PASSWORD")

        deploy_wizard_state_save

        echo "Testing MongoDB connection..."
        if deploy_test_mongodb "$MONGODB_URI" "$MONGODB_HOST"; then
            echo "✓ MongoDB connected"
            export MONGODB_HOST MONGODB_PORT MONGODB_DATABASE MONGODB_URI
            export MONGODB_USERNAME MONGODB_PASSWORD MONGODB_REQUIRES_AUTH
            deploy_wizard_step_done mongodb
            return 0
        fi

        echo "MongoDB connection failed. Please correct the values and try again."
    done
}

deploy_wizard_step_admin() {
    ADMIN_EMAIL=$(deploy_prompt "Admin email" "${ADMIN_EMAIL:-admin@local}")
    while [[ -z "${ADMIN_PASSWORD:-}" ]]; do
        ADMIN_PASSWORD=$(deploy_prompt_secret_optional "Admin password (required)" "${ADMIN_PASSWORD:-}")
        if [[ -z "$ADMIN_PASSWORD" ]]; then
            echo "Admin password cannot be empty."
        fi
    done

    export ADMIN_EMAIL ADMIN_PASSWORD
    deploy_wizard_step_done admin
}

deploy_wizard_step_ssl() {
    local ssl_default="n"

    SSL_ENABLED="${SSL_ENABLED:-false}"
    SSL_CERT_PATH="${SSL_CERT_PATH:-./docker/nginx/ssl-empty}"

    if [[ "$SSL_ENABLED" == "true" ]]; then
        ssl_default="y"
    fi

    if deploy_prompt_yes_no "Enable HTTPS?" "$ssl_default"; then
        SSL_ENABLED=true
        if deploy_prompt_yes_no "Generate a self-signed certificate?" "y"; then
            local cn cert_dir
            cn=$(deploy_prompt "Certificate common name (IP or hostname)" "$(echo "$APP_URL" | sed -E 's#https?://([^/:]+).*#\1#')")
            cert_dir="${DEPLOY_WIZARD_ROOT}/storage/prod-ssl"
            deploy_generate_self_signed_cert "$cert_dir" "$cn"
            SSL_CERT_PATH="$cert_dir"
        else
            SSL_CERT_PATH=$(deploy_prompt "Path to directory containing cert.pem and key.pem" "${SSL_CERT_PATH:-./docker/nginx/ssl-empty}")
            if [[ ! -f "${SSL_CERT_PATH}/cert.pem" || ! -f "${SSL_CERT_PATH}/key.pem" ]]; then
                echo "ERROR: cert.pem and key.pem not found in ${SSL_CERT_PATH}" >&2
                return 1
            fi
        fi
    else
        SSL_ENABLED=false
        SSL_CERT_PATH="./docker/nginx/ssl-empty"
    fi

    export SSL_ENABLED SSL_CERT_PATH
    deploy_wizard_step_done ssl
}

deploy_wizard_step_horizon() {
    HORIZON_REPLICAS=$(deploy_prompt "Horizon container replicas" "${HORIZON_REPLICAS:-1}")
    export HORIZON_REPLICAS
    deploy_wizard_step_done horizon
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

DEPLOY_TARGET=${DEPLOY_TARGET:-docker}

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
    deploy_wizard_state_clear "$root"
    echo "Wrote ${env_file}"
}

deploy_wizard_run() {
    local root="$1"
    local mode="${2:-install}"

    DEPLOY_WIZARD_ROOT="$root"

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
    elif [[ "$mode" == "install" ]]; then
        deploy_wizard_state_load "$root"
    fi

    if [[ "$mode" == "install" ]] && ! deploy_wizard_step_is_done target; then
        deploy_wizard_step_target
    elif [[ -n "${DEPLOY_TARGET:-}" ]]; then
        echo "✓ Deploy target: ${DEPLOY_TARGET}"
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

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done general; then
        deploy_wizard_step_general
        if [[ "$mode" == "install" ]]; then
            deploy_wizard_step_done general
        fi
    else
        APP_URL=$(deploy_normalize_app_url "${APP_URL}")
        FLICKR_CALLBACK_URL="${APP_URL%/}/flickr/callback"
        echo "✓ General settings (saved)"
    fi

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done mysql; then
        deploy_wizard_prompt_mysql
    else
        echo "✓ MySQL (saved)"
    fi

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done redis; then
        deploy_wizard_prompt_redis
    else
        echo "✓ Redis (saved)"
    fi

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done mongodb; then
        deploy_wizard_prompt_mongodb
    else
        echo "✓ MongoDB (saved)"
    fi

    if ! deploy_test_all_services; then
        echo "ERROR: Final connectivity check failed." >&2
        return 1
    fi

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done admin; then
        deploy_wizard_step_admin
    else
        echo "✓ Admin account (saved)"
    fi

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done ssl; then
        deploy_wizard_step_ssl || return 1
    else
        echo "✓ HTTPS settings (saved)"
    fi

    if [[ "$mode" == "configure" ]] || ! deploy_wizard_step_is_done horizon; then
        deploy_wizard_step_horizon
    else
        echo "✓ Horizon replicas (saved)"
    fi

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
            cert_dir="${DEPLOY_WIZARD_ROOT}/storage/prod-ssl"
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
