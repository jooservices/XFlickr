<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class SyncOperationsControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_sync_page_lists_connected_accounts(): void
    {
        $connection = $this->createFlickrConnection();

        $this->get('/sync')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Crawl/Sync')
                ->has('accounts', 1)
                ->where('accounts.0.public_id', $connection->public_id));
    }
}
