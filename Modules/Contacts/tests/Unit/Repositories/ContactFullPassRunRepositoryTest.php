<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Repositories;

use Modules\Contacts\Database\Factories\ContactFullPassRunFactory;
use Modules\Contacts\Repositories\ContactFullPassRunRepository;
use Modules\Spider\Contracts\ConcurrentRunGuard;
use Modules\Spider\Enums\SpiderRunStatus;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactFullPassRunRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_has_active_run_returns_true_when_running_full_pass_exists(): void
    {
        $connection = $this->createFlickrConnection();

        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        $repository = app(ContactFullPassRunRepository::class);

        $this->assertTrue($repository->hasActiveRun($connection->connection_key));
        $this->assertTrue($repository->findActiveForConnection($connection->connection_key)?->is($run));
    }

    public function test_has_active_run_returns_false_when_only_completed_or_no_runs_exist(): void
    {
        $connection = $this->createFlickrConnection();
        $otherConnection = $this->createFlickrConnection();

        ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Completed,
            'completed_at' => now(),
        ]);

        $repository = app(ContactFullPassRunRepository::class);

        $this->assertFalse($repository->hasActiveRun($connection->connection_key));
        $this->assertNull($repository->findActiveForConnection($connection->connection_key));
        $this->assertFalse($repository->hasActiveRun($otherConnection->connection_key));
    }

    public function test_container_resolves_concurrent_run_guard_to_repository(): void
    {
        $guard = app(ConcurrentRunGuard::class);

        $this->assertInstanceOf(ContactFullPassRunRepository::class, $guard);
    }
}
