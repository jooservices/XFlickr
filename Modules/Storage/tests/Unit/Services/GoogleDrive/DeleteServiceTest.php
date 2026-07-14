<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\GoogleDrive;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GoogleDrive\DeleteService;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class DeleteServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_delete_many_removes_items_and_collects_failures(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $successId = fake()->uuid();
        $missingId = fake()->uuid();

        $handler = HandlerStack::create(new MockHandler([
            new Response(204),
            new Response(404, [], (string) json_encode([
                'error' => ['message' => 'File not found'],
            ], JSON_THROW_ON_ERROR)),
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

        $result = app(DeleteService::class)->deleteMany(
            $account,
            [$successId, $missingId],
        );

        $this->assertSame([$successId], $result['deleted']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame($missingId, $result['failed'][0]['id']);
    }
}
