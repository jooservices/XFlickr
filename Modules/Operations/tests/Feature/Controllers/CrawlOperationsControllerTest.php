<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class CrawlOperationsControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_crawl_operations_page_lists_presented_accounts(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->get('/operations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Crawl/Operations')
            ->has('accounts', 1)
            ->where('accounts.0.public_id', $connection->public_id)
            ->where('accounts.0.nsid', $connection->connection_key)
            ->where('accounts.0.is_connected', true));
    }

    public function test_legacy_crawl_operations_path_redirects(): void
    {
        $response = $this->get('/crawl/operations');

        $response->assertRedirect('/operations');
    }
}
