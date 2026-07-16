<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Enums\IntegrityResolution;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Jobs\RunIntegrityScanJob;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Repositories\IntegrityAnomalyRepository;
use Modules\Transfer\Repositories\IntegrityScanRepository;
use Modules\Transfer\Repositories\StoredFileRepository;

final class IntegrityScanService
{
    public function __construct(
        private readonly IntegrityScanRepository $scans,
        private readonly IntegrityAnomalyRepository $anomalies,
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchService $transfers,
    ) {}

    public function startScan(): IntegrityScan
    {
        $scan = DB::transaction(fn (): IntegrityScan => $this->scans->createPending('local'));
        RunIntegrityScanJob::dispatch($scan->id)->afterCommit();

        return $scan;
    }

    public function find(string $uuid): ?IntegrityScan
    {
        return $this->scans->findByUuid($uuid);
    }

    public function anomalies(IntegrityScan $scan, int $limit): LengthAwarePaginator
    {
        return $this->anomalies->paginateUnresolved($scan->id, $limit);
    }

    /** @param list<string> $anomalyIds @return array{resolved_count: int, skipped_count: int} */
    public function resolve(IntegrityScan $scan, IntegrityResolution $resolution, array $anomalyIds): array
    {
        $result = DB::transaction(function () use ($scan, $resolution, $anomalyIds): array {
            $resolved = 0;
            $skipped = 0;
            $downloads = [];
            foreach ($this->anomalies->lockUnresolvedByUuids($scan->id, $anomalyIds) as $anomaly) {
                if (! $this->isValidResolution($resolution, $anomaly->type)) {
                    $skipped++;

                    continue;
                }
                if ($resolution === IntegrityResolution::Delete && is_string($anomaly->local_path) && Storage::disk($scan->disk)->exists($anomaly->local_path)) {
                    Storage::disk($scan->disk)->delete($anomaly->local_path);
                }
                if ($resolution === IntegrityResolution::Redownload && $anomaly->stored_file_id !== null) {
                    $file = $this->storedFiles->findById($anomaly->stored_file_id);
                    if ($file === null) {
                        $skipped++;

                        continue;
                    }
                    $this->storedFiles->markStatusAndPath($file->id, StoredFileStatus::Pending->value);
                    $downloads[(string) $file->source_owner][] = ['source_type' => (string) $file->source_type, 'source_id' => (string) $file->source_id, 'source_owner' => (string) $file->source_owner];
                }
                if ($resolution === IntegrityResolution::Import) {
                    if (! $this->importOrphan($scan, $anomaly->local_path, $anomaly->source_id, $anomaly->connection_key)) {
                        $skipped++;

                        continue;
                    }
                }
                $this->anomalies->resolve($anomaly->id, $resolution);
                $resolved++;
            }

            return ['resolved_count' => $resolved, 'skipped_count' => $skipped, 'downloads' => $downloads];
        });

        foreach ($result['downloads'] as $connectionKey => $items) {
            $this->transfers->queueDownloads($items, $connectionKey, $connectionKey, 'bulk', 'integrity_restore', 'Integrity Recovery');
        }

        return ['resolved_count' => $result['resolved_count'], 'skipped_count' => $result['skipped_count']];
    }

    private function isValidResolution(IntegrityResolution $resolution, IntegrityAnomalyType $type): bool
    {
        return match ($resolution) {
            IntegrityResolution::Delete, IntegrityResolution::Import => $type === IntegrityAnomalyType::Orphaned, IntegrityResolution::Redownload => $type === IntegrityAnomalyType::Missing
        };
    }

    private function importOrphan(IntegrityScan $scan, ?string $path, ?string $sourceId, ?string $connectionKey): bool
    {
        if (! is_string($path) || ! is_string($sourceId) || ! is_string($connectionKey) || ! Storage::disk($scan->disk)->exists($path)) {
            return false;
        }
        $disk = Storage::disk($scan->disk);
        $existing = $this->storedFiles->findOriginalBySourceId('flickr_photo', $sourceId);
        $bytes = $disk->size($path);
        $sha256 = hash_file('sha256', $disk->path($path)) ?: null;
        if ($existing === null) {
            $this->storedFiles->createStoredFile(['source_type' => 'flickr_photo', 'source_id' => $sourceId, 'source_owner' => $connectionKey, 'variant' => 'original', 'status' => StoredFileStatus::Completed->value, 'local_path' => $path, 'original_name' => basename($path), 'bytes' => $bytes, 'content_sha256' => $sha256, 'downloaded_at' => now()]);
        } else {
            $this->storedFiles->markStatusAndPath($existing->id, StoredFileStatus::Completed->value, $path, $bytes, $sha256);
        }

        return true;
    }
}
