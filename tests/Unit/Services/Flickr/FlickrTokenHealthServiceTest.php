<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Flickr;

use Modules\Crawler\Models\Connection;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Tests\TestCase;

final class FlickrTokenHealthServiceTest extends TestCase
{
    public function test_probe_returns_invalid_when_connection_has_no_token(): void
    {
        $connection = new Connection([
            'connection_key' => '94529704@N02',
            'token_payload' => '',
            'disconnected_at' => null,
        ]);

        $result = app(FlickrTokenHealthService::class)->probe($connection);

        $this->assertFalse($result->valid);
        $this->assertSame('Connection has no OAuth token.', $result->errorMessage);
    }
}
