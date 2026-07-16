#!/usr/bin/env bash
# Self-signed TLS cert for IP-only production installs.
set -euo pipefail

deploy_generate_self_signed_cert() {
    local cert_dir="$1"
    local common_name="$2"
    local days="${3:-825}"
    local san_type="DNS"

    mkdir -p "${cert_dir}"

    if ! command -v openssl >/dev/null 2>&1; then
        echo "ERROR: openssl is required to generate a self-signed certificate." >&2
        return 1
    fi

    if [[ "$common_name" =~ ^[0-9a-fA-F:.]+$ ]]; then
        san_type="IP"
    fi

    openssl req -x509 -nodes -newkey rsa:2048 \
        -keyout "${cert_dir}/key.pem" \
        -out "${cert_dir}/cert.pem" \
        -days "${days}" \
        -subj "/CN=${common_name}" \
        -addext "subjectAltName=${san_type}:${common_name}" \
        >/dev/null 2>&1 || return 1

    chmod 600 "${cert_dir}/key.pem"
    chmod 644 "${cert_dir}/cert.pem"

    echo "${cert_dir}"
}
