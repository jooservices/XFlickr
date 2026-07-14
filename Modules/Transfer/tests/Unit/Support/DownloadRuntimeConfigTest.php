<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Transfer\Support\DownloadRuntimeConfig;
use Tests\TestCase;

final class DownloadRuntimeConfigTest extends TestCase
{
    private const TIMEOUT_PATH = 'xflickr_download.timeout_seconds';

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }

        $this->resetDownloadRuntimeConfig();
    }

    protected function tearDown(): void
    {
        $this->resetDownloadRuntimeConfig();

        parent::tearDown();
    }

    private function resetDownloadRuntimeConfig(): void
    {
        if (RuntimeConfig::has(self::TIMEOUT_PATH)) {
            RuntimeConfig::forget(self::TIMEOUT_PATH);
        }

        RuntimeConfig::refresh();
    }

    public function test_reads_default_when_not_stored(): void
    {
        $config = app(DownloadRuntimeConfig::class);

        $this->assertSame(120, $config->timeoutSeconds());
    }

    public function test_reads_stored_runtime_value(): void
    {
        RuntimeConfig::set(self::TIMEOUT_PATH, 300, 'int');
        RuntimeConfig::refresh();

        $config = app(DownloadRuntimeConfig::class);

        $this->assertSame(300, $config->timeoutSeconds());
    }

    public function test_clamps_timeout_between_thirty_and_nine_hundred_seconds(): void
    {
        RuntimeConfig::set(self::TIMEOUT_PATH, 10, 'int');
        RuntimeConfig::refresh();

        $config = app(DownloadRuntimeConfig::class);

        $this->assertSame(30, $config->timeoutSeconds());

        RuntimeConfig::set(self::TIMEOUT_PATH, 1200, 'int');
        RuntimeConfig::refresh();

        $this->assertSame(900, $config->timeoutSeconds());
    }
}
