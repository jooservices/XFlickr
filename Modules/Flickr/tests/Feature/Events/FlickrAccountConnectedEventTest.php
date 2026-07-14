<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Events;

use Illuminate\Support\Facades\Event;
use Modules\Flickr\Events\FlickrAccountConnected;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class FlickrAccountConnectedEventTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_event_carries_connection_payload(): void
    {
        Event::fake([FlickrAccountConnected::class]);

        $connectionKey = FlickrNsid::fake();
        $username = fake()->userName();
        $event = new FlickrAccountConnected($connectionKey, 'main', $username);

        event($event);

        Event::assertDispatched(FlickrAccountConnected::class, function (FlickrAccountConnected $dispatched) use ($connectionKey, $username): bool {
            return $dispatched->connectionKey === $connectionKey
                && $dispatched->appProfile === 'main'
                && $dispatched->username === $username
                && $dispatched->payload() === [
                    'connection_key' => $connectionKey,
                    'app_profile' => 'main',
                    'username' => $username,
                ];
        });
    }
}
