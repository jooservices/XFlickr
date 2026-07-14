<?php

declare(strict_types=1);

namespace Database\Factories\Crawler;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Crawler\Models\Connection;
use Tests\Support\CreatesFlickrConnection;

/**
 * App-side factory for the crawler Connection model (vendor HasFactory unavailable).
 * Prefer {@see CreatesFlickrConnection} in Feature/Unit tests.
 *
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = Connection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nsid = $this->flickrNsid();

        return [
            'public_id' => (string) Str::uuid(),
            'connection_key' => $nsid,
            'app_profile' => 'main',
            'token_payload' => json_encode([
                'oauthToken' => fake()->sha1(),
                'oauthTokenSecret' => fake()->sha1(),
                'userNsid' => $nsid,
            ], JSON_THROW_ON_ERROR),
            'username' => fake()->userName(),
            'fullname' => fake()->name(),
            'is_active' => true,
            'connected_at' => now(),
        ];
    }

    /**
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return Connection::query()->forceCreate(
            array_merge($this->definition(), is_array($attributes) ? $attributes : []),
        );
    }
}
