<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class TransferWebRouteIsolationTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_api_does_not_expose_redirecting_transfer_web_routes(): void
    {
        $connection = $this->createFlickrConnection();

        $this->postJson('/api/v1/flickr/accounts/'.$connection->public_id.'/download')->assertNotFound();
        $this->postJson('/api/v1/flickr/accounts/'.$connection->public_id.'/upload')->assertNotFound();
    }
}
