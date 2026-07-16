<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Adapters\GooglePhotos;

use Illuminate\Support\Facades\Http;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Adapters\GooglePhotosAdapter;
use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAdapterFactory;
use Modules\Storage\Tests\Concerns\AssertsConnectionReports;
use Modules\Storage\Tests\TestCase;

final class GooglePhotosAdapterVerifyTest extends TestCase
{
    use AssertsConnectionReports;

    public function test_factory_creates_google_photos_adapter_for_google_photos_account(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        $adapter = app(StorageAdapterFactory::class)->make($account);

        $this->assertInstanceOf(GooglePhotosAdapter::class, $adapter);
    }

    public function test_verify_reports_missing_scopes_without_calling_api(): void
    {
        $account = StorageAccount::factory()->create([
            'provider' => StorageDriver::GooglePhotos->value,
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => ['https://www.googleapis.com/auth/photoslibrary.appendonly'],
            ],
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $authorization = $this->check($report, 'Authorization');
        $this->assertSame(ConnectionCheckStatus::Failed, $authorization->status);
        $this->assertFalse($report->healthy());
        $this->assertNull($this->findCheck($report, 'Library'));
        Http::assertNothingSent();
    }

    public function test_verify_returns_library_samples_when_api_succeeds(): void
    {
        $clientId = fake()->uuid();
        RuntimeConfig::set('storage_app.'.StorageDriver::GooglePhotos->value, [
            'client_id' => $clientId,
            'client_secret' => fake()->sha256(),
        ]);

        $account = StorageAccount::factory()->googlePhotos()->create([
            'credentials' => [
                'access_token' => 'test-token',
                'refresh_token' => 'refresh',
                'client_id' => $clientId,
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
        ]);

        $albumTitle = fake()->words(2, true);
        $filename = fake()->word().'.jpg';

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [
                    [
                        'id' => 'album-'.fake()->uuid(),
                        'title' => $albumTitle,
                        'mediaItemsCount' => 2,
                    ],
                ],
            ]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'media-'.fake()->uuid(),
                        'filename' => $filename,
                        'mimeType' => 'image/jpeg',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ],
            ]),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $this->assertTrue($report->healthy());
        $this->assertSame(ConnectionCheckStatus::Passed, $this->check($report, 'OAuth app')->status);
        $this->assertSame(ConnectionCheckStatus::Passed, $this->check($report, 'Authorization')->status);

        $library = $this->check($report, 'Library');
        $this->assertSame(ConnectionCheckStatus::Passed, $library->status);
        $this->assertContains('Albums:      1', $library->details);
        $this->assertContains('Media items: 1', $library->details);
        $this->assertTrue($this->detailsContain($library, "Album: {$albumTitle}"));
        $this->assertTrue($this->detailsContain($library, "Photo: {$filename}"));
    }

    public function test_verify_warns_when_library_is_empty(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response(['mediaItems' => []]),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $library = $this->check($report, 'Library');
        $this->assertSame(ConnectionCheckStatus::Warning, $library->status);
        $this->assertStringContainsString('JFlickr', $library->message);
        $this->assertTrue($report->healthy());
    }

    public function test_verify_surfaces_api_error_message(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'error' => ['message' => 'The user has not granted permission.'],
            ], 403),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $library = $this->check($report, 'Library');
        $this->assertSame(ConnectionCheckStatus::Failed, $library->status);
        $this->assertSame('Library probe failed: The user has not granted permission.', $library->message);
        $this->assertFalse($report->healthy());
    }

    public function test_verify_marks_truncated_when_pagination_exceeds_max_pages(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [['id' => 'album-1', 'title' => 'Album']],
                'nextPageToken' => 'page-2',
            ]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [],
            ]),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $library = $this->check($report, 'Library');
        $this->assertContains('Albums:      5+', $library->details);
    }

    public function test_verify_masks_client_ids_in_oauth_details(): void
    {
        $clientId = fake()->regexify('[a-z0-9]{32}');
        $account = StorageAccount::factory()->googlePhotos()->create([
            'credentials' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
                'client_id' => $clientId,
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response(['mediaItems' => []]),
        ]);

        $report = app(StorageAdapterFactory::class)->make($account)->verify();

        $oauth = $this->check($report, 'OAuth app');
        $this->assertTrue($this->detailsContain($oauth, substr($clientId, 0, 4).'…'.substr($clientId, -4)));
        $this->assertFalse($this->detailsContain($oauth, $clientId));
    }
}
