<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Storage\StorageBrowseDriver;
use App\Contracts\Storage\StorageDeleteDriver;
use App\Enums\StorageDriver;
use App\Services\Storage\StorageDriverRegistry;
use Tests\TestCase;

final class StorageDriverRegistryTest extends TestCase
{
    public function test_it_resolves_browse_and_delete_capabilities_for_each_driver(): void
    {
        $registry = app(StorageDriverRegistry::class);

        foreach (StorageDriver::cases() as $driver) {
            $this->assertInstanceOf(StorageBrowseDriver::class, $registry->browseDriver($driver));
            $this->assertInstanceOf(StorageDeleteDriver::class, $registry->deleteDriver($driver));
        }
    }
}
