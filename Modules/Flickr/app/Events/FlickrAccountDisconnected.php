<?php

declare(strict_types=1);

namespace Modules\Flickr\Events;

use JOOservices\LaravelEvents\EventSourcing\Concerns\HasEventSourcingDefaults;
use JOOservices\LaravelEvents\EventSourcing\Contracts\EventSourcingInterface;

final class FlickrAccountDisconnected implements EventSourcingInterface
{
    use HasEventSourcingDefaults;

    public function __construct(
        public readonly string $connectionKey,
    ) {}

    public function payload(): array
    {
        return [
            'connection_key' => $this->connectionKey,
        ];
    }

    public function aggregateId(): string
    {
        return $this->connectionKey;
    }
}
