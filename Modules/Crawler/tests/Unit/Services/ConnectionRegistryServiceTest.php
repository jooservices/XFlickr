<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\ConnectionRegistryService;
use Modules\Crawler\Tests\TestCase;

final class ConnectionRegistryServiceTest extends TestCase
{
    private ConnectionRegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ConnectionRegistryService::class);
    }

    public function test_register_creates_connection_with_profile_fields(): void
    {
        $connection = $this->registry->register(
            connectionKey: '111@N01',
            tokenPayload: $this->sampleToken(),
            appProfile: 'main',
            username: 'alice',
            fullname: 'Alice Example',
        );

        $this->assertSame('111@N01', $connection->connection_key);
        $this->assertSame('alice', $connection->username);
        $this->assertSame('Alice Example', $connection->fullname);
        $this->assertTrue($connection->is_active);
        $this->assertNotNull($connection->connected_at);
        $this->assertNull($connection->disconnected_at);
    }

    public function test_activate_switches_active_connection(): void
    {
        $this->registry->register('111@N01', $this->sampleToken(), activate: true);
        $this->registry->register('222@N02', $this->sampleToken(), activate: false);

        $this->registry->activate('222@N02');

        $this->assertFalse(Connection::query()->where('connection_key', '111@N01')->value('is_active'));
        $this->assertTrue(Connection::query()->where('connection_key', '222@N02')->value('is_active'));
        $this->assertSame('222@N02', $this->registry->active()?->connection_key);
    }

    public function test_disconnect_clears_token_and_marks_inactive(): void
    {
        $this->registry->register('333@N03', $this->sampleToken());

        $this->registry->disconnect('333@N03');

        $connection = Connection::query()->where('connection_key', '333@N03')->first();
        $this->assertNotNull($connection);
        $this->assertSame('', $connection->token_payload);
        $this->assertFalse($connection->is_active);
        $this->assertNotNull($connection->disconnected_at);
    }

    public function test_list_returns_connections_ordered_by_connected_at(): void
    {
        $this->registry->register('aaa@N01', $this->sampleToken(), activate: false);
        $this->registry->register('bbb@N02', $this->sampleToken(), activate: false);

        $this->assertCount(2, $this->registry->list());
    }
}
