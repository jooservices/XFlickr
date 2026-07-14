<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\R2;

use Illuminate\Contracts\Filesystem\Filesystem;
use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\R2\DeleteService;
use Modules\Storage\Support\StorageR2Config;
use Modules\Storage\Tests\TestCase;
use RuntimeException;

final class DeleteServiceTest extends TestCase
{
    public function test_delete_many_removes_existing_objects(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $itemId = 'album/photo.jpg';
        $key = StorageR2Config::from($account->credentials ?? [])->objectKey($itemId);

        ['disk' => $disk] = $this->bindInMemoryDisk();
        $disk->put($key, 'photo-bytes');

        $result = app(DeleteService::class)->deleteMany($account, [$itemId]);

        $this->assertSame([$itemId], $result['deleted']);
        $this->assertSame([], $result['failed']);
        $this->assertFalse($disk->exists($key));
    }

    public function test_delete_many_treats_missing_objects_as_deleted(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $itemId = 'missing.jpg';

        $this->bindInMemoryDisk();

        $result = app(DeleteService::class)->deleteMany($account, [$itemId]);

        $this->assertSame([$itemId], $result['deleted']);
    }

    public function test_delete_many_collects_failures_without_aborting_batch(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $failingId = 'broken.jpg';
        $successId = 'ok.jpg';

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(true);
        $disk->shouldReceive('delete')->andReturnUsing(function (string $key) use ($failingId): bool {
            if (str_contains($key, $failingId)) {
                throw new RuntimeException('Delete denied');
            }

            return true;
        });

        $this->bindInMemoryDisk(function ($factory) use ($disk): void {
            $factory->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $result = app(DeleteService::class)->deleteMany($account, [$failingId, $successId]);

        $this->assertSame([$successId], $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame($failingId, $result['failed'][0]['id']);
        $this->assertSame('Delete denied', $result['failed'][0]['message']);
    }
}
