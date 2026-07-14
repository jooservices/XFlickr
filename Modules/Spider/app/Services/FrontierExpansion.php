<?php

declare(strict_types=1);

namespace Modules\Spider\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Spider\Contracts\FrontierRepositoryContract;
use Modules\Spider\Contracts\RunWriteRepository;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;

/**
 * Shared frontier lifecycle helpers for spider and full-pass planners.
 */
final class FrontierExpansion
{
    /**
     * @param  list<string>  $contactNsids
     */
    public function enqueueDiscovered(
        Model $run,
        FrontierRepositoryContract $frontier,
        RunWriteRepository $runs,
        array $contactNsids,
        int $depth,
    ): void {
        $maxDepth = (int) $run->getAttribute('max_depth');
        $runId = (int) $run->getAttribute('id');

        if ($depth > $maxDepth || $contactNsids === []) {
            return;
        }

        $known = array_flip($frontier->knownContactNsids($runId));
        $discovered = 0;

        foreach ($contactNsids as $contactNsid) {
            if (isset($known[$contactNsid])) {
                continue;
            }

            if ($frontier->enqueue($runId, $contactNsid, $depth)) {
                $discovered++;
            }
        }

        if ($discovered > 0) {
            $runs->incrementRun($run, 'contacts_discovered', $discovered);
        }
    }

    public function maybeCompleteRun(
        Model $run,
        FrontierRepositoryContract $frontier,
        RunWriteRepository $runs,
    ): void {
        $status = $run->getAttribute('status');

        if ($status !== SpiderRunStatus::Running) {
            return;
        }

        $runId = (int) $run->getAttribute('id');

        if ($frontier->countByStatus($runId, SpiderFrontierStatus::Pending) > 0) {
            return;
        }

        if ($frontier->countByStatus($runId, SpiderFrontierStatus::Queued) > 0) {
            return;
        }

        $this->completeRun($run, $runs);
    }

    public function completeRun(Model $run, RunWriteRepository $runs): void
    {
        $runs->updateRun($run, [
            'status' => SpiderRunStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function pauseMissingConnection(Model $run, RunWriteRepository $runs): void
    {
        $runs->updateRun($run, [
            'status' => SpiderRunStatus::Paused,
            'paused_at' => now(),
        ]);
    }
}
