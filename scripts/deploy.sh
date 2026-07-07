#!/usr/bin/env bash
# DEPRECATED — use: bash scripts/dev.sh reload
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "NOTE: scripts/deploy.sh is deprecated. Use: bash scripts/dev.sh reload" >&2
exec bash "${ROOT}/scripts/dev.sh" reload
