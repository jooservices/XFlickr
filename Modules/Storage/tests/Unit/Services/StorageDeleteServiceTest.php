<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageDeleteService;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageDeleteServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_delete_many_rejects_empty_item_ids(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one item id is required.');

        app(StorageDeleteService::class)->deleteMany($account, StorageDriver::GoogleDrive, []);
    }

    public function test_delete_many_filters_blank_ids_before_delegating(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $itemId = fake()->uuid();

        $handler = HandlerStack::create(new MockHandler([
            new Response(204),
        ]));
        $googleClient = new GoogleClient;
        $googleClient->setHttpClient(new GuzzleClient(['handler' => $handler]));
        $googleClient->setAccessToken([
            'access_token' => fake()->sha256(),
            'created' => time(),
            'expires_in' => 3600,
        ]);

        $this->mock(GoogleTokenService::class, function ($mock) use ($googleClient): void {
            $mock->shouldReceive('clientForAccount')->once()->andReturn($googleClient);
        });

        $result = app(StorageDeleteService::class)->deleteMany(
            $account,
            StorageDriver::GoogleDrive,
            ['', $itemId],
        );

        $this->assertSame([$itemId], $result['deleted']);
    }
}
