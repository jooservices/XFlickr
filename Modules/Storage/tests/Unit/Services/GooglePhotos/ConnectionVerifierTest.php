<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\GooglePhotos;

use Illuminate\Support\Facades\Http;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotos\ConnectionVerifier;
use Modules\Storage\Tests\TestCase;
use RuntimeException;

final class ConnectionVerifierTest extends TestCase
{
    public function test_verify_rejects_non_google_photos_account(): void
    {
        $account = StorageAccount::factory()->r2()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account is not a Google Photos storage account.');

        app(ConnectionVerifier::class)->verify($account);
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

        $report = app(ConnectionVerifier::class)->verify($account);

        $this->assertTrue($report['authorization']['needs_reauthorization']);
        $this->assertSame(
            'Missing OAuth scopes. Reauthorize this account before browsing app-created content.',
            $report['library']['error'],
        );
        $this->assertFalse($report['library']['accessible']);
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

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [
                    [
                        'id' => 'album-'.fake()->uuid(),
                        'title' => fake()->words(2, true),
                        'mediaItemsCount' => 2,
                    ],
                ],
            ]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'media-'.fake()->uuid(),
                        'filename' => fake()->word().'.jpg',
                        'mimeType' => 'image/jpeg',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ],
            ]),
        ]);

        $report = app(ConnectionVerifier::class)->verify($account);

        $this->assertTrue($report['library']['accessible']);
        $this->assertNull($report['library']['error']);
        $this->assertSame(1, $report['library']['album_count']);
        $this->assertSame(1, $report['library']['media_item_count']);
        $this->assertTrue($report['oauth_app']['client_ids_match']);
        $this->assertCount(1, $report['library']['sample_albums']);
        $this->assertCount(1, $report['library']['sample_media_items']);
    }

    public function test_verify_surfaces_api_error_message(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'error' => ['message' => 'The user has not granted permission.'],
            ], 403),
        ]);

        $report = app(ConnectionVerifier::class)->verify($account);

        $this->assertFalse($report['library']['accessible']);
        $this->assertSame('The user has not granted permission.', $report['library']['error']);
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

        $report = app(ConnectionVerifier::class)->verify($account, sampleLimit: 5, maxPages: 1);

        $this->assertTrue($report['library']['truncated']);
    }

    public function test_verify_masks_long_client_ids(): void
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

        $report = app(ConnectionVerifier::class)->verify($account);

        $masked = $report['oauth_app']['account_client_id'];
        $this->assertIsString($masked);
        $this->assertStringContainsString('…', $masked);
        $this->assertStringStartsWith(substr($clientId, 0, 8), $masked);
    }
}
