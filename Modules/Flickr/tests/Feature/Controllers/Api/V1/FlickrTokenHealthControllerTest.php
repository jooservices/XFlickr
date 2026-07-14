<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Controllers\Api\V1;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;

final class FlickrTokenHealthControllerTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_show_returns_null_token_valid_for_disconnected_account(): void
    {
        $connection = $this->createFlickrConnection();
        $connection->update([
            'disconnected_at' => now(),
            'token_payload' => '',
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/token-health');

        $response->assertOk();
        $response->assertJsonPath('data.token_valid', null);
    }

    public function test_show_returns_null_token_valid_when_token_payload_is_empty(): void
    {
        $connection = $this->createFlickrConnection([
            'token_payload' => '',
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/token-health');

        $response->assertOk();
        $response->assertJsonPath('data.token_valid', null);
    }

    public function test_show_probes_connected_account_token_health(): void
    {
        $connection = $this->createFlickrConnection();
        $this->useRealTokenHealthService(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'ok',
                'user' => ['id' => $connection->connection_key, 'username' => 'healthy-user'],
            ]),
        );

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/token-health');

        $response->assertOk();
        $response->assertJsonPath('data.token_valid', true);
        $response->assertJsonPath('data.user_nsid', $connection->connection_key);
    }

    public function test_show_reports_invalid_token_from_probe(): void
    {
        $connection = $this->createFlickrConnection();
        $this->useRealTokenHealthService(
            FakeFlickrTransport::new()->pushJson([
                'stat' => 'fail',
                'code' => 99,
                'message' => 'Invalid auth token',
            ]),
        );

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/token-health');

        $response->assertOk();
        $response->assertJsonPath('data.token_valid', false);
        $response->assertJsonPath('data.error_message', 'Invalid auth token');
        $response->assertJsonPath('data.error_code', 99);
    }

    private function useRealTokenHealthService(FakeFlickrTransport $transport): void
    {
        $this->bindFakeFlickrTransport($transport);
        $this->app->forgetInstance(FlickrTokenHealthService::class);
    }
}
