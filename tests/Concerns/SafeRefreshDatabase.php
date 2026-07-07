<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RefreshDatabaseGuard;

/** RefreshDatabase with dev-Docker guard — use this instead of Illuminate RefreshDatabase. */
trait SafeRefreshDatabase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        RefreshDatabaseGuard::assertSafeTarget();
    }
}
