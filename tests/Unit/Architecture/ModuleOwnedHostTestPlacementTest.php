<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

/**
 * Host tests/ must not accumulate module-owned suites after A8 migration.
 */
final class ModuleOwnedHostTestPlacementTest extends TestCase
{
    /**
     * Host paths permitted to reference Modules deeply (glue, cross-cutting).
     *
     * @var list<string>
     */
    private const ALLOWED_RELATIVE_PREFIXES = [
        'tests/Unit/Architecture/',
        'tests/Unit/Repositories/',
        'tests/Unit/Support/',
        'tests/Unit/Dto/',
        'tests/Unit/Http/',
        'tests/Unit/Models/',
        'tests/Feature/Middleware/',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_RELATIVE_FILES = [
        'tests/Feature/RepositoryServiceProviderTest.php',
    ];

    /**
     * Known module-owned basenames that must not live under host tests/.
     *
     * @var list<string>
     */
    private const BANNED_HOST_TEST_BASENAMES = [
        'FlickrPhotoUrlHelperTest.php',
        'FlickrTokenHealthServiceTest.php',
        'StorageR2ConfigTest.php',
        'FlickrAccountConnectedEventTest.php',
        'XFlickrCrawlConfigTest.php',
        'FlickrContactFrontendSupportTest.php',
        'SpiderRuntimeConfigTest.php',
        'ContactGraphRuntimeConfigTest.php',
    ];

    /**
     * @var list<string>
     */
    private const MODULE_NAMES = [
        'Auth',
        'Catalog',
        'Contacts',
        'Crawler',
        'Flickr',
        'Operations',
        'Settings',
        'Spider',
        'Storage',
        'Transfer',
    ];

    #[Test]
    public function host_tests_do_not_retain_module_owned_leftovers(): void
    {
        $violations = [];

        foreach ($this->hostTestFiles() as $file) {
            $relative = $this->relativePath($file);

            if ($this->isAllowedHostTest($relative)) {
                continue;
            }

            if (in_array(basename($relative), self::BANNED_HOST_TEST_BASENAMES, true)) {
                $violations[] = "{$relative} is a known module-owned test and must live under Modules/*/tests/";

                continue;
            }

            $soleModule = $this->soleModuleUnderTest($relative);

            if ($soleModule !== null) {
                $violations[] = "{$relative} appears to test only Modules\\{$soleModule}\\* — move to Modules/{$soleModule}/tests/";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Host tests must not own module-only suites:\n".implode("\n", $violations),
        );
    }

    private function isAllowedHostTest(string $relative): bool
    {
        if (in_array($relative, self::ALLOWED_RELATIVE_FILES, true)) {
            return true;
        }

        foreach (self::ALLOWED_RELATIVE_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function soleModuleUnderTest(string $relative): ?string
    {
        $contents = (string) file_get_contents(base_path($relative));

        if (! preg_match_all('/^use\s+Modules\\\\([^\\\\]+)\\\\/m', $contents, $matches)) {
            return null;
        }

        $modules = array_values(array_unique($matches[1]));

        if (count($modules) !== 1) {
            return null;
        }

        $module = $modules[0];

        if (! in_array($module, self::MODULE_NAMES, true)) {
            return null;
        }

        if (preg_match('/^use\s+App\\\\/m', $contents)) {
            return null;
        }

        if (preg_match('/^use\s+Tests\\\\Concerns\\\\SafeRefreshDatabase;/m', $contents)
            && preg_match('/^use\s+Tests\\\\Support\\\\CreatesFlickrConnection;/m', $contents)) {
            return null;
        }

        return $module;
    }

    /**
     * @return list<string>
     */
    private function hostTestFiles(): array
    {
        $files = [];

        foreach (['tests/Unit', 'tests/Feature'] as $root) {
            $absolute = base_path($root);

            if (! is_dir($absolute)) {
                continue;
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute)),
                '/Test\.php$/',
            );

            foreach ($iterator as $file) {
                $files[] = $this->relativePath($file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $absolute): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $absolute);
    }
}
