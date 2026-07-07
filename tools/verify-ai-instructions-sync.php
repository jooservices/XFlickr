<?php

declare(strict_types=1);

/**
 * Verifies that critical AI-facing policy invariants remain visible across
 * XFlickr instruction adapters. Focused on policy drift, not prose style.
 */
$root = dirname(__DIR__);

$files = [
    'AGENTS.md',
    'CLAUDE.md',
    '.claude/commands/quality-check.md',
    'ai/README.md',
    '.cursor/rules/00-repo-quality-foundation.mdc',
    '.cursor/rules/docker-dev-forbidden.mdc',
    '.github/copilot-instructions.md',
    '.github/skills/repo-quality-foundation/SKILL.md',
    '.github/skills/xflickr-docker-testing/SKILL.md',
    '.github/skills/operator-dev-docker/SKILL.md',
    'ai/skills/repo-quality-foundation/SKILL.md',
    'ai/skills/xflickr-docker-testing/SKILL.md',
    'ai/skills/operator-dev-docker/SKILL.md',
    'ai/skills/form-request-service-repository/SKILL.md',
];

$missingFiles = [];
$fileContents = [];
$corpus = '';
$failures = [];

foreach ($files as $file) {
    $path = $root.'/'.$file;

    if (! is_file($path)) {
        $missingFiles[] = $file;

        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        $failures[] = 'Unreadable instruction file: '.$file;

        continue;
    }

    $fileContents[$file] = $content;
    $corpus .= "\n\n--- {$file} ---\n".$content;
}

$globalRequiredPatterns = [
    'scripts test sh gate' => '/scripts\/test\.sh/i',
    'docker compose dev yml' => '/docker-compose\.dev\.yml/i',
    'docker compose test yml test stack' => '/docker-compose\.test\.yml/i',
    'forbidden docker exec xflickr dev' => '/docker exec xflickr-dev/i',
    'operator dev docker skill' => '/operator-dev-docker/i',
    'form request service repository flow' => '/FormRequest.*Service.*Repository/is',
    'viet vu commit authorship' => '/Viet Vu <jooservices@gmail.com>/i',
    'no ai co-authored commits' => '/Co-authored-by/i',
    'manual crawl only or no auto-spider' => '/manual crawl|auto-spider/i',
    'canonical ai skills path' => '/ai\/skills\//i',
];

$canonicalSkillStubs = [
    'repo-quality-foundation',
    'architecture-and-design-principles',
    'class-purpose-and-module-map',
    'code-style-and-conventions',
    'review-and-risk-assessment',
    'documentation-sync',
    'multi-llm-plan-review',
    'xflickr-docker-testing',
    'docker-dev-stack-safety',
    'operator-dev-docker',
    'database-migration-safety',
    'security-hardening',
    'form-request-service-repository',
    'crawler-pipeline-integrity',
    'transfer-pipeline-safety',
    'storage-driver-safety',
    'queue-horizon-operations',
    'api-response-standards',
    'react-inertia-frontend',
    'testing-and-quality-gates',
    'release-and-deploy-flow',
];

foreach ($canonicalSkillStubs as $skill) {
    $canonical = "ai/skills/{$skill}/SKILL.md";
    $stub = ".github/skills/{$skill}/SKILL.md";

    if (! is_file($root.'/'.$canonical)) {
        $failures[] = 'Missing canonical skill: '.$canonical;
    }

    if (! is_file($root.'/'.$stub)) {
        $failures[] = 'Missing Copilot stub: '.$stub;

        continue;
    }

    $stubContent = file_get_contents($root.'/'.$stub);
    if ($stubContent === false || ! str_contains($stubContent, "ai/skills/{$skill}/SKILL.md")) {
        $failures[] = "Stub {$stub} does not link to canonical ai/skills/{$skill}/SKILL.md";
    }
}

foreach ($globalRequiredPatterns as $label => $pattern) {
    if (preg_match($pattern, $corpus) !== 1) {
        $failures[] = 'Missing invariant: '.$label;
    }
}

foreach ($missingFiles as $file) {
    $failures[] = 'Missing instruction file: '.$file;
}

if ($failures !== []) {
    fwrite(STDERR, "AI instruction sync verification failed:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }

    exit(1);
}

echo "AI instruction sync verification passed.\n";
