#!/bin/sh
set -eu

HTTP_PORT="${HTTP_PORT:-80}"
HTTPS_PORT="${HTTPS_PORT:-443}"
SSL_ENABLED="${SSL_ENABLED:-false}"

cat > /etc/nginx/conf.d/default.conf <<EOF
upstream xflickr_app {
    server app:8000;
}

server {
    listen ${HTTP_PORT};
    server_name _;

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

if [ "$SSL_ENABLED" = "true" ] && [ -f /etc/nginx/ssl/cert.pem ] && [ -f /etc/nginx/ssl/key.pem ]; then
    cat >> /etc/nginx/conf.d/default.conf <<EOF

server {
    listen ${HTTPS_PORT} ssl;
    server_name _;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;

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
fi

exec nginx -g 'daemon off;'
