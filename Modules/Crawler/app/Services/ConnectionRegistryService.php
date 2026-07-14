<?php

declare(strict_types=1);

namespace Modules\Crawler\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Repositories\ConnectionRepository;
use Modules\Crawler\Support\XFlickrConfig;

final class ConnectionRegistryService
{
    public function __construct(
        private readonly ConnectionRepository $connections,
    ) {}

    /**
     * @param  array<string, mixed>|string  $tokenPayload
     */
    public function register(
        string $connectionKey,
        array|string $tokenPayload,
        ?string $appProfile = null,
        ?string $username = null,
        ?string $fullname = null,
        bool $activate = true,
    ): Connection {
        $profile = $appProfile !== null
            ? XFlickrConfig::sanitizeProfileSlug($appProfile)
            : XFlickrConfig::defaultAppProfile();

        $payload = is_array($tokenPayload)
            ? json_encode($tokenPayload, JSON_THROW_ON_ERROR)
            : $tokenPayload;

        if ($activate) {
            $this->connections->deactivateAll();
        }

        return $this->connections->updateOrCreateByKey($connectionKey, [
            'app_profile' => $profile,
            'token_payload' => $payload,
            'username' => $username,
            'fullname' => $fullname,
            'connected_at' => now(),
            'disconnected_at' => null,
            'is_active' => $activate,
        ]);
    }

    public function disconnect(string $connectionKey): void
    {
        $this->connections->disconnect($connectionKey);
    }

    public function activate(string $connectionKey): void
    {
        $this->connections->activate($connectionKey);
    }

    public function active(): ?Connection
    {
        return $this->connections->findActive();
    }

    /**
     * @return Collection<int, Connection>
     */
    public function list(): Collection
    {
        return $this->connections->listOrderedByConnectedAt();
    }

    public function find(string $connectionKey): ?Connection
    {
        return $this->connections->findByKey($connectionKey);
    }
}
