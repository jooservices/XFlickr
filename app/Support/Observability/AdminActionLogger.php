<?php

declare(strict_types=1);

namespace App\Support\Observability;

use JOOservices\LaravelLogging\Facades\ActivityLog;

final class AdminActionLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, array $properties = [], ?string $message = null): void
    {
        $log = ActivityLog::audit()
            ->action($action)
            ->properties($properties)
            ->withRequest()
            ->sync();

        if ($message !== null) {
            $log->message($message);
        }

        $log->dispatch();
    }
}
