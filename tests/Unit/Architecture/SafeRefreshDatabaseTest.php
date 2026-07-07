<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('architecture')]
class SafeRefreshDatabaseTest extends TestCase
{
    public function test_forbids_raw_illuminate_refresh_database_in_feature_tests(): void
    {
        $testsRoot = base_path('tests');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($testsRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            if (str_contains($path, 'SafeRefreshDatabase.php') || str_contains($path, 'RefreshDatabaseGuard.php')) {
                continue;
            }

            if (! str_contains($path, DIRECTORY_SEPARATOR.'Feature'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, 'Illuminate\\Foundation\\Testing\\RefreshDatabase')) {
                $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Use Tests\\Concerns\\SafeRefreshDatabase instead of Illuminate RefreshDatabase:'."\n".implode("\n", $violations)
        );
    }
}
