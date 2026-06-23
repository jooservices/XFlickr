<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use App\Enums\StoredFileStatus;
use App\Repositories\StoredFileRepository;
use App\Repositories\TransferBatchRepository;
use Illuminate\Support\Facades\Storage;
use JOOservices\XFlickrCrawler\Models\Connection;

final class ContactDownloadCountsService
{
    public function __construct(
        private readonly StoredFileRepository $storedFiles,
        private readonly TransferBatchRepository $batches,
    ) {}

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, array{
     *     total: int,
     *     failed: int,
     *     processing: bool,
     *     batch_completed?: int,
     *     batch_total?: int
     * }>
     */
    public function forContacts(Connection $connection, array $contactNsids): array
    {
        $counts = [];

        foreach ($contactNsids as $contactNsid) {
            $counts[$contactNsid] = [
                'total' => 0,
                'failed' => 0,
                'processing' => false,
            ];
        }

        if ($contactNsids === []) {
            return $counts;
        }

        $storedFileList = $this->storedFiles->originalsForOwners($contactNsids);

        foreach ($storedFileList->groupBy('owner_nsid') as $ownerNsid => $files) {
            if (! isset($counts[$ownerNsid])) {
                continue;
            }

            $failed = 0;
            $downloaded = 0;

            foreach ($files as $file) {
                if ($file->status === StoredFileStatus::Failed->value) {
                    $failed++;

                    continue;
                }

                if ($file->status !== StoredFileStatus::Completed->value) {
                    continue;
                }

                $downloaded++;

                if ($file->local_path === null || ! Storage::exists($file->local_path)) {
                    $failed++;
                }
            }

            $counts[$ownerNsid]['total'] = $downloaded;
            $counts[$ownerNsid]['failed'] = $failed;
        }

        $runningBatches = $this->batches->runningDownloadsForSubjects($connection->connection_key, $contactNsids);

        foreach ($runningBatches->groupBy('subject_nsid') as $subjectNsid => $batchGroup) {
            if (! isset($counts[$subjectNsid])) {
                continue;
            }

            $counts[$subjectNsid]['processing'] = true;
            $counts[$subjectNsid]['batch_completed'] = (int) $batchGroup->sum('completed_count');
            $counts[$subjectNsid]['batch_total'] = (int) $batchGroup->sum('total_count');
        }

        return $counts;
    }
}
