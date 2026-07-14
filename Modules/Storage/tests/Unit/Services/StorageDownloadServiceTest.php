<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use Mockery;
use Modules\Storage\Dto\StorageStreamResult;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageDownloadService;
use Modules\Storage\Support\StorageR2Config;
use Modules\Storage\Tests\TestCase;
use RuntimeException;

final class StorageDownloadServiceTest extends TestCase
{
    public function test_open_stream_rejects_non_r2_providers(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Download is not supported for this provider yet.');

        app(StorageDownloadService::class)->openStreamForAccount($account, 'photo.jpg');
    }

    public function test_open_stream_returns_null_when_remote_file_missing(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $this->bindInMemoryDisk();

        $result = app(StorageDownloadService::class)->openStreamForAccount($account, 'missing.jpg');

        $this->assertNull($result);
    }

    public function test_open_stream_returns_stream_result_for_existing_file(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey('photos/image.jpg');

        ['disk' => $disk] = $this->bindInMemoryDisk();
        $disk->put($key, 'image-bytes');

        $result = app(StorageDownloadService::class)->openStreamForAccount($account, 'photos/image.jpg');

        $this->assertInstanceOf(StorageStreamResult::class, $result);
        $this->assertSame('image.jpg', $result->filename);
    }

    public function test_download_to_path_writes_file_and_returns_metadata(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey('photos/image.jpg');

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->once()->andReturn([
            'ETag' => '"etag-123"',
            'ContentLength' => 14,
        ]);

        ['disk' => $disk] = $this->bindInMemoryDisk(function ($factory) use ($client): void {
            $factory->shouldReceive('r2Client')->once()->andReturn($client);
        });
        $disk->put($key, 'download-bytes');

        $localPath = sys_get_temp_dir().'/xflickr-storage-download-'.fake()->uuid().'.bin';

        try {
            $result = app(StorageDownloadService::class)->downloadToPath(
                $account->id,
                'photos/image.jpg',
                $localPath,
            );
        } finally {
            @unlink($localPath);
        }

        $this->assertSame($localPath, $result['path']);
        $this->assertSame(14, $result['size']);
        $this->assertSame('etag-123', $result['etag']);
    }

    public function test_download_to_path_throws_when_stream_read_fails(): void
    {
        $account = StorageAccount::factory()->r2()->create();

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('readStream')->once()->andReturn(false);

        $this->bindInMemoryDisk(function ($factory) use ($disk): void {
            $factory->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read remote file');

        app(StorageDownloadService::class)->downloadToPath(
            $account->id,
            'missing.jpg',
            sys_get_temp_dir().'/xflickr-missing.bin',
        );
    }
}
