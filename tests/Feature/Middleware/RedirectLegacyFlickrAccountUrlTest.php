<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use Illuminate\Support\Str;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class RedirectLegacyFlickrAccountUrlTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_redirects_legacy_nsid_web_path_to_public_id(): void
    {
        $connection = $this->createFlickrConnection([
            'public_id' => (string) Str::uuid(),
        ]);

        $encodedNsid = urlencode($connection->connection_key);

        $response = $this->get('/flickr/accounts/'.$encodedNsid.'/contacts');

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id.'/contacts');
    }

    public function test_redirects_legacy_nsid_web_path_preserving_query_string(): void
    {
        $connection = $this->createFlickrConnection([
            'public_id' => (string) Str::uuid(),
        ]);

        $encodedNsid = urlencode($connection->connection_key);

        $response = $this->get('/flickr/accounts/'.$encodedNsid.'/contacts?search=alpha');

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id.'/contacts?search=alpha');
    }

    public function test_passes_through_uuid_paths_without_redirect(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->get('/flickr/accounts/'.$connection->public_id.'/contacts');

        $response->assertOk();
    }

    public function test_passes_through_unknown_legacy_segment(): void
    {
        $response = $this->get('/flickr/accounts/unknown-not-a-uuid/contacts');

        $response->assertNotFound();
    }
}
