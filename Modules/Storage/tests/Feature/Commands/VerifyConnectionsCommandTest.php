<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Commands;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Tests\TestCase;

final class VerifyConnectionsCommandTest extends TestCase
{
    public function test_command_fails_when_no_storage_accounts_exist(): void
    {
        $this->artisan('xflickr:storage:verify-connections')
            ->expectsOutput('No storage accounts found. Connect one in Settings → Storages.')
            ->assertFailed();
    }

    public function test_command_verifies_accounts_across_providers(): void
    {
        $photos = StorageAccount::factory()->googlePhotos()->default()->create([
            'label' => 'Primary Photos',
        ]);
        $r2 = StorageAccount::factory()->r2()->create([
            'label' => 'Backup Bucket',
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response(['mediaItems' => []]),
        ]);
        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
        });

        $this->artisan('xflickr:storage:verify-connections')
            ->expectsOutputToContain("Google Photos — Primary Photos (#{$photos->id})")
            ->expectsOutputToContain("Cloudflare R2 — Backup Bucket (#{$r2->id})")
            ->expectsOutputToContain('All 2 storage account(s) verified.')
            ->assertSuccessful();
    }

    public function test_command_resolves_single_account_by_option(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create();
        $target = StorageAccount::factory()->googlePhotos()->create([
            'label' => 'Secondary Photos',
        ]);

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(['albums' => []]),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response(['mediaItems' => []]),
        ]);

        $this->artisan('xflickr:storage:verify-connections', ['--account' => (string) $target->id])
            ->expectsOutputToContain("Google Photos — Secondary Photos (#{$target->id})")
            ->expectsOutputToContain('All 1 storage account(s) verified.')
            ->assertSuccessful();
    }

    public function test_command_fails_when_account_option_is_unknown(): void
    {
        $this->artisan('xflickr:storage:verify-connections', ['--account' => '999'])
            ->expectsOutput('Storage account [999] not found.')
            ->assertFailed();
    }

    public function test_command_filters_by_provider_option(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create();
        $r2 = StorageAccount::factory()->r2()->create([
            'label' => 'Only Bucket',
        ]);

        Http::fake();
        $this->bindInMemoryDisk(function ($factory): void {
            $factory->shouldReceive('verifyR2Credentials')->once();
        });

        $this->artisan('xflickr:storage:verify-connections', ['--provider' => 'r2'])
            ->expectsOutputToContain("Cloudflare R2 — Only Bucket (#{$r2->id})")
            ->expectsOutputToContain('All 1 storage account(s) verified.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_command_fails_when_provider_option_is_unknown(): void
    {
        $this->artisan('xflickr:storage:verify-connections', ['--provider' => 'dropbox'])
            ->expectsOutput('Unknown storage provider [dropbox].')
            ->assertFailed();
    }

    public function test_command_fails_when_a_probe_errors(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'error' => ['message' => 'Token expired'],
            ], 401),
        ]);

        $this->artisan('xflickr:storage:verify-connections')
            ->expectsOutputToContain('Library probe failed: Token expired')
            ->expectsOutputToContain('1 of 1 storage account(s) failed verification.')
            ->assertFailed();
    }

    public function test_command_fails_when_scopes_are_missing(): void
    {
        StorageAccount::factory()->googlePhotos()->default()->create([
            'credentials' => [
                'access_token' => 'token',
                'granted_scopes' => ['https://www.googleapis.com/auth/photoslibrary.appendonly'],
            ],
        ]);

        $this->artisan('xflickr:storage:verify-connections')
            ->expectsOutputToContain('Additional scopes required')
            ->expectsOutputToContain('1 of 1 storage account(s) failed verification.')
            ->assertFailed();
    }
}
