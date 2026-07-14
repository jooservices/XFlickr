<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

/**
 * Guard: module Services under app/Services must not call Eloquent or domain-table DB::table directly.
 *
 * Allowed: DB::transaction; Operations DB introspection services (whitelisted below);
 * allowlisted static receivers (Str, Arr, Cache, …) for non-Eloquent helpers.
 */
final class ServiceLayeringTest extends TestCase
{
    /** @var list<string> */
    private const WHITELIST_BASENAMES = [
        'DatabaseUsageService.php',
        'ServicesDependencyProbeService.php',
    ];

    /**
     * Static Eloquent-style entry points (and close cousins) that services must not use.
     *
     * @var list<string>
     */
    private const FORBIDDEN_STATIC_METHODS = [
        'query',
        'create',
        'updateOrCreate',
        'firstOrCreate',
        'where',
        'whereIn',
        'whereNull',
        'whereNotNull',
        'whereHas',
        'find',
        'findOrFail',
        'first',
        'firstOrFail',
        'firstWhere',
        'with',
        'all',
        'pluck',
        'paginate',
        'get',
    ];

    /**
     * Non-Eloquent static receivers that may call methods overlapping the forbidden list
     * (e.g. Arr::get, StorageDriver::all, Cache::get, RuntimeConfig::get).
     *
     * @var list<string>
     */
    private const ALLOWED_STATIC_RECEIVERS = [
        'Arr',
        'Auth',
        'Bus',
        'Cache',
        'Carbon',
        'Config',
        'Date',
        'DB',
        'Event',
        'Gate',
        'Hash',
        'Http',
        'Log',
        'Queue',
        'RateLimiter',
        'Redis',
        'Route',
        'RuntimeConfig',
        'Storage',
        'StorageDriver',
        'Str',
        'URL',
        'Validator',
    ];

    /** @var list<string> */
    private const FORBIDDEN_INSTANCE_PATTERNS = [
        // Eloquent instance mutations — exclude $this->repo->update(...) chains.
        '/\$(?!this\b)[A-Za-z_][A-Za-z0-9_]*->update\s*\(\s*\[/',
        '/\$(?!this\b)[A-Za-z_][A-Za-z0-9_]*->save\s*\(\s*\)/',
        '/\$(?!this\b)[A-Za-z_][A-Za-z0-9_]*->delete\s*\(\s*\)/',
        '/\$(?!this\b)[A-Za-z_][A-Za-z0-9_]*->increment\s*\(/',
        '/\$(?!this\b)[A-Za-z_][A-Za-z0-9_]*->decrement\s*\(/',
        '/DB::table\s*\(/',
    ];

    #[Test]
    public function module_services_do_not_call_eloquent_or_db_table_directly(): void
    {
        $violations = $this->scan(base_path('Modules'));

        $this->assertSame(
            [],
            $violations,
            "Module Services must use Repositories only. Offenders:\n".implode("\n", $violations),
        );
    }

    #[Test]
    #[DataProvider('forbiddenFixtureProvider')]
    public function forbidden_fixtures_are_detected(string $source): void
    {
        $this->assertNotSame(
            [],
            $this->violationsInSource($source),
            'Expected a Service layering violation for fixture source.',
        );
    }

    #[Test]
    #[DataProvider('allowedFixtureProvider')]
    public function allowed_fixtures_are_clean(string $source): void
    {
        $this->assertSame(
            [],
            $this->violationsInSource($source),
            "Unexpected Service layering violation(s):\n".implode("\n", $this->violationsInSource($source)),
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function forbiddenFixtureProvider(): iterable
    {
        yield 'Model::where' => ["Contact::where('nsid', \$n)->get();"];
        yield 'Model::whereIn' => ["Photo::whereIn('id', \$ids)->get();"];
        yield 'Model::find' => ['CrawlRun::find($id);'];
        yield 'Model::findOrFail' => ['Connection::findOrFail($id);'];
        yield 'Model::first' => ['SpiderRun::first();'];
        yield 'Model::firstWhere' => ["Favorite::firstWhere('nsid', \$n);"];
        yield 'Model::with' => ["Photo::with('owner')->get();"];
        yield 'Model::all' => ['Contact::all();'];
        yield 'Model::query' => ['Contact::query()->where("nsid", $n);'];
        yield 'Model::create' => ["Contact::create(['nsid' => \$n]);"];
        yield 'DB::table' => ["DB::table('photos')->get();"];
        yield 'instance save' => ['$state->save();'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allowedFixtureProvider(): iterable
    {
        yield 'Str::of' => ['Str::of($value)->lower();'];
        yield 'Log::info' => ["Log::info('ok', \$ctx);"];
        yield 'DB::transaction' => ['DB::transaction(fn () => null);'];
        yield 'StorageDriver::all' => ['StorageDriver::all();'];
        yield 'Arr::get' => ["Arr::get(\$row, 'id');"];
        yield 'Cache::get' => ['Cache::get($key);'];
        yield 'RuntimeConfig::get' => ['RuntimeConfig::get($path);'];
        yield 'Redis::get' => ['Redis::get($key);'];
        yield 'repo find' => ['$this->runs->find($id);'];
        yield 'builder where' => ["\$query->where('nsid', \$n);"];
        yield 'ValidationException::withMessages' => ["ValidationException::withMessages(['url' => 'bad']);"];
        yield 'Http::withToken' => ['Http::withToken($token)->get($url);'];
    }

    /**
     * @return list<string>
     */
    private function scan(string $modulesRoot): array
    {
        if (! is_dir($modulesRoot)) {
            return [];
        }

        $violations = [];

        foreach (scandir($modulesRoot) ?: [] as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $servicesPath = $modulesRoot.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Services';
            if (! is_dir($servicesPath)) {
                continue;
            }

            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($servicesPath)),
                '/\.php$/',
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $path = $file->getPathname();
                if (in_array($file->getBasename(), self::WHITELIST_BASENAMES, true)) {
                    continue;
                }

                $contents = $this->stripComments((string) file_get_contents($path));
                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

                foreach ($this->violationsInSource($contents) as $detail) {
                    $violations[] = $relative.' '.$detail;
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function violationsInSource(string $contents): array
    {
        $violations = [];
        $methods = implode('|', array_map(
            static fn (string $method): string => preg_quote($method, '/'),
            self::FORBIDDEN_STATIC_METHODS,
        ));

        if (preg_match_all('/\\\\?([A-Za-z_][A-Za-z0-9_]*)::('.$methods.')\s*\(/', $contents, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $receiver = $match[1];
                $method = $match[2];
                if (in_array($receiver, self::ALLOWED_STATIC_RECEIVERS, true)) {
                    continue;
                }

                $violations[] = "matches {$receiver}::{$method}(";
            }
        }

        foreach (self::FORBIDDEN_INSTANCE_PATTERNS as $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = 'matches '.$pattern;
            }
        }

        return $violations;
    }

    private function stripComments(string $contents): string
    {
        $stripped = preg_replace('!/\*.*?\*/!s', '', $contents);
        if (! is_string($stripped)) {
            return $contents;
        }

        $stripped = preg_replace('/^\s*\/\/.*$/m', '', $stripped);

        return is_string($stripped) ? $stripped : $contents;
    }
}
