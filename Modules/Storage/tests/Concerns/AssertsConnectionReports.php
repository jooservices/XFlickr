<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Concerns;

use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;

trait AssertsConnectionReports
{
    protected function check(ConnectionReport $report, string $name): ConnectionCheck
    {
        $check = $this->findCheck($report, $name);
        $this->assertNotNull($check, "Check [{$name}] missing from report.");

        return $check;
    }

    protected function findCheck(ConnectionReport $report, string $name): ?ConnectionCheck
    {
        foreach ($report->checks as $check) {
            if ($check->name === $name) {
                return $check;
            }
        }

        return null;
    }

    protected function detailsContain(ConnectionCheck $check, string $needle): bool
    {
        foreach ($check->details as $detail) {
            if (str_contains($detail, $needle)) {
                return true;
            }
        }

        return false;
    }
}
