<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Adapters\R2;

use Modules\Storage\Adapters\R2Adapter;
use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAdapterFactory;
use Modules\Storage\Tests\Concerns\AssertsConnectionReports;
use Modules\Storage\Tests\TestCase;
use RuntimeException;

final class R2AdapterVerifyTest extends TestCase
{
    use AssertsConnectionReports;

    public function test_factory_creates_correct_adapter_for_provider(): void
    {
        $account = StorageAccount::factory()->r2()->create();

        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(R2Adapter::class, $adapter);
    }

    public function test_verify_passes_when_bucket_is_reachable(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $bucket = ($account->credentials ?? [])['bucket'];

        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
        });

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $this->assertTrue($report->healthy());
        $this->assertSame(ConnectionCheckStatus::Passed, $this->check($report, 'Credentials')->status);
        $bucketCheck = $this->check($report, 'Bucket access');
        $this->assertSame(ConnectionCheckStatus::Passed, $bucketCheck->status);
        $this->assertStringContainsString($bucket, $bucketCheck->message);
    }

    public function test_verify_fails_when_bucket_probe_throws(): void
    {
        $account = StorageAccount::factory()->r2()->create();

        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')
                ->once()
                ->andThrow(new RuntimeException('403 Forbidden'));
        });

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $bucketCheck = $this->check($report, 'Bucket access');
        $this->assertSame(ConnectionCheckStatus::Failed, $bucketCheck->status);
        $this->assertSame('Bucket probe failed: 403 Forbidden', $bucketCheck->message);
        $this->assertFalse($report->healthy());
    }

    public function test_verify_fails_when_credentials_are_incomplete(): void
    {
        $account = StorageAccount::factory()->r2()->create([
            'credentials' => [
                'access_key_id' => fake()->regexify('[A-Z0-9]{20}'),
            ],
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $credentials = $this->check($report, 'Credentials');
        $this->assertSame(ConnectionCheckStatus::Failed, $credentials->status);
        $this->assertSame('Cloudflare R2 credentials are incomplete.', $credentials->message);
        $this->assertNull($this->findCheck($report, 'Bucket access'));
        $this->assertFalse($report->healthy());
    }
}
