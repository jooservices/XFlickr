#!/usr/bin/env bash

ARTISAN_LOG="$(mktemp)"
trap 'rm -f "$ARTISAN_LOG"' EXIT

php() {
    if [[ "${1:-}" == "artisan" ]]; then
        shift
        printf '%s\n' "$*" >>"$ARTISAN_LOG"
        return 0
    fi
    command php "$@"
}

export -f php
export ARTISAN_LOG
export DEPLOY_ARTISAN_MODE=host
export DEPLOY_TARGET=host

pgrep() { return 0; }
export -f pgrep

# shellcheck disable=SC1091
source "${ROOT}/scripts/lib/deploy/app-release.sh"

deploy_app_migrate
deploy_app_optimize_refresh
deploy_app_graceful_worker_shutdown

log="$(cat "$ARTISAN_LOG")"
deploy_test_assert_contains "$log" "migrate --force" "migrate runs"
deploy_test_assert_contains "$log" "config:clear" "config clear before cache"
deploy_test_assert_contains "$log" "optimize:clear" "optimize clear"
deploy_test_assert_contains "$log" "config:cache" "config cache after clear"
deploy_test_assert_contains "$log" "horizon:terminate" "horizon terminate"

first_clear="$(grep -n 'config:clear' "$ARTISAN_LOG" | head -1 | cut -d: -f1)"
first_cache="$(grep -n 'config:cache' "$ARTISAN_LOG" | head -1 | cut -d: -f1)"
if [[ "$first_clear" -lt "$first_cache" ]]; then
    deploy_test_assert_eq "ok" "ok" "clear happens before cache"
else
    deploy_test_assert_eq "ok" "bad" "clear happens before cache"
fi
