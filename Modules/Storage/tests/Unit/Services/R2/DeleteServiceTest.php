<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\R2;

use Illuminate\Contracts\Filesystem\Filesystem;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\R2\DeleteService;
use Modules\Storage\Services\StorageFlysystemFactory;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class DeleteServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_delete_many_removes_existing_objects(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $itemId = 'album/photo.jpg';

        $disk = $this->createMock(Filesystem::class);
        $disk->expects($this->once())->method('exists')->willReturn(true);
        $disk->expects($this->once())->method('delete')->with($account->credentials['prefix'].'/'.$itemId);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $result = app(DeleteService::class)->deleteMany($account, [$itemId]);

        $this->assertSame([$itemId], $result['deleted']);
        $this->assertSame([], $result['failed']);
    }

    public function test_delete_many_treats_missing_objects_as_deleted(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $itemId = 'missing.jpg';

        $disk = $this->createMock(Filesystem::class);
        $disk->expects($this->once())->method('exists')->willReturn(false);
        $disk->expects($this->never())->method('delete');

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $result = app(DeleteService::class)->deleteMany($account, [$itemId]);

        $this->assertSame([$itemId], $result['deleted']);
    }

    public function test_delete_many_collects_failures_without_aborting_batch(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $failingId = 'broken.jpg';
        $successId = 'ok.jpg';

        $disk = $this->createMock(Filesystem::class);
        $disk->method('exists')->willReturn(true);
        $disk->method('delete')->willReturnCallback(function (string $key) use ($failingId): bool {
            if (str_contains($key, $failingId)) {
                throw new RuntimeException('Delete denied');
            }

            return true;
        });

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
        });

        $result = app(DeleteService::class)->deleteMany($account, [$failingId, $successId]);

        $this->assertSame([$successId], $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame($failingId, $result['failed'][0]['id']);
        $this->assertSame('Delete denied', $result['failed'][0]['message']);
    }
}
