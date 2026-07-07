<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Events\FlickrAccountConnected;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class FlickrAccountConnectedEventTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_event_carries_connection_payload(): void
    {
        Event::fake([FlickrAccountConnected::class]);

        $event = new FlickrAccountConnected('me@N01', 'main', 'demo-user');

        event($event);

        Event::assertDispatched(FlickrAccountConnected::class, function (FlickrAccountConnected $dispatched): bool {
            return $dispatched->connectionKey === 'me@N01'
                && $dispatched->appProfile === 'main'
                && $dispatched->username === 'demo-user'
                && $dispatched->payload() === [
                    'connection_key' => 'me@N01',
                    'app_profile' => 'main',
                    'username' => 'demo-user',
                ];
        });
    }
}
