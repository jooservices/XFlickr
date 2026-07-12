<?php

declare(strict_types=1);

namespace Modules\Flickr\Events;

use JOOservices\LaravelEvents\EventSourcing\Concerns\HasEventSourcingDefaults;
use JOOservices\LaravelEvents\EventSourcing\Contracts\EventSourcingInterface;

final class FlickrAccountConnected implements EventSourcingInterface
{
    use HasEventSourcingDefaults;

    public function __construct(
        public readonly string $connectionKey,
        public readonly string $appProfile,
        public readonly ?string $username = null,
    ) {}

    public function payload(): array
    {
        return [
            'connection_key' => $this->connectionKey,
            'app_profile' => $this->appProfile,
            'username' => $this->username,
        ];
    }

    public function aggregateId(): ?string
    {
        return $this->connectionKey;
    }
}
