<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use JOOservices\XFlickrCrawler\Models\Connection;

trait CreatesFlickrConnection
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createFlickrConnection(array $attributes = []): Connection
    {
        $nsid = (string) ($attributes['connection_key'] ?? $attributes['nsid'] ?? 'me@N01');
        unset($attributes['nsid']);

        $tokenPayload = $attributes['token_payload'] ?? [
            'oauthToken' => 't',
            'oauthTokenSecret' => 's',
            'userNsid' => $nsid,
        ];
        unset($attributes['token_payload']);

        if (is_array($tokenPayload)) {
            $tokenPayload = json_encode($tokenPayload, JSON_THROW_ON_ERROR);
        }

        $publicId = (string) ($attributes['public_id'] ?? (string) Str::uuid());
        unset($attributes['public_id']);

        return Connection::query()->forceCreate(array_merge([
            'public_id' => $publicId,
            'connection_key' => $nsid,
            'app_profile' => 'main',
            'token_payload' => $tokenPayload,
            'username' => null,
            'fullname' => null,
            'is_active' => true,
            'connected_at' => now(),
        ], $attributes));
    }
}
