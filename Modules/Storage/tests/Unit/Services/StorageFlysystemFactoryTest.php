<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Aws\S3\S3Client;
use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageFlysystemFactoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_r2_client_is_created_from_credentials(): void
    {
        $credentials = StorageAccount::factory()->r2()->make()->credentials ?? [];

        $client = app(StorageFlysystemFactory::class)->r2Client($credentials);

        $this->assertInstanceOf(S3Client::class, $client);
    }

    public function test_disk_for_google_photos_account_throws(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos uses direct API upload.');

        app(StorageFlysystemFactory::class)->diskForAccount($account);
    }

    public function test_verify_r2_credentials_calls_head_bucket(): void
    {
        $credentials = StorageAccount::factory()->r2()->make()->credentials ?? [];
        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headBucket')
            ->once()
            ->with(['Bucket' => $credentials['bucket']]);

        $factory = Mockery::mock(StorageFlysystemFactory::class)->makePartial();
        $factory->shouldReceive('r2Client')->once()->andReturn($client);
        $this->instance(StorageFlysystemFactory::class, $factory);

        $factory->verifyR2Credentials($credentials);
    }

    public function test_r2_disk_can_be_created(): void
    {
        $credentials = StorageAccount::factory()->r2()->make()->credentials ?? [];

        $disk = app(StorageFlysystemFactory::class)->r2Disk($credentials);

        $this->assertTrue(method_exists($disk, 'files'));
    }

    public function test_disk_for_r2_account_uses_r2_disk_builder(): void
    {
        $account = StorageAccount::factory()->r2()->create();

        $disk = app(StorageFlysystemFactory::class)->diskForAccount($account);

        $this->assertTrue(method_exists($disk, 'files'));
    }
}
