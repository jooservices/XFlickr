<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Spider\Support\SpiderRuntimeConfig;
use Tests\TestCase;

final class SpiderRuntimeConfigTest extends TestCase
{
    /** @var list<string> */
    private const SPIDER_PATHS = [
        'spider.enabled',
        'spider.max_depth',
        'spider.max_new_contacts_per_run',
        'spider.max_contacts_total',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }

        $this->resetSpiderRuntimeConfig();
    }

    protected function tearDown(): void
    {
        $this->resetSpiderRuntimeConfig();

        parent::tearDown();
    }

    private function resetSpiderRuntimeConfig(): void
    {
        foreach (self::SPIDER_PATHS as $path) {
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }

        RuntimeConfig::refresh();
    }

    public function test_reads_defaults_when_not_stored(): void
    {
        $config = app(SpiderRuntimeConfig::class);

        $this->assertFalse($config->enabled());
        $this->assertSame(2, $config->maxDepth());
        $this->assertSame(25, $config->maxNewContactsPerRun());
        $this->assertSame(500, $config->maxContactsTotal());
    }

    public function test_reads_stored_runtime_values(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::set('spider.max_depth', 3, 'int');
        RuntimeConfig::set('spider.max_new_contacts_per_run', 10, 'int');
        RuntimeConfig::set('spider.max_contacts_total', 100, 'int');
        RuntimeConfig::refresh();

        $config = app(SpiderRuntimeConfig::class);

        $this->assertTrue($config->enabled());
        $this->assertSame(3, $config->maxDepth());
        $this->assertSame(10, $config->maxNewContactsPerRun());
        $this->assertSame(100, $config->maxContactsTotal());
    }
}
