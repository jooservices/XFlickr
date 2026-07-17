<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers\Api\V1;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ActivityFeedControllerGuestTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/operations/activities')->assertUnauthorized();
    }
}
