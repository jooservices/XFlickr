<?php

declare(strict_types=1);

namespace Modules\Storage\Console\Commands;

use Illuminate\Console\Command;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Enums\ConnectionCheckStatus;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Services\ConnectionVerificationService;

final class VerifyConnectionsCommand extends Command
{
    protected $signature = 'xflickr:storage:verify-connections
        {--account= : Verify a single storage account by ID}
        {--provider= : Verify only accounts for one provider (google, google_photos, onedrive, r2)}';

    protected $description = 'Verify credentials, authorization, and connectivity for connected storage accounts';

    public function handle(ConnectionVerificationService $verifier): int
    {
        $reports = $this->resolveReports($verifier);
        if ($reports === null) {
            return self::FAILURE;
        }

        if ($reports === []) {
            $this->error('No storage accounts found. Connect one in Settings → Storages.');

            return self::FAILURE;
        }

        $unhealthy = 0;
        foreach ($reports as $report) {
            $this->displayReport($report);
            if (! $report->healthy()) {
                $unhealthy++;
            }
        }

        if ($unhealthy > 0) {
            $this->error(sprintf('%d of %d storage account(s) failed verification.', $unhealthy, count($reports)));

            return self::FAILURE;
        }

        $this->info(sprintf('All %d storage account(s) verified.', count($reports)));

        return self::SUCCESS;
    }

    /**
     * @return list<ConnectionReport>|null
     */
    private function resolveReports(ConnectionVerificationService $verifier): ?array
    {
        $accountId = $this->option('account');
        if (is_string($accountId) && $accountId !== '') {
            $report = $verifier->verifyAccount((int) $accountId);
            if ($report === null) {
                $this->error("Storage account [{$accountId}] not found.");

                return null;
            }

            return [$report];
        }

        $provider = $this->option('provider');
        if (is_string($provider) && $provider !== '') {
            $driver = StorageDriver::tryFrom($provider);
            if ($driver === null) {
                $this->error("Unknown storage provider [{$provider}].");

                return null;
            }

            return $verifier->verifyAll($driver);
        }

        return $verifier->verifyAll();
    }

    private function displayReport(ConnectionReport $report): void
    {
        $this->line("<options=bold>{$report->providerLabel} — {$report->accountLabel} (#{$report->accountId})</>");

        foreach ($report->checks as $check) {
            $this->displayCheck($check);
        }

        $this->newLine();
    }

    private function displayCheck(ConnectionCheck $check): void
    {
        match ($check->status) {
            ConnectionCheckStatus::Passed => $this->info("  ✓ {$check->name}: {$check->message}"),
            ConnectionCheckStatus::Warning => $this->warn("  ⚠ {$check->name}: {$check->message}"),
            ConnectionCheckStatus::Failed => $this->error("  ✗ {$check->name}: {$check->message}"),
        };

        foreach ($check->details as $detail) {
            $this->line('      '.$detail);
        }
    }
}
