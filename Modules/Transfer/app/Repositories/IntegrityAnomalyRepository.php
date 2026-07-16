<?php

declare(strict_types=1);

namespace Modules\Transfer\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Modules\Transfer\Enums\IntegrityResolution;
use Modules\Transfer\Models\IntegrityAnomaly;

/** @extends EloquentRepository<IntegrityAnomaly> */
final class IntegrityAnomalyRepository extends EloquentRepository
{
    public function __construct(IntegrityAnomaly $model)
    {
        parent::__construct($model);
    }

    /** @param list<array<string, mixed>> $rows */
    public function insertForScan(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $this->newQuery()->insert($chunk);
        }
    }

    public function paginateUnresolved(int $scanId, int $limit): LengthAwarePaginator
    {
        return $this->newQuery()->where('integrity_scan_id', $scanId)->unresolved()->orderBy('id')->paginate($limit);
    }

    /** @param list<string> $uuids @return list<IntegrityAnomaly> */
    public function lockUnresolvedByUuids(int $scanId, array $uuids): array
    {
        return $this->newQuery()->where('integrity_scan_id', $scanId)->whereIn('uuid', $uuids)->unresolved()->lockForUpdate()->get()->all();
    }

    public function resolve(int $id, IntegrityResolution $resolution): void
    {
        $this->newQuery()->whereKey($id)->update(['resolution' => $resolution, 'resolved_at' => now()]);
    }
}
