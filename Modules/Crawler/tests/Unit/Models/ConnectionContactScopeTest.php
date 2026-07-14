<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Models;

use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class ConnectionContactScopeTest extends TestCase
{
    public function test_for_connection_scope_match_and_non_match(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $match = ConnectionContact::query()->forceCreate([
            'connection_key' => $matchKey,
            'contact_nsid' => FlickrNsid::fake(),
            'discovered_at' => now(),
        ]);
        ConnectionContact::query()->forceCreate([
            'connection_key' => $otherKey,
            'contact_nsid' => FlickrNsid::fake(),
            'discovered_at' => now(),
        ]);

        $this->assertTrue(ConnectionContact::query()->forConnection($matchKey)->whereKey($match->id)->exists());
        $this->assertFalse(ConnectionContact::query()->forConnection($otherKey)->whereKey($match->id)->exists());
    }
}
