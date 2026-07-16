<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\ConnectionVerificationService;
use Modules\Storage\Tests\Concerns\AssertsConnectionReports;
use Modules\Storage\Tests\TestCase;

final class ConnectionVerificationServiceTest extends TestCase
{
    use AssertsConnectionReports;

    public function test_verify_account_returns_null_for_unknown_id(): void
    {
        $this->assertNull(app(ConnectionVerificationService::class)->verifyAccount(999));
    }

    public function test_verify_reports_unknown_provider_as_failed(): void
    {
        $account = StorageAccount::factory()->create(['provider' => 'bogus']);

        $report = app(ConnectionVerificationService::class)->verify($account);

        $this->assertFalse($report->healthy());
        $this->assertSame('bogus', $report->provider);
        $provider = $this->check($report, 'Provider');
        $this->assertSame(ConnectionCheckStatus::Failed, $provider->status);
        $this->assertSame('Unknown storage provider [bogus].', $provider->message);
    }

    public function test_verify_all_filters_by_provider(): void
    {
        StorageAccount::factory()->googlePhotos()->create();
        $r2 = StorageAccount::factory()->r2()->create();

        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
        });

        $reports = app(ConnectionVerificationService::class)->verifyAll(StorageDriver::R2);

        $this->assertCount(1, $reports);
        $this->assertSame($r2->id, $reports[0]->accountId);
        $this->assertSame(StorageDriver::R2->value, $reports[0]->provider);
    }

    public function test_verify_all_routes_each_account_to_its_driver(): void
    {
        $r2 = StorageAccount::factory()->r2()->create();

        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
        });

        $reports = app(ConnectionVerificationService::class)->verifyAll();

        $this->assertCount(1, $reports);
        $this->assertSame($r2->id, $reports[0]->accountId);
        $this->assertSame(StorageDriver::R2->label(), $reports[0]->providerLabel);
        $this->assertTrue($reports[0]->healthy());
    }
}
