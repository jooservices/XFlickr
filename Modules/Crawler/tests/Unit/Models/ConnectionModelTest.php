<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Models;

use Modules\Crawler\Models\Connection;
use Modules\Crawler\Tests\TestCase;

final class ConnectionModelTest extends TestCase
{
    public function test_route_key_is_connection_key(): void
    {
        $connection = new Connection([
            'connection_key' => '12037949629@N01',
        ]);

        $this->assertSame('connection_key', $connection->getRouteKeyName());
        $this->assertSame('12037949629@N01', $connection->getRouteKey());
    }
}
