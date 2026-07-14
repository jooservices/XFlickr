<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageDownloadService;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Support\StorageStreamResult;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageDownloadServiceTest extends TestCase
{
    use SafeRefreshDatabase;

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
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->once()->andReturn(false);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $result = app(StorageDownloadService::class)->openStreamForAccount($account, 'missing.jpg');

        $this->assertNull($result);
    }

    public function test_open_stream_returns_stream_result_for_existing_file(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $stream = fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);
        fwrite($stream, 'image-bytes');
        rewind($stream);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->once()->andReturn(true);
        $disk->shouldReceive('readStream')->once()->andReturn($stream);
        $disk->shouldReceive('mimeType')->once()->andReturn('image/jpeg');

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $result = app(StorageDownloadService::class)->openStreamForAccount($account, 'photos/image.jpg');

        $this->assertInstanceOf(StorageStreamResult::class, $result);
        $this->assertSame('image.jpg', $result->filename);
        $this->assertSame('image/jpeg', $result->mimeType);
    }

    public function test_download_to_path_writes_file_and_returns_metadata(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $stream = fopen('php://temp', 'rb+');
        $this->assertIsResource($stream);
        fwrite($stream, 'download-bytes');
        rewind($stream);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('readStream')->once()->andReturn($stream);

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->once()->andReturn([
            'ETag' => '"etag-123"',
            'ContentLength' => 14,
        ]);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk, $client): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

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

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
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
