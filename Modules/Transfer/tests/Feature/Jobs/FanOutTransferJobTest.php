<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Jobs;

use App\Repositories\Crawler\ConnectionQueryRepository;
use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\Queue;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageService;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Jobs\FanOutTransferJob;
use Modules\Transfer\Services\PhotoTransferService;
use Modules\Transfer\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class FanOutTransferJobTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_missing_connection_is_ignored(): void
    {
        Queue::fake();
        $job = new FanOutTransferJob(TransferType::Download, 'missing@N01');

        $this->handle($job);

        Queue::assertNothingPushed();
    }

    public function test_download_fans_out_catalog_photos(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        PhotoFactory::new()->create(['owner_nsid' => $connection->connection_key]);
        $job = new FanOutTransferJob(TransferType::Download, $connection->connection_key);

        $this->handle($job);

        Queue::assertPushed(DownloadFileJob::class, 1);
    }

    public function test_upload_requires_existing_storage_account(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        PhotoFactory::new()->create(['owner_nsid' => $connection->connection_key]);

        $this->handle(new FanOutTransferJob(TransferType::Upload, $connection->connection_key));
        $this->handle(new FanOutTransferJob(TransferType::Upload, $connection->connection_key, null, 999999));

        Queue::assertNothingPushed();
    }

    public function test_upload_queues_missing_local_download_for_valid_account(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection(['connection_key' => 'connection-a']);
        $account = StorageAccount::factory()->googleDrive()->create();
        PhotoFactory::new()->create(['owner_nsid' => $connection->connection_key]);

        $this->handle(new FanOutTransferJob(
            TransferType::Upload,
            $connection->connection_key,
            null,
            $account->id,
            true,
        ));

        Queue::assertPushed(DownloadFileJob::class, 1);
    }

    private function handle(FanOutTransferJob $job): void
    {
        $job->handle(
            app(PhotoTransferService::class),
            app(ConnectionQueryRepository::class),
            app(StorageService::class),
        );
    }
}
