<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Feature\Controllers;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Crawler\Models\Photoset;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class CatalogWebControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_photos_page_renders_with_active_account(): void
    {
        $connection = $this->createFlickrConnection(['is_active' => true]);

        $response = $this->get(route('photos.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Photos')
            ->has('account')
            ->where('account.nsid', $connection->connection_key));
    }

    public function test_photosets_page_renders_for_connection_route(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->get(route('flickr.accounts.photosets', $connection->public_id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Photosets')
            ->where('account.nsid', $connection->connection_key));
    }

    public function test_galleries_and_favorites_pages_render(): void
    {
        $this->createFlickrConnection(['is_active' => true]);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Catalog/Galleries'));

        $this->get(route('favorites.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Catalog/Favorites'));
    }

    public function test_show_photoset_renders_presented_payload(): void
    {
        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => (string) fake()->numerify('########'),
            'owner_nsid' => $ownerNsid,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(6),
            'photo_count' => 3,
        ]);

        $response = $this->get(route('photosets.show', $photoset->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Photosets/Show')
            ->where('photoset.id', $photoset->id)
            ->where('photoset.title', $photoset->title));
    }

    public function test_show_photoset_returns_not_found_for_missing_record(): void
    {
        $this->get(route('photosets.show', 999999))->assertNotFound();
    }
}
