<?php

declare(strict_types=1);

namespace Tests\Support;

use Database\Factories\Crawler\ConnectionFactory;
use Illuminate\Support\Str;
use Mockery;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Dto\FlickrTokenHealthResult;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Tests\TestCase;

trait CreatesFlickrConnection
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createFlickrConnection(array $attributes = []): Connection
    {
        if (array_key_exists('nsid', $attributes)) {
            $attributes['connection_key'] = $attributes['nsid'];
            unset($attributes['nsid']);
        }

        if (! array_key_exists('connection_key', $attributes)) {
            $attributes['connection_key'] = FlickrNsid::fake();
        }

        $nsid = (string) $attributes['connection_key'];

        if (! array_key_exists('token_payload', $attributes)) {
            $attributes['token_payload'] = [
                'oauthToken' => fake()->sha1(),
                'oauthTokenSecret' => fake()->sha1(),
                'userNsid' => $nsid,
            ];
        }

        if (is_array($attributes['token_payload'])) {
            $payload = $attributes['token_payload'];
            if (! isset($payload['userNsid'])) {
                $payload['userNsid'] = $nsid;
            }
            $attributes['token_payload'] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        if (! array_key_exists('public_id', $attributes)) {
            $attributes['public_id'] = (string) Str::uuid();
        }

        if (! array_key_exists('username', $attributes)) {
            $attributes['username'] = fake()->userName();
        }

        if (! array_key_exists('fullname', $attributes)) {
            $attributes['fullname'] = fake()->name();
        }

        /** @var Connection $connection */
        $connection = ConnectionFactory::new()->create($attributes);

        return $connection;
    }

    protected function mockFlickrTokenHealth(bool $valid = true, ?string $errorMessage = null): void
    {
        /** @var TestCase $this */
        $mock = Mockery::mock(FlickrTokenHealthService::class);
        $mock->shouldReceive('probe')
            ->andReturn(new FlickrTokenHealthResult(
                valid: $valid,
                errorMessage: $valid ? null : ($errorMessage ?? 'Invalid auth token'),
            ));
        $mock->shouldReceive('forgetCache')->zeroOrMoreTimes();
        $mock->shouldReceive('forgetCacheForKey')->zeroOrMoreTimes();
        $this->app->instance(FlickrTokenHealthService::class, $mock);
    }
}
