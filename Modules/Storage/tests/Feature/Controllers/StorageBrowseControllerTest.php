<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers;

use Modules\Storage\Enums\StorageDriver;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageBrowseControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_google_photos_browse_page_renders_inertia_props(): void
    {
        $this->assertBrowsePage('/storages/google-photos', StorageDriver::GooglePhotos, 'Album');
    }

    public function test_google_drive_browse_page_renders_inertia_props(): void
    {
        $this->assertBrowsePage('/storages/google-drive', StorageDriver::GoogleDrive, 'Folder');
    }

    public function test_onedrive_browse_page_renders_inertia_props(): void
    {
        $this->assertBrowsePage('/storages/onedrive', StorageDriver::OneDrive, 'Folder');
    }

    public function test_r2_browse_page_renders_inertia_props(): void
    {
        $this->assertBrowsePage('/storages/r2', StorageDriver::R2, 'Folder');
    }

    public function test_browse_pages_require_authentication(): void
    {
        auth()->logout();

        $this->get('/storages/google-photos')->assertRedirect();
    }

    private function assertBrowsePage(string $uri, StorageDriver $driver, string $containerLabel): void
    {
        $response = $this->get($uri);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storage/Browse')
            ->where('provider', $driver->value)
            ->where('provider_slug', $driver->routeSlug())
            ->where('provider_label', $driver->label())
            ->where('container_label', $containerLabel));
    }
}
