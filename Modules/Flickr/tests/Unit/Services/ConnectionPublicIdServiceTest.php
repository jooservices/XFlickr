<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Modules\Crawler\Models\Connection;
use Modules\Flickr\Services\ConnectionPublicIdService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;

final class ConnectionPublicIdServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    private ConnectionPublicIdService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ConnectionPublicIdService::class);
    }

    public function test_ensure_returns_existing_public_id_unchanged(): void
    {
        $connection = $this->createFlickrConnection();
        $existing = $connection->public_id;

        $result = $this->service->ensure($connection);

        $this->assertSame($existing, $result);
        $this->assertSame($existing, $connection->fresh()->public_id);
    }

    public function test_ensure_assigns_uuid_when_public_id_missing(): void
    {
        $connection = $this->createFlickrConnection();
        $connection->forceFill(['public_id' => ''])->save();

        $result = $this->service->ensure($connection->fresh());

        $this->assertNotSame('', $result);
        $this->assertSame($result, $connection->fresh()->public_id);
    }

    public function test_ensure_is_idempotent_for_generated_ids(): void
    {
        $connection = $this->createFlickrConnection();
        $connection->forceFill(['public_id' => null])->save();
        $connection = $connection->fresh();

        $first = $this->service->ensure($connection);
        $second = $this->service->ensure($connection);

        $this->assertSame($first, $second);
    }

    public function test_ensure_skips_persist_for_unsaved_connection(): void
    {
        $connection = new Connection([
            'connection_key' => FlickrNsid::fake(),
            'username' => fake()->userName(),
        ]);

        $result = $this->service->ensure($connection);

        $this->assertNotSame('', $result);
        $this->assertSame($result, $connection->public_id);
        $this->assertNull($connection->getKey());
    }
}
