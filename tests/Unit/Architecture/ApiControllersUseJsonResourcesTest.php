<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class ApiControllersUseJsonResourcesTest extends TestCase
{
    #[Test]
    public function json_api_success_returns_use_json_resources(): void
    {
        $paths = [
            ...$this->phpFiles(app_path('Http/Controllers/Api')),
            ...$this->phpFiles(base_path('Modules')),
        ];

        $violations = [];

        foreach ($paths as $path) {
            if (! str_contains($path, '/Http/Controllers/') || ! str_ends_with($path, 'Controller.php')) {
                continue;
            }

            if (! str_contains($path, '/Api/')) {
                continue;
            }

            $contents = file_get_contents($path);
            if (! is_string($contents)) {
                continue;
            }

            if (! str_contains($contents, 'Http\\Resources\\') && ! preg_match('/use .+\\\\Http\\\\Resources\\\\/', $contents)) {
                // Stream-only controllers (no JSON success payload) are allowed.
                if (! preg_match('/\$this->(?:success|accepted|created)\s*\(/', $contents)) {
                    continue;
                }

                $violations[] = str_replace(base_path().'/', '', $path);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "API controllers that return success/accepted/created must import Json Resources:\n".implode("\n", $violations),
        );
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getPathname(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
