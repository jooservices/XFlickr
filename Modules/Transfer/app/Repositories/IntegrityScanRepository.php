<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Models\IntegrityScan;

/** @extends EloquentRepository<IntegrityScan> */
final class IntegrityScanRepository extends EloquentRepository
{
    public function __construct(IntegrityScan $model)
    {
        parent::__construct($model);
    }

    public function createPending(string $disk): IntegrityScan
    {
        return $this->newQuery()->create(['disk' => $disk, 'status' => IntegrityScanStatus::Pending]);
    }

    public function findByUuid(string $uuid): ?IntegrityScan
    {
        return $this->newQuery()->where('uuid', $uuid)->first();
    }

    public function findById(int $id): ?IntegrityScan
    {
        $scan = $this->newQuery()->find($id);

        return $scan instanceof IntegrityScan ? $scan : null;
    }

    public function markRunning(int $id): void
    {
        $this->newQuery()->whereKey($id)->update(['status' => IntegrityScanStatus::Running, 'started_at' => now(), 'error_message' => null]);
    }

    public function markCompleted(int $id, int $orphaned, int $missing): void
    {
        $this->newQuery()->whereKey($id)->update(['status' => IntegrityScanStatus::Completed, 'orphaned_count' => $orphaned, 'missing_count' => $missing, 'finished_at' => now()]);
    }

    public function markFailed(int $id, string $message): void
    {
        $this->newQuery()->whereKey($id)->update(['status' => IntegrityScanStatus::Failed, 'error_message' => $message, 'finished_at' => now()]);
    }
}
