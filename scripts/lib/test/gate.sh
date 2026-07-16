#!/usr/bin/env bash
# XFlickr quality gates — lint, test, CI parity.
set -u
set -o pipefail

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/common.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose-test.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/prereqs.sh"
# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/deploy-gate.sh"

TEST_GATE_FAILED=0
TEST_COVERAGE_MIN="${TEST_COVERAGE_MIN:-95}"

test_gate_run_step() {
    local name="$1"
    shift
    printf '\n==> %s\n' "$name"
    if "$@"; then
        printf '[PASS] %s\n' "$name"
    else
        printf '[FAIL] %s\n' "$name"
        TEST_GATE_FAILED=1
    fi
}

test_gate_lint() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    TEST_GATE_FAILED=0

    printf 'XFlickr lint gate\n'

    test_gate_run_step "Host toolchain" test_prereqs_host_toolchain
    test_gate_run_step "Composer validate" composer validate --strict
    test_gate_run_step "Pint" composer lint:pint
    test_gate_run_step "PHPCS" composer lint:phpcs
    test_gate_run_step "PHPStan" composer lint:phpstan
    test_gate_run_step "PHPMD" composer lint:phpmd
    test_gate_run_step "Deptrac" composer lint:deptrac
    test_gate_run_step "AI instructions sync" composer instructions:verify
    test_gate_run_step "Frontend typecheck" npm run typecheck
    test_gate_run_step "Frontend ESLint" npm run lint

    if [[ "$TEST_GATE_FAILED" -ne 0 ]]; then
        printf '\nLint gate FAILED\n'
        return 1
    fi

    printf '\nLint gate PASSED\n'
    return 0
}

test_gate_security() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    TEST_GATE_FAILED=0

    printf 'XFlickr security gate\n'

    test_gate_run_step "Composer audit" composer audit --locked --abandoned=ignore

    if [[ "$TEST_GATE_FAILED" -ne 0 ]]; then
        printf '\nSecurity gate FAILED\n'
        return 1
    fi

    printf '\nSecurity gate PASSED\n'
    return 0
}

test_run_phpunit_docker() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    bash scripts/test-docker.sh "$@"
}

test_run_phpunit_docker_coverage() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    xf_test_compose run --rm test php -d memory_limit=512M artisan test --coverage --coverage-clover=coverage.xml
}

test_run_frontend_vitest() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    npm run test:coverage
}

test_gate_test() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    TEST_GATE_FAILED=0

    printf 'XFlickr test gate\n'

    test_gate_lint || return 1
    TEST_GATE_FAILED=0

    test_gate_run_step "Host toolchain" test_prereqs_host_toolchain
    test_gate_run_step "Docker available" test_require_docker
    test_gate_run_step "Frontend build" npm run build
    test_gate_run_step "PHPUnit (Docker test stack)" test_run_phpunit_docker
    test_gate_run_step "Vitest" test_run_frontend_vitest

    if [[ "$TEST_GATE_FAILED" -ne 0 ]]; then
        printf '\nTest gate FAILED\n'
        return 1
    fi

    printf '\nTest gate PASSED\n'
    return 0
}

test_gate_coverage() {
    local root line_pct
    root="$(xf_script_root)"
    cd "$root"
    TEST_GATE_FAILED=0

    printf 'XFlickr coverage gate (min %s%%)\n' "$TEST_COVERAGE_MIN"

    test_gate_run_step "Docker available" test_require_docker
    test_gate_run_step "Frontend build" npm run build
    test_gate_run_step "PHPUnit coverage (Docker test stack)" test_run_phpunit_docker_coverage

    if [[ -f "${root}/coverage.xml" ]]; then
        line_pct="$(
            php -r '
                $xml = simplexml_load_file("coverage.xml");
                if ($xml === false) { echo "0"; exit(1); }
                $metrics = $xml->project->metrics ?? null;
                if ($metrics === null) { echo "0"; exit(1); }
                $covered = (float) $metrics["coveredstatements"];
                $total = (float) $metrics["statements"];
                if ($total <= 0) { echo "0"; exit(1); }
                echo round(($covered / $total) * 100, 2);
            ' 2>/dev/null || echo "0"
        )"
        printf 'Line coverage: %s%% (required >= %s%%)\n' "$line_pct" "$TEST_COVERAGE_MIN"
        if awk -v pct="$line_pct" -v min="$TEST_COVERAGE_MIN" 'BEGIN { exit (pct + 0 >= min + 0) ? 0 : 1 }'; then
            printf '[PASS] Coverage threshold\n'
        else
            printf '[FAIL] Coverage below %s%%\n' "$TEST_COVERAGE_MIN"
            TEST_GATE_FAILED=1
        fi

        zero_files="$(
            php -r '
                $xml = simplexml_load_file("coverage.xml");
                if ($xml === false) { exit(1); }
                $zeros = [];
                foreach ($xml->xpath("//file") ?: [] as $file) {
                    $metrics = $file->metrics ?? null;
                    if ($metrics === null) { continue; }
                    $statements = (int) $metrics["statements"];
                    $covered = (int) $metrics["coveredstatements"];
                    if ($statements > 0 && $covered === 0) {
                        $zeros[] = (string) $file["name"];
                    }
                }
                echo implode("\n", $zeros);
            ' 2>/dev/null || true
        )"
        if [[ -n "${zero_files}" ]]; then
            printf '[FAIL] Zero-covered files:\n%s\n' "${zero_files}"
            TEST_GATE_FAILED=1
        else
            printf '[PASS] No zero-covered files\n'
        fi
    else
        printf '[FAIL] coverage.xml missing\n'
        TEST_GATE_FAILED=1
    fi

    if [[ "$TEST_GATE_FAILED" -ne 0 ]]; then
        printf '\nCoverage gate FAILED\n'
        return 1
    fi

    printf '\nCoverage gate PASSED\n'
    return 0
}

test_gate_ci() {
    local root
    root="$(xf_script_root)"
    cd "$root"
    TEST_GATE_FAILED=0

    printf 'XFlickr CI gate (GitHub Actions parity)\n'

    test_gate_run_step "Host toolchain" test_prereqs_host_toolchain || return 1
    test_gate_run_step "Security" test_gate_security
    test_gate_run_step "Lint" test_gate_lint
    test_gate_run_step "Frontend build + Vitest" bash -c "npm ci && npm run typecheck && npm run lint && npm run test && npm run build"
    test_gate_run_step "Coverage" test_gate_coverage
    test_gate_run_step "Deploy scripts" test_gate_deploy_scripts

    if [[ "$TEST_GATE_FAILED" -ne 0 ]]; then
        printf '\nCI gate FAILED\n'
        return 1
    fi

    printf '\nCI gate PASSED\n'
    return 0
}
