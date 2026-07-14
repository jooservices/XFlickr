<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Architecture;

use Modules\Crawler\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * Guard: Services / Console commands under Modules/Crawler must not call Eloquent directly.
 * Jobs may resolve Repositories; AbstractXFlickrCrawlJob + trait must not use Model::query().
 */
final class CrawlerLayeringTest extends TestCase
{
    private const array FORBIDDEN_PATTERNS = [
        '/\\\\?[A-Za-z0-9_]+::query\s*\(/',
        '/\\\\?[A-Za-z0-9_]+::create\s*\(/',
        '/\\\\?[A-Za-z0-9_]+::updateOrCreate\s*\(/',
        '/\\\\?[A-Za-z0-9_]+::firstOrCreate\s*\(/',
    ];

    #[Test]
    public function crawler_services_do_not_call_eloquent_directly(): void
    {
        $violations = $this->scan(base_path('Modules/Crawler/app/Services'));

        $this->assertSame(
            [],
            $violations,
            "Crawler Services must use Repositories only. Offenders:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function crawler_console_commands_do_not_call_eloquent_directly(): void
    {
        $violations = $this->scan(base_path('Modules/Crawler/app/Console'));

        $this->assertSame(
            [],
            $violations,
            "Crawler Commands must call Services (not Eloquent). Offenders:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function crawler_jobs_do_not_call_model_query_static(): void
    {
        $violations = $this->scan(base_path('Modules/Crawler/app/Jobs'));

        $this->assertSame(
            [],
            $violations,
            "Crawler Jobs must not use Model::query()/create()/updateOrCreate(). Offenders:\n".implode("\n", $violations),
        );
    }

    /**
     * @return list<string>
     */
    private function scan(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $violations = [];
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)),
            '/\.php$/',
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            $contents = (string) file_get_contents($path);

            // Allow DB::transaction wrappers in catalog service — but ban table Eloquent stubs.
            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).' matches '.$pattern;

                    break;
                }
            }
        }

        return $violations;
    }
}
