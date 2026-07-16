<?php

declare(strict_types=1);

namespace Modules\Transfer\Console\Commands;

use Illuminate\Console\Command;
use Modules\Transfer\Services\IntegrityScanService;

final class ScanStorageIntegrityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xflickr:transfer:integrity-scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan local disk storage vs database to check for integrity mismatches';

    /**
     * Execute the console command.
     */
    public function handle(IntegrityScanService $service): int
    {
        $scan = $service->startScan();
        $this->info("Integrity scan queued: {$scan->uuid}");

        return 0;
    }
}
