<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Controllers;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrAccountControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function tearDown(): void
    {
        if (app()->bound('config-store') && RuntimeConfig::has('xflickr.global_pause')) {
            RuntimeConfig::forget('xflickr.global_pause');
            RuntimeConfig::refresh();
        }

        parent::tearDown();
    }

    public function test_flickr_accounts_index_redirects_to_connections_hub(): void
    {
        $this->createFlickrConnection();

        $this->get('/flickr/accounts')
            ->assertRedirect(route('connections.index', [
                'provider' => 'flickr',
            ]));
    }

    public function test_flickr_accounts_legacy_section_redirects_to_connections_hub(): void
    {
        $this->get('/flickr/accounts?section=apps')
            ->assertRedirect(route('connections.index', [
                'provider' => 'flickr',
            ]));
    }

    public function test_flickr_account_show_page_renders_account_detail_placeholder(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->get('/flickr/accounts/'.$connection->public_id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Flickr/Show')
            ->where('account.public_id', $connection->public_id)
            ->where('account.nsid', $connection->connection_key)
            ->where('account.username', $connection->username));
    }

    public function test_account_crawl_is_blocked_when_global_pause_is_active(): void
    {
        $connection = $this->createFlickrConnection();

        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/crawl', [
            'types' => ['photos'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Global crawl pause is active. Resume from the header to start crawls.');
        $this->assertDatabaseMissing('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
        ]);
    }
}
