#!/usr/bin/env bash
# Playwright / demo-dataset prep instructions (operator-owned dev stack).
# AI must NOT seed the operator MySQL volume — use `bash scripts/dev.sh seed --demo`.
set -u
set -o pipefail

xf_demo_seed_instructions() {
    cat <<EOF
Playwright smoke tests target the operator dev stack (default http://localhost:8082).

Prep (operator — AI must not run these):
  1. bash scripts/dev.sh seed --demo     # admin user + demo Flickr/storage/transfer data
  2. npm run build                       # or bash scripts/dev.sh reload
  3. ADMIN_PASSWORD=<from .env> npm run test:e2e

The isolated test stack (bash scripts/test.sh up) has no HTTP app service and uses
SQLite :memory: for PHPUnit — use the dev stack for browser E2E, not test.sh up.

Re-seed demo data after a schema wipe:
  bash scripts/dev.sh refresh && bash scripts/dev.sh seed --demo

Instructions only: bash scripts/test.sh e2e:prep
EOF
}
