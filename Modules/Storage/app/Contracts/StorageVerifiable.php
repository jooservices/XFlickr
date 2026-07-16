<?php

declare(strict_types=1);

namespace Modules\Storage\Contracts;

use Modules\Storage\Dto\ConnectionReport;

interface StorageVerifiable
{
    public function verify(): ConnectionReport;
}
