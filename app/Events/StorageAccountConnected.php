<?php

declare(strict_types=1);

namespace App\Events;

use JOOservices\LaravelEvents\EventSourcing\Concerns\HasEventSourcingDefaults;
use JOOservices\LaravelEvents\EventSourcing\Contracts\EventSourcingInterface;

final class StorageAccountConnected implements EventSourcingInterface
{
    use HasEventSourcingDefaults;

    public function __construct(
        public readonly int $accountId,
        public readonly string $provider,
        public readonly ?string $label = null,
    ) {}

    public function payload(): array
    {
        return [
            'account_id' => $this->accountId,
            'provider' => $this->provider,
            'label' => $this->label,
        ];
    }

    public function aggregateId(): ?string
    {
        return (string) $this->accountId;
    }
}
