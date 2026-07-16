<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Adapters\OneDrive;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Adapters\OneDriveAdapter;
use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAdapterFactory;
use Modules\Storage\Tests\Concerns\AssertsConnectionReports;
use Modules\Storage\Tests\TestCase;

final class OneDriveAdapterVerifyTest extends TestCase
{
    use AssertsConnectionReports;

    public function test_factory_creates_correct_adapter_for_provider(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(OneDriveAdapter::class, $adapter);
    }

    public function test_verify_reports_missing_scopes_without_calling_api(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => ['offline_access'],
            ],
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $this->assertSame(ConnectionCheckStatus::Failed, $this->check($report, 'Authorization')->status);
        $this->assertFalse($report->healthy());
        $this->assertNull($this->findCheck($report, 'Drive API'));
        Http::assertNothingSent();
    }

    public function test_verify_reports_drive_details_when_api_succeeds(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();
        $owner = fake()->name();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response([
                'id' => 'drive-1',
                'driveType' => 'personal',
                'owner' => ['user' => ['displayName' => $owner]],
                'quota' => ['used' => 1073741824, 'total' => 5368709120],
            ]),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $this->assertTrue($report->healthy());
        $drive = $this->check($report, 'Drive API');
        $this->assertSame(ConnectionCheckStatus::Passed, $drive->status);
        $this->assertTrue($this->detailsContain($drive, $owner));
        $this->assertTrue($this->detailsContain($drive, 'Drive: drive-1 (personal)'));
        $this->assertTrue($this->detailsContain($drive, 'Storage used:'));
    }

    public function test_verify_surfaces_api_error_message(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive*' => Http::response([
                'error' => ['message' => 'InvalidAuthenticationToken'],
            ], 401),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $drive = $this->check($report, 'Drive API');
        $this->assertSame(ConnectionCheckStatus::Failed, $drive->status);
        $this->assertSame('OneDrive probe failed: InvalidAuthenticationToken', $drive->message);
        $this->assertFalse($report->healthy());
    }

    public function test_verify_fails_when_credentials_are_incomplete(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create([
            'credentials' => [
                'granted_scopes' => StorageDriver::OneDrive->defaultScopes(),
            ],
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $drive = $this->check($report, 'Drive API');
        $this->assertSame(ConnectionCheckStatus::Failed, $drive->status);
        $this->assertStringContainsString('OneDrive credentials are incomplete', $drive->message);
    }
}
