#!/usr/bin/env sh
# Sync node_modules when package-lock.json changes (dev app + frontend containers).
set -eu

cd /var/www/html

LOCK_STAMP="node_modules/.package-lock.stamp"

need_ci=0

if [ ! -d node_modules ] || [ ! -f "${LOCK_STAMP}" ] || ! cmp -s package-lock.json "${LOCK_STAMP}"; then
  need_ci=1
fi

if [ "${need_ci}" -eq 1 ]; then
  echo "Frontend dependencies changed — running npm ci…"
  npm ci
  cp package-lock.json "${LOCK_STAMP}"
fi
