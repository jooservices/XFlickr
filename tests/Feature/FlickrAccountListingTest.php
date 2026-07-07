<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrAccountListingTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_flickr_accounts_page_lists_presented_accounts(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => 'me@N01',
            'username' => 'me',
            'fullname' => 'Test Account',
        ]);

        $response = $this->get('/flickr/accounts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Flickr/Index')
            ->has('accounts', 1)
            ->where('accounts.0.public_id', $connection->public_id)
            ->where('accounts.0.nsid', 'me@N01')
            ->where('accounts.0.is_connected', true));
    }

    public function test_crawl_operations_page_lists_presented_accounts(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => 'me@N01',
            'username' => 'me',
        ]);

        $response = $this->get('/crawl/operations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Crawl/Operations')
            ->has('accounts', 1)
            ->where('accounts.0.public_id', $connection->public_id)
            ->where('accounts.0.nsid', 'me@N01')
            ->where('accounts.0.is_connected', true));
    }
}
