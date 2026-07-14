<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

final class CrawlerModuleBoundariesTest extends TestCase
{
    #[Test]
    public function crawler_module_app_does_not_import_peer_modules(): void
    {
        $appPath = base_path('Modules/Crawler/app');
        $this->assertDirectoryExists($appPath);

        $violations = $this->filesMatching(
            $appPath,
            static function (string $contents): bool {
                // Own PSR-4 is Modules\Crawler\ — peer imports are other Modules\{Name}\.
                return preg_match('/use\s+Modules\\\\(?!Crawler\\\\)[A-Za-z]+\\\\/', $contents) === 1;
            },
        );

        $this->assertSame(
            [],
            $violations,
            "Modules/Crawler must be a leaf (no peer Modules\\* imports). Offenders:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function http_controllers_do_not_import_crawler_fetchers_or_jobs(): void
    {
        $violations = [];

        foreach ($this->moduleHttpPaths() as $httpPath) {
            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($httpPath)),
                '/\.php$/',
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $contents = (string) file_get_contents($file->getPathname());
                if (
                    preg_match('/use\s+Modules\\\\Crawler\\\\(Fetchers|Jobs)\\\\/', $contents) === 1
                ) {
                    $violations[] = $file->getPathname();
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Http controllers must not import crawler Fetchers/Jobs. Offenders:\n".implode("\n", $violations),
        );
    }

    /**
     * @param  callable(string): bool  $predicate
     * @return list<string>
     */
    private function filesMatching(string $directory, callable $predicate): array
    {
        $violations = [];
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)),
            '/\.php$/',
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $contents = (string) file_get_contents($file->getPathname());
            if ($predicate($contents)) {
                $violations[] = $file->getPathname();
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function moduleHttpPaths(): array
    {
        $paths = [];
        $modulesRoot = base_path('Modules');

        foreach (scandir($modulesRoot) ?: [] as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $httpPath = $modulesRoot.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http';
            if (is_dir($httpPath)) {
                $paths[] = $httpPath;
            }
        }

        return $paths;
    }
}
