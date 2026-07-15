<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class OperationsBroadcastChannelTest extends TestCase
{
    use SafeRefreshDatabase;

    #[Test]
    public function operations_channel_is_registered(): void
    {
        $channels = Broadcast::getChannels();

        $this->assertArrayHasKey('operations', $channels);
    }

    #[Test]
    public function operations_channel_allows_authenticated_user(): void
    {
        $user = User::factory()->create();
        $channels = Broadcast::getChannels();

        $callback = $channels['operations'];
        $this->assertTrue($callback($user));
    }

    #[Test]
    public function operations_channel_denies_guest(): void
    {
        $channels = Broadcast::getChannels();

        $callback = $channels['operations'];
        $this->assertFalse($callback(null));
    }
}
