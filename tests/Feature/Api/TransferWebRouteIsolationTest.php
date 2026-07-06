<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferWebRouteIsolationTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_api_does_not_expose_redirecting_transfer_web_routes(): void
    {
        $connection = $this->createFlickrConnection();

        $this->postJson('/api/flickr/accounts/'.$connection->public_id.'/download')->assertNotFound();
        $this->postJson('/api/flickr/accounts/'.$connection->public_id.'/upload')->assertNotFound();
    }
}
