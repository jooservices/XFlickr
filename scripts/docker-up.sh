#!/usr/bin/env bash
# Backward-compatible entry — use scripts/dev.sh for more commands.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec bash "${ROOT}/scripts/dev.sh" up "$@"
