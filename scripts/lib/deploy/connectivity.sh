#!/usr/bin/env bash
# Connectivity checks for production deploy wizard (Docker network context).
set -u
set -o pipefail

DEPLOY_CHECK_NETWORK="${DEPLOY_CHECK_NETWORK:-xflickr-prod-connectivity}"

deploy_ensure_check_network() {
    if ! docker network inspect "${DEPLOY_CHECK_NETWORK}" >/dev/null 2>&1; then
        docker network create "${DEPLOY_CHECK_NETWORK}" >/dev/null
    fi
}

deploy_docker_run_opts() {
    echo --rm --network "${DEPLOY_CHECK_NETWORK}" --add-host=host.docker.internal:host-gateway
}

deploy_same_host_hint() {
    local host="$1"

    if [[ "$host" == "localhost" || "$host" == "127.0.0.1" ]]; then
        cat <<'EOF'
Hint: localhost inside a container is the container itself, not your host.
  - macOS/Windows Docker Desktop: use host.docker.internal
  - Linux: use host.docker.internal or your Docker bridge gateway (often 172.17.0.1)
EOF
    fi
}

deploy_test_mysql() {
    local host="$1" port="$2" database="$3" username="$4" password="$5"
    local output

    deploy_ensure_check_network

    # shellcheck disable=SC2046
    if ! output=$(docker run $(deploy_docker_run_opts) -e MYSQL_PWD="${password}" mysql:9 \
        mysql -h"${host}" -P"${port}" -u"${username}" -D"${database}" -N -e 'SELECT 1' 2>&1); then
        deploy_same_host_hint "$host"
        echo "MySQL connection failed: ${output}" >&2
        return 1
    fi

    return 0
}

deploy_test_redis() {
    local host="$1" port="$2" password="$3"
    local output args=(-h "${host}" -p "${port}")

    deploy_ensure_check_network

    if [[ -n "$password" ]]; then
        args+=(-a "${password}")
    fi

    # shellcheck disable=SC2046
    if ! output=$(docker run $(deploy_docker_run_opts) redis:7-alpine \
        redis-cli "${args[@]}" ping 2>&1); then
        deploy_same_host_hint "$host"
        echo "Redis connection failed: ${output}" >&2
        return 1
    fi

    if [[ "$output" != "PONG" ]]; then
        deploy_same_host_hint "$host"
        echo "Redis connection failed: expected PONG, got: ${output}" >&2
        return 1
    fi

    return 0
}

deploy_urlencode() {
    local value="$1"

    if command -v python3 >/dev/null 2>&1; then
        python3 -c "import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1], safe=''))" "$value"
        return 0
    fi

    if command -v php >/dev/null 2>&1; then
        php -r 'echo rawurlencode($argv[1]);' "$value"
        return 0
    fi

    echo "$value"
}

deploy_build_mongodb_uri() {
    local host="$1" port="$2" database="$3" username="$4" password="$5"

    if [[ -n "$username" ]]; then
        local encoded_user encoded_pass
        encoded_user=$(deploy_urlencode "$username")
        encoded_pass=$(deploy_urlencode "$password")
        echo "mongodb://${encoded_user}:${encoded_pass}@${host}:${port}/${database}?authSource=admin"
    else
        echo "mongodb://${host}:${port}/${database}"
    fi
}

deploy_test_mongodb() {
    local uri="$1" host="$2"
    local output

    deploy_ensure_check_network

    # shellcheck disable=SC2046
    if ! output=$(docker run $(deploy_docker_run_opts) mongo:7 \
        mongosh "${uri}" --quiet --eval 'db.adminCommand({ping:1}).ok' 2>&1); then
        deploy_same_host_hint "$host"
        echo "MongoDB connection failed: ${output}" >&2
        return 1
    fi

    if [[ "$output" != "1" ]]; then
        deploy_same_host_hint "$host"
        echo "MongoDB connection failed: ping did not return ok" >&2
        return 1
    fi

    return 0
}

deploy_test_all_services() {
    local ok=0

    echo "==> Final connectivity check (MySQL, Redis, MongoDB)..."

    if deploy_test_mysql "${DB_HOST}" "${DB_PORT}" "${DB_DATABASE}" "${DB_USERNAME}" "${DB_PASSWORD}"; then
        echo "  ✓ MySQL"
    else
        ok=1
    fi

    if deploy_test_redis "${REDIS_HOST}" "${REDIS_PORT}" "${REDIS_PASSWORD:-}"; then
        echo "  ✓ Redis"
    else
        ok=1
    fi

    if deploy_test_mongodb "${MONGODB_URI}" "${MONGODB_HOST:-}"; then
        echo "  ✓ MongoDB"
    else
        ok=1
    fi

    return "$ok"
}
