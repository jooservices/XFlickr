<?php

declare(strict_types=1);

namespace Modules\Storage\Console\Commands;

use Illuminate\Console\Command;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotosConnectionVerifier;

final class VerifyGooglePhotosConnectionCommand extends Command
{
    protected $signature = 'xflickr:storage:verify-google-photos {--account= : Storage account ID (defaults to google_photos default)}';

    protected $description = 'Verify Google Photos app-created library content for the connected OAuth client';

    public function handle(GooglePhotosConnectionVerifier $verifier): int
    {
        $account = $this->resolveAccount();
        if ($account === null) {
            $this->error('No Google Photos storage account found. Connect one in Settings → Storage.');

            return self::FAILURE;
        }

        $this->info("Verifying Google Photos account #{$account->id} ({$account->label})…");
        $this->newLine();

        $report = $verifier->verify($account);

        $oauth = $report['oauth_app'];
        $this->line('OAuth app (Client ID):');
        $this->line('  Configured in Settings: '.($oauth['configured_client_id'] ?? 'not set'));
        $this->line('  Stored on account:      '.($oauth['account_client_id'] ?? 'not set'));
        $this->line('  Client IDs match:       '.($oauth['client_ids_match'] ? 'yes' : 'no'));
        $this->newLine();

        $auth = $report['authorization'];
        if ($auth['needs_reauthorization']) {
            $this->warn('Authorization: additional scopes required — reauthorize in Settings or Storages → Google Photos.');
            foreach ($auth['missing_scopes'] as $scope) {
                $this->line('  - '.($scope['label'] ?? $scope['scope']));
            }
            $this->newLine();
        } else {
            $this->info('Authorization: scopes OK for app-created content.');
            $this->newLine();
        }

        $library = $report['library'];
        if ($library['error'] !== null) {
            $this->error('Library probe failed: '.$library['error']);

            return self::FAILURE;
        }

        if (! $library['accessible']) {
            $this->error('Library is not accessible.');

            return self::FAILURE;
        }

        $this->info('App-created library content visible to this Client ID:');
        $this->line('  Albums:      '.$library['album_count'].($library['truncated'] ? '+' : ''));
        $this->line('  Media items: '.$library['media_item_count'].($library['truncated'] ? '+' : ''));
        if ($library['truncated']) {
            $this->comment('  (Counts may be higher — verification capped pagination for speed.)');
        }
        $this->newLine();

        if ($library['album_count'] === 0 && $library['media_item_count'] === 0) {
            $this->warn('No albums or photos found for this OAuth Client ID.');
            $this->line('If you uploaded via JFlickr, confirm it used the same Google OAuth Client ID as XFlickr Settings.');
        } else {
            if ($library['sample_albums'] !== []) {
                $this->line('Sample albums:');
                foreach ($library['sample_albums'] as $album) {
                    $count = $album['media_items_count'] ?? '—';
                    $this->line("  - {$album['title']} ({$count} items) [{$album['id']}]");
                }
                $this->newLine();
            }

            if ($library['sample_media_items'] !== []) {
                $this->line('Sample photos:');
                foreach ($library['sample_media_items'] as $item) {
                    $this->line("  - {$item['filename']} [{$item['id']}]");
                }
            }
        }

        return self::SUCCESS;
    }

    private function resolveAccount(): ?StorageAccount
    {
        $accountId = $this->option('account');
        if (is_string($accountId) && $accountId !== '') {
            return StorageAccount::query()
                ->where('provider', StorageDriver::GooglePhotos->value)
                ->find((int) $accountId);
        }

        return StorageAccount::query()
            ->where('provider', StorageDriver::GooglePhotos->value)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }
}
