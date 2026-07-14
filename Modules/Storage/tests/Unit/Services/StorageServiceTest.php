<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Collection;
use Modules\Storage\Services\StorageService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_accounts_returns_collection(): void
    {
        $accounts = app(StorageService::class)->accounts();

        $this->assertInstanceOf(Collection::class, $accounts);
    }

    public function test_drivers_lists_all_storage_drivers(): void
    {
        $drivers = app(StorageService::class)->drivers();

        $this->assertNotEmpty($drivers);
        $this->assertArrayHasKey('value', $drivers->first());
        $this->assertArrayHasKey('label', $drivers->first());
    }

    public function test_apps_returns_collection(): void
    {
        $apps = app(StorageService::class)->apps();

        $this->assertInstanceOf(Collection::class, $apps);
    }

    public function test_redirects_returns_provider_map(): void
    {
        $redirects = app(StorageService::class)->redirects();

        $this->assertIsArray($redirects);
    }
}
