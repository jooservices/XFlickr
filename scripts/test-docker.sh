#!/usr/bin/env bash
# Safe test runner — uses docker-compose.test.yml ONLY (never touches dev MySQL).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/compose-test.sh"

if docker compose -f docker-compose.dev.yml -p xflickr-dev ps --format '{{.Service}}' 2>/dev/null | grep -qx 'app'; then
    echo "Note: dev stack is running — tests use isolated docker-compose.test.yml (dev MySQL is not used)." >&2
fi

xf_test_compose run --rm test php artisan test "$@"
