#!/usr/bin/env bash
# Safe test runner — uses docker-compose.test.yml ONLY (never touches dev MySQL).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if docker compose ps --format '{{.Service}}' 2>/dev/null | grep -qx 'app'; then
    echo "Note: local dev stack is running — tests will use isolated docker-compose.test.yml (dev MySQL is not used)." >&2
fi

exec docker compose -f docker-compose.test.yml run --rm test php artisan test "$@"
