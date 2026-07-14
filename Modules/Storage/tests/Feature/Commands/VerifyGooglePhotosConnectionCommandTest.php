<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Commands;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class VerifyGooglePhotosConnectionCommandTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_command_fails_when_no_google_photos_account_exists(): void
    {
        $this->artisan('xflickr:storage:verify-google-photos')
            ->expectsOutput('No Google Photos storage account found. Connect one in Settings → Storage.')
            ->assertFailed();
    }

    public function test_command_uses_default_google_photos_account(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->default()->create([
            'label' => 'Primary Photos',
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response(['mediaItems' => []]),
        ]);

        $this->artisan('xflickr:storage:verify-google-photos')
            ->expectsOutputToContain("Verifying Google Photos account #{$account->id} (Primary Photos)")
            ->expectsOutputToContain('Authorization: scopes OK for app-created content.')
            ->assertSuccessful();
    }

    public function test_command_resolves_account_by_option(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create();
        $target = StorageAccount::factory()->googlePhotos()->create([
            'label' => 'Secondary Photos',
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response(['mediaItems' => []]),
        ]);

        $this->artisan('xflickr:storage:verify-google-photos', ['--account' => (string) $target->id])
            ->expectsOutputToContain("Verifying Google Photos account #{$target->id} (Secondary Photos)")
            ->assertSuccessful();
    }

    public function test_command_fails_when_library_probe_errors(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'error' => ['message' => 'Token expired'],
            ], 401),
        ]);

        $this->artisan('xflickr:storage:verify-google-photos')
            ->expectsOutputToContain('Library probe failed: Token expired')
            ->assertFailed();
    }

    public function test_command_warns_when_scopes_are_missing(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create([
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => ['https://www.googleapis.com/auth/photoslibrary.appendonly'],
            ],
        ]);

        $this->artisan('xflickr:storage:verify-google-photos')
            ->expectsOutputToContain('Authorization: additional scopes required')
            ->expectsOutputToContain('Library probe failed:')
            ->assertFailed();
    }

    public function test_command_prints_sample_albums_and_media_when_library_has_content(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create([
            'label' => 'Populated Photos',
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [[
                    'id' => 'album-1',
                    'title' => 'Trip Album',
                    'mediaItemsCount' => 2,
                ]],
            ]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [[
                    'id' => 'media-1',
                    'filename' => 'sunset.jpg',
                ]],
            ]),
        ]);

        $this->artisan('xflickr:storage:verify-google-photos')
            ->expectsOutputToContain('Albums:      1')
            ->expectsOutputToContain('Media items: 1')
            ->expectsOutputToContain('Sample albums:')
            ->expectsOutputToContain('Sample photos:')
            ->expectsOutputToContain('sunset.jpg')
            ->assertSuccessful();
    }
}
