<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Jobs;

use App\Repositories\Crawler\ConnectionQueryRepository;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Jobs\FanOutTransferBatchJob;
use Modules\Transfer\Services\PhotoDownloadService;
use Modules\Transfer\Services\PhotoUploadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FanOutTransferBatchJobTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_logs_warning_when_flickr_connection_is_missing(): void
    {
        Event::fake([MessageLogged::class]);

        (new FanOutTransferBatchJob(TransferType::Download, 'missing@N00'))->handle(
            app(PhotoDownloadService::class),
            app(PhotoUploadService::class),
            app(ConnectionQueryRepository::class),
            app(StorageAccountRepository::class),
        );

        Event::assertDispatched(
            MessageLogged::class,
            fn (MessageLogged $log): bool => $log->level === 'warning'
                && $log->message === 'Fan-out skipped: Flickr connection missing.'
                && ($log->context['connection_key'] ?? null) === 'missing@N00',
        );
    }

    public function test_it_logs_warning_when_upload_storage_account_id_is_missing(): void
    {
        Event::fake([MessageLogged::class]);

        $connection = $this->createFlickrConnection(['connection_key' => 'owner@N01']);

        (new FanOutTransferBatchJob(TransferType::Upload, $connection->connection_key))->handle(
            app(PhotoDownloadService::class),
            app(PhotoUploadService::class),
            app(ConnectionQueryRepository::class),
            app(StorageAccountRepository::class),
        );

        Event::assertDispatched(
            MessageLogged::class,
            fn (MessageLogged $log): bool => $log->level === 'warning'
                && $log->message === 'Fan-out skipped: storage account id missing for upload.',
        );
    }

    public function test_it_runs_download_fan_out_for_existing_connection(): void
    {
        $connection = $this->createFlickrConnection();

        (new FanOutTransferBatchJob(TransferType::Download, $connection->connection_key))->handle(
            app(PhotoDownloadService::class),
            app(PhotoUploadService::class),
            app(ConnectionQueryRepository::class),
            app(StorageAccountRepository::class),
        );

        $this->assertTrue(true);
    }

    public function test_it_logs_warning_when_upload_storage_account_is_missing(): void
    {
        Event::fake([MessageLogged::class]);

        $connection = $this->createFlickrConnection();

        (new FanOutTransferBatchJob(
            TransferType::Upload,
            $connection->connection_key,
            storageAccountId: 999999,
        ))->handle(
            app(PhotoDownloadService::class),
            app(PhotoUploadService::class),
            app(ConnectionQueryRepository::class),
            app(StorageAccountRepository::class),
        );

        Event::assertDispatched(
            MessageLogged::class,
            fn (MessageLogged $log): bool => $log->level === 'warning'
                && $log->message === 'Fan-out skipped: storage account not found.',
        );
    }

    public function test_it_runs_upload_fan_out_for_existing_storage_account(): void
    {
        $connection = $this->createFlickrConnection();
        $account = StorageAccount::factory()->googlePhotos()->create();

        (new FanOutTransferBatchJob(
            TransferType::Upload,
            $connection->connection_key,
            storageAccountId: $account->id,
        ))->handle(
            app(PhotoDownloadService::class),
            app(PhotoUploadService::class),
            app(ConnectionQueryRepository::class),
            app(StorageAccountRepository::class),
        );

        $this->assertTrue(true);
    }
}
