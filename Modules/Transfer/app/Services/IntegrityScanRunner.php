<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Repositories\IntegrityAnomalyRepository;
use Modules\Transfer\Repositories\IntegrityScanRepository;
use Modules\Transfer\Repositories\StoredFileRepository;
use Throwable;

final class IntegrityScanRunner
{
    public function __construct(private readonly IntegrityScanRepository $scans, private readonly IntegrityAnomalyRepository $anomalies, private readonly StoredFileRepository $storedFiles) {}

    public function run(int $scanId): void
    {
        $lock = Cache::lock('xflickr:integrity:scan', 3600);
        if (! $lock->get()) {
            return;
        }
        try {
            $scan = $this->scans->findById($scanId);
            if (! $scan instanceof IntegrityScan) {
                return;
            }
            $this->scans->markRunning($scanId);
            $disk = Storage::disk($scan->disk);
            $records = $this->storedFiles->completedOriginals();
            $byPath = $records->filter(fn ($file): bool => is_string($file->local_path) && $file->local_path !== '')->keyBy('local_path');
            $orphaned = 0;
            $rows = [];
            foreach ($disk->allFiles('flickr') as $path) {
                if (! preg_match('#^flickr/([^/]+)/photos/([a-zA-Z0-9_-]+)_[a-zA-Z0-9]+\.[a-zA-Z0-9]+$#', $path, $matches) || $byPath->has($path)) {
                    continue;
                }
                $rows[] = ['integrity_scan_id' => $scanId, 'uuid' => (string) Str::uuid(), 'type' => IntegrityAnomalyType::Orphaned->value, 'local_path' => $path, 'stored_file_id' => null, 'connection_key' => $matches[1], 'source_id' => $matches[2], 'created_at' => now(), 'updated_at' => now()];
                $orphaned++;
            }
            foreach ($records as $file) {
                if (! is_string($file->local_path) || $file->local_path === '' || $disk->exists($file->local_path)) {
                    continue;
                }
                $rows[] = ['integrity_scan_id' => $scanId, 'uuid' => (string) Str::uuid(), 'type' => IntegrityAnomalyType::Missing->value, 'stored_file_id' => $file->id, 'local_path' => $file->local_path, 'connection_key' => $file->source_owner, 'source_id' => $file->source_id, 'created_at' => now(), 'updated_at' => now()];
            }
            $this->anomalies->insertForScan($rows);
            $this->scans->markCompleted($scanId, $orphaned, count($rows) - $orphaned);
        } catch (Throwable $exception) {
            $this->fail($scanId, $exception);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function fail(int $scanId, Throwable $exception): void
    {
        $this->scans->markFailed($scanId, 'Integrity scan failed. Review application logs for details.');
    }
}
