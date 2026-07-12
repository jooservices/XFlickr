#!/usr/bin/env sh
# Sync node_modules when package-lock.json changes, then start Vite (dev frontend container).
set -eu

cd /var/www/html

sh scripts/docker-npm-sync.sh

exec npm run dev -- --host 0.0.0.0 --port 5173
