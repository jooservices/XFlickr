<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\HorizonRuntimeConfig;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Tests\TestCase;

final class HorizonRuntimeConfigTest extends TestCase
{
    /** @var list<string> */
    private const HORIZON_PATHS = [
        'horizon.general_max_processes',
        'horizon.downloads_max_processes',
        'horizon.uploads_max_processes',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }

        $this->resetHorizonRuntimeConfig();
    }

    protected function tearDown(): void
    {
        $this->resetHorizonRuntimeConfig();

        parent::tearDown();
    }

    private function resetHorizonRuntimeConfig(): void
    {
        foreach (self::HORIZON_PATHS as $path) {
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }

        RuntimeConfig::refresh();
    }

    public function test_reads_production_defaults_when_not_stored(): void
    {
        $config = app(HorizonRuntimeConfig::class);

        $this->assertSame(8, $config->effectiveMaxProcesses(HorizonRuntimeConfig::SUPERVISOR_GENERAL));
        $this->assertSame(4, $config->effectiveMaxProcesses(HorizonRuntimeConfig::SUPERVISOR_DOWNLOADS));
        $this->assertSame(2, $config->effectiveMaxProcesses(HorizonRuntimeConfig::SUPERVISOR_UPLOADS));
    }

    public function test_reads_stored_runtime_values(): void
    {
        RuntimeConfig::set('horizon.general_max_processes', 5, 'int');
        RuntimeConfig::set('horizon.downloads_max_processes', 3, 'int');
        RuntimeConfig::set('horizon.uploads_max_processes', 1, 'int');
        RuntimeConfig::refresh();

        $config = app(HorizonRuntimeConfig::class);

        $this->assertSame(5, $config->effectiveMaxProcesses(HorizonRuntimeConfig::SUPERVISOR_GENERAL));
        $this->assertSame(3, $config->effectiveMaxProcesses(HorizonRuntimeConfig::SUPERVISOR_DOWNLOADS));
        $this->assertSame(1, $config->effectiveMaxProcesses(HorizonRuntimeConfig::SUPERVISOR_UPLOADS));
    }

    public function test_clamps_worker_count_between_one_and_thirty_two(): void
    {
        $config = app(HorizonRuntimeConfig::class);

        $this->assertSame(1, $config->assertValidWorkerCount(0));
        $this->assertSame(1, $config->assertValidWorkerCount(-3));
        $this->assertSame(32, $config->assertValidWorkerCount(99));
        $this->assertSame(4, $config->assertValidWorkerCount(4));
    }
}
