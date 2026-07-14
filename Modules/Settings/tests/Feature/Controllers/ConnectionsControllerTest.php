<?php

declare(strict_types=1);

namespace Modules\Settings\Tests\Feature\Controllers;

use Modules\Storage\Models\StorageAccount;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ConnectionsControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_connections_page_lists_flickr_accounts_by_default(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->get('/connections');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Connections/Index')
            ->where('provider', 'flickr')
            ->missing('section')
            ->has('flickr_accounts', 1)
            ->has('flickr_apps')
            ->has('storage_accounts')
            ->has('storage_apps')
            ->where('flickr_accounts.0.public_id', $connection->public_id)
            ->where('flickr_accounts.0.nsid', $connection->connection_key)
            ->where('has_completed_crawl', false));
    }

    public function test_connections_storage_provider_renders(): void
    {
        StorageAccount::factory()->create([
            'provider' => 'r2',
            'label' => 'Test R2',
        ]);

        $this->get('/connections?provider=storage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Connections/Index')
                ->where('provider', 'storage')
                ->missing('section')
                ->has('storage_accounts', 1));
    }

    public function test_legacy_section_query_is_ignored(): void
    {
        $this->get('/connections?provider=storage&section=apps')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Connections/Index')
                ->where('provider', 'storage')
                ->missing('section'));
    }

    public function test_flickr_accounts_path_redirects_to_connections(): void
    {
        $this->get('/flickr/accounts?section=apps')
            ->assertRedirect(route('connections.index', [
                'provider' => 'flickr',
            ]));
    }

    public function test_settings_flickr_and_storage_tabs_redirect_to_connections(): void
    {
        $this->get('/settings?tab=flickr')
            ->assertRedirect(route('connections.index', ['provider' => 'flickr']));

        $this->get('/settings?tab=storage')
            ->assertRedirect(route('connections.index', ['provider' => 'storage']));
    }
}
