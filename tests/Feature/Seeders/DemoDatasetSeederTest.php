<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Database\Seeders\DemoDatasetSeeder;
use Illuminate\Support\Facades\Artisan;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteAlbum;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class DemoDatasetSeederTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_demo_dataset_seeder_creates_expected_records(): void
    {
        Artisan::call('db:seed', ['--class' => DemoDatasetSeeder::class]);

        $connection = Connection::query()->where('username', DemoDatasetSeeder::DEMO_USERNAME)->first();

        $this->assertNotNull($connection);
        $this->assertTrue($connection->is_active);
        $this->assertSame(30, ConnectionContact::query()->where('connection_key', $connection->connection_key)->count());
        $this->assertSame(100, Photo::query()->count());
        $this->assertSame(2, Photoset::query()->count());
        $this->assertSame(1, Gallery::query()->count());

        $this->assertSame(1, TransferBatch::query()->where('status', 'completed')->count());
        $this->assertSame(1, TransferBatch::query()->where('status', 'failed')->count());
        $this->assertSame(8, TransferItem::query()->count());

        $this->assertSame(1, StorageAccount::query()->count());
        $this->assertSame(3, StorageRemoteAlbum::query()->count());
        $this->assertSame(5, StorageRemoteItem::query()->count());
    }

    public function test_demo_dataset_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => DemoDatasetSeeder::class]);
        Artisan::call('db:seed', ['--class' => DemoDatasetSeeder::class]);

        $this->assertSame(1, Connection::query()->where('username', DemoDatasetSeeder::DEMO_USERNAME)->count());
        $this->assertSame(100, Photo::query()->count());
    }
}
