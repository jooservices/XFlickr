<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Support;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Transfer\Support\TransferRuntimeConfig;
use Tests\TestCase;

final class TransferRuntimeConfigTest extends TestCase
{
    private const DELETE_PATH = 'xflickr_transfer.delete_local_after_upload';

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }

        $this->resetTransferRuntimeConfig();
    }

    protected function tearDown(): void
    {
        $this->resetTransferRuntimeConfig();

        parent::tearDown();
    }

    private function resetTransferRuntimeConfig(): void
    {
        if (RuntimeConfig::has(self::DELETE_PATH)) {
            RuntimeConfig::forget(self::DELETE_PATH);
        }

        RuntimeConfig::refresh();
    }

    public function test_defaults_to_false_when_not_stored(): void
    {
        $config = app(TransferRuntimeConfig::class);

        $this->assertFalse($config->shouldDeleteLocalAfterUpload());
    }

    public function test_reads_stored_runtime_value_true(): void
    {
        RuntimeConfig::set(self::DELETE_PATH, true, 'bool');
        RuntimeConfig::refresh();

        $config = app(TransferRuntimeConfig::class);

        $this->assertTrue($config->shouldDeleteLocalAfterUpload());
    }

    public function test_reads_stored_runtime_value_false(): void
    {
        RuntimeConfig::set(self::DELETE_PATH, false, 'bool');
        RuntimeConfig::refresh();

        $config = app(TransferRuntimeConfig::class);

        $this->assertFalse($config->shouldDeleteLocalAfterUpload());
    }
}
