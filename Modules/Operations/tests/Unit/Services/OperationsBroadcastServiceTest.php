<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Operations\Events\OperationsOverviewChanged;
use Modules\Operations\Services\OperationsBroadcastService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class OperationsBroadcastServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    #[Test]
    public function overview_broadcast_is_throttled_to_one_per_second(): void
    {
        $this->createFlickrConnection();
        Cache::flush();
        Event::fake([OperationsOverviewChanged::class]);

        $service = app(OperationsBroadcastService::class);
        $service->broadcastOverviewChanged();
        $service->broadcastOverviewChanged();

        Event::assertDispatchedTimes(OperationsOverviewChanged::class, 1);
    }
}
