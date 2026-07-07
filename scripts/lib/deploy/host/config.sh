#!/usr/bin/env bash
# Render and install host nginx + supervisor configuration.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/install-prereqs.sh"

DEPLOY_HOST_APP_PORT="${DEPLOY_HOST_APP_PORT:-8000}"
DEPLOY_HOST_NGINX_SITE="/etc/nginx/sites-available/xflickr"
DEPLOY_HOST_NGINX_ENABLED="/etc/nginx/sites-enabled/xflickr"
DEPLOY_HOST_SUPERVISOR_CONF="/etc/supervisor/conf.d/xflickr.conf"
DEPLOY_HOST_LOG_DIR="/var/log/xflickr"

deploy_host_render_ssl_block() {
    local ssl_enabled="${SSL_ENABLED:-false}"
    local ssl_path="${SSL_CERT_PATH:-}"
    local https_port="${HTTPS_PORT:-443}"

    if [[ "$ssl_enabled" != "true" ]]; then
        echo ""
        return 0
    fi

    if [[ ! -f "${ssl_path}/cert.pem" || ! -f "${ssl_path}/key.pem" ]]; then
        echo "WARNING: SSL enabled but cert.pem/key.pem not found in ${ssl_path}" >&2
        echo ""
        return 0
    fi

    cat <<EOF

server {
    listen ${https_port} ssl;
    server_name _;

    ssl_certificate ${ssl_path}/cert.pem;
    ssl_certificate_key ${ssl_path}/key.pem;

    client_max_body_size 100M;

    location / {
        proxy_pass http://xflickr_app;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300s;
    }
}
EOF
}

deploy_host_render_nginx_site() {
    local root="$1"
    local template http_port output
    template="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/nginx-site.conf.template"
    http_port="${HTTP_PORT:-80}"

    output="$(sed -e "s|__APP_PORT__|${DEPLOY_HOST_APP_PORT}|g" \
        -e "s|__HTTP_PORT__|${http_port}|g" "$template")"

    if [[ "${SSL_ENABLED:-false}" == "true" ]]; then
        output="${output}$(deploy_host_render_ssl_block)"
    fi

    printf '%s' "$output"
}

deploy_host_render_supervisor_conf() {
    local root="$1"
    local template replicas log_dir
    template="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/supervisor.conf.template"
    replicas="${HORIZON_REPLICAS:-1}"
    log_dir="${DEPLOY_HOST_LOG_DIR}"

    sed -e "s|__PROJECT_ROOT__|${root}|g" \
        -e "s|__APP_PORT__|${DEPLOY_HOST_APP_PORT}|g" \
        -e "s|__HORIZON_REPLICAS__|${replicas}|g" \
        -e "s|__LOG_DIR__|${log_dir}|g" "$template"
}

deploy_host_install_configs() {
    local root="$1"

    deploy_host_sudo mkdir -p "${DEPLOY_HOST_LOG_DIR}"
    deploy_host_sudo chown www-data:www-data "${DEPLOY_HOST_LOG_DIR}"

    deploy_host_render_nginx_site "$root" | deploy_host_sudo tee "${DEPLOY_HOST_NGINX_SITE}" >/dev/null
    deploy_host_sudo ln -sf "${DEPLOY_HOST_NGINX_SITE}" "${DEPLOY_HOST_NGINX_ENABLED}"
    deploy_host_sudo rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

    deploy_host_render_supervisor_conf "$root" | deploy_host_sudo tee "${DEPLOY_HOST_SUPERVISOR_CONF}" >/dev/null

    deploy_host_sudo nginx -t
}

deploy_host_print_urls() {
    local port app_url
    port="${HTTP_PORT:-80}"
    app_url="${APP_URL:-http://127.0.0.1:${port}}"

    echo
    echo "XFlickr production (host) is running:"
    echo "  App:      ${app_url}"
    echo "  Login:    ${app_url}/login"
    echo "  Horizon:  ${app_url}/horizon"
    echo "  Settings: ${app_url}/settings"
    echo
    echo "  HTTP port:         ${port}"
    echo "  Horizon replicas:  ${HORIZON_REPLICAS:-1}"
    echo "  Flickr callback:   ${FLICKR_CALLBACK_URL:-${app_url}/flickr/callback}"
    echo
    echo "  Update:  bash scripts/deploy.sh update"
    echo "  Verify:  bash scripts/deploy.sh verify"
    echo
}
