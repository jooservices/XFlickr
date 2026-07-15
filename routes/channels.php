<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('operations', function ($user): bool {
    return $user !== null;
});
