#!/usr/bin/env bash
# Backward-compatible alias for Docker verification.
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/verify-docker.sh"
