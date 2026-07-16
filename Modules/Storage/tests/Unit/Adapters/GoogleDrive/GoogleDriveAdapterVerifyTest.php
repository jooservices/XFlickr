<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Adapters\GoogleDrive;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Adapters\GoogleDriveAdapter;
use Modules\Storage\Adapters\R2Adapter;
use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAdapterFactory;
use Modules\Storage\Tests\Concerns\AssertsConnectionReports;
use Modules\Storage\Tests\TestCase;

final class GoogleDriveAdapterVerifyTest extends TestCase
{
    use AssertsConnectionReports;

    public function test_factory_creates_correct_adapter_for_provider(): void
    {
        $googleDriveAccount = StorageAccount::factory()->googleDrive()->create();
        $r2Account = StorageAccount::factory()->r2()->create();

        $factory = app(StorageAdapterFactory::class);

        $this->assertInstanceOf(GoogleDriveAdapter::class, $factory->make($googleDriveAccount));
        $this->assertInstanceOf(R2Adapter::class, $factory->make($r2Account));
    }

    public function test_verify_reports_missing_scopes_without_calling_api(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => [],
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
        $account = StorageAccount::factory()->googleDrive()->create();
        $email = fake()->safeEmail();

        Http::fake([
            'www.googleapis.com/drive/v3/about*' => Http::response([
                'user' => ['displayName' => fake()->name(), 'emailAddress' => $email],
                'storageQuota' => ['usage' => '1073741824', 'limit' => '16106127360'],
            ]),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $this->assertTrue($report->healthy());
        $drive = $this->check($report, 'Drive API');
        $this->assertSame(ConnectionCheckStatus::Passed, $drive->status);
        $this->assertTrue($this->detailsContain($drive, $email));
        $this->assertTrue($this->detailsContain($drive, 'Storage used:'));
    }

    public function test_verify_surfaces_api_error_message(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        Http::fake([
            'www.googleapis.com/drive/v3/about*' => Http::response([
                'error' => ['message' => 'Invalid Credentials'],
            ], 401),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $drive = $this->check($report, 'Drive API');
        $this->assertSame(ConnectionCheckStatus::Failed, $drive->status);
        $this->assertSame('Drive probe failed: Invalid Credentials', $drive->message);
        $this->assertFalse($report->healthy());
    }

    public function test_verify_fails_when_credentials_are_incomplete(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create([
            'credentials' => [
                'granted_scopes' => StorageDriver::GoogleDrive->defaultScopes(),
            ],
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $drive = $this->check($report, 'Drive API');
        $this->assertSame(ConnectionCheckStatus::Failed, $drive->status);
        $this->assertStringContainsString('Google credentials are incomplete.', $drive->message);
    }
}
