<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Contacts\Support\ContactGraphRuntimeConfig;
use Tests\TestCase;

final class ContactGraphRuntimeConfigTest extends TestCase
{
    /** @var list<string> */
    private const PATHS = [
        'contact_graph.initial_direct_limit',
        'contact_graph.load_more_step',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }

        $this->resetConfig();
    }

    protected function tearDown(): void
    {
        $this->resetConfig();

        parent::tearDown();
    }

    private function resetConfig(): void
    {
        foreach (self::PATHS as $path) {
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }

        RuntimeConfig::refresh();
    }

    public function test_reads_defaults_when_not_stored(): void
    {
        $config = app(ContactGraphRuntimeConfig::class);

        $this->assertSame(100, $config->initialDirectLimit());
        $this->assertSame(100, $config->loadMoreStep());
    }

    public function test_reads_stored_runtime_values(): void
    {
        RuntimeConfig::set('contact_graph.initial_direct_limit', 250, 'int');
        RuntimeConfig::set('contact_graph.load_more_step', 50, 'int');
        RuntimeConfig::refresh();

        $config = app(ContactGraphRuntimeConfig::class);

        $this->assertSame(250, $config->initialDirectLimit());
        $this->assertSame(50, $config->loadMoreStep());
    }
}
