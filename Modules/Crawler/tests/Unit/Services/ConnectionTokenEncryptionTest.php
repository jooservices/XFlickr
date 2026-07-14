<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JOOservices\Flickr\Flickr;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Crawler\Tests\TestCase;

final class ConnectionTokenEncryptionTest extends TestCase
{
    public function test_token_payload_is_encrypted_at_rest(): void
    {
        $payload = json_encode([
            'oauthToken' => 'token',
            'oauthTokenSecret' => 'secret',
            'userNsid' => '123@N01',
        ], JSON_THROW_ON_ERROR);

        $connection = Connection::query()->create([
            'connection_key' => 'encrypt-test',
            'app_profile' => 'default',
            'token_payload' => $payload,
        ]);

        $raw = (string) DB::table($connection->getTable())
            ->where('id', $connection->id)
            ->value('token_payload');

        $this->assertNotSame($payload, $raw);
        $this->assertSame($payload, $connection->fresh()->token_payload);
        $this->assertSame($payload, Crypt::decryptString($raw));
    }

    public function test_flickr_client_factory_reads_encrypted_connection_token(): void
    {
        $payload = json_encode([
            'oauthToken' => 'token',
            'oauthTokenSecret' => 'secret',
            'userNsid' => '123@N01',
        ], JSON_THROW_ON_ERROR);

        Connection::query()->create([
            'connection_key' => 'encrypt-client',
            'app_profile' => 'default',
            'token_payload' => $payload,
        ]);

        $factory = app(FlickrClientFactory::class);
        $client = $factory->forConnection('encrypt-client');

        $this->assertInstanceOf(Flickr::class, $client);
    }
}
