<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageBrowseService;
use Modules\Storage\Tests\TestCase;
use RuntimeException;

final class StorageBrowseServiceTest extends TestCase
{
    public function test_accounts_for_provider_returns_presented_rows(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        $rows = app(StorageBrowseService::class)->accountsForProvider(StorageDriver::GooglePhotos);

        $this->assertCount(1, $rows);
        $this->assertSame($account->id, $rows[0]['id']);
        $this->assertSame(StorageDriver::GooglePhotos->value, $rows[0]['provider']);
    }

    public function test_browse_throws_when_account_missing_for_provider(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Storage account not found for this provider.');

        app(StorageBrowseService::class)->browse(
            StorageDriver::OneDrive,
            $account->id,
        );
    }
}
