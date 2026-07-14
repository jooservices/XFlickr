<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

final class FlickrClientFactoryLayeringTest extends TestCase
{
    /** @var list<string> */
    private const FLICKR_FACTORY_ALLOWLIST = [
        'Modules/Crawler/app/Services/FlickrClientFactory.php',
        'Modules/Flickr/app/Services/FlickrOAuthService.php',
    ];

    /** @var list<string> */
    private const CLIENT_FACTORY_IMPORT_MODULES = [
        'Crawler',
        'Flickr',
    ];

    #[Test]
    public function flickr_factory_may_only_be_used_in_approved_paths(): void
    {
        $allowlist = array_map(
            static fn (string $relative): string => base_path($relative),
            self::FLICKR_FACTORY_ALLOWLIST,
        );

        $violations = [];

        foreach ($this->moduleAppPhpFiles() as $file) {
            if (in_array($file, $allowlist, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            if (
                preg_match('/use\s+JOOservices\\\\Flickr\\\\FlickrFactory\s*;/', $contents) === 1
                || preg_match('/\bFlickrFactory::make\s*\(/', $contents) === 1
            ) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "FlickrFactory may only be used in Crawler FlickrClientFactory and Flickr OAuth. Offenders:\n"
            .implode("\n", $violations),
        );
    }

    #[Test]
    public function peer_modules_must_not_import_flickr_client_factory(): void
    {
        $violations = [];

        foreach ($this->moduleAppPhpFiles() as $file) {
            $module = $this->moduleNameFromPath($file);
            if ($module === null || in_array($module, self::CLIENT_FACTORY_IMPORT_MODULES, true)) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            if (preg_match('/use\s+Modules\\\\Crawler\\\\Services\\\\FlickrClientFactory\s*;/', $contents) === 1) {
                $violations[] = $this->relativePath($file);
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Only Crawler and Flickr may import FlickrClientFactory in app/. Offenders:\n"
            .implode("\n", $violations),
        );
    }

    /**
     * @return list<string>
     */
    private function moduleAppPhpFiles(): array
    {
        $files = [];
        $modulesRoot = base_path('Modules');

        foreach (scandir($modulesRoot) ?: [] as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $appPath = $modulesRoot.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'app';
            if (! is_dir($appPath)) {
                continue;
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath)),
                '/\.php$/',
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function moduleNameFromPath(string $path): ?string
    {
        $modulesRoot = base_path('Modules').DIRECTORY_SEPARATOR;
        if (! str_starts_with($path, $modulesRoot)) {
            return null;
        }

        $remainder = substr($path, strlen($modulesRoot));
        $parts = explode(DIRECTORY_SEPARATOR, $remainder);

        return $parts[0] ?? null;
    }

    private function relativePath(string $absolutePath): string
    {
        return ltrim(str_replace(base_path(), '', $absolutePath), DIRECTORY_SEPARATOR);
    }
}
