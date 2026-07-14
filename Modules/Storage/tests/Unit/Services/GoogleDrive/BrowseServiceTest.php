<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\GoogleDrive;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Services\GoogleDrive\BrowseService;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class BrowseServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_browse_lists_folders_and_image_files(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $folderId = fake()->uuid();
        $fileId = fake()->uuid();

        $this->bindGoogleClient([
            [
                'files' => [[
                    'id' => $folderId,
                    'name' => fake()->words(2, true),
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'thumbnailLink' => 'https://example.com/folder-thumb',
                ]],
            ],
            [
                'files' => [[
                    'id' => $fileId,
                    'name' => fake()->word().'.jpg',
                    'mimeType' => 'image/jpeg',
                    'size' => '2048',
                    'modifiedTime' => '2026-01-01T00:00:00Z',
                    'webViewLink' => 'https://drive.google.com/file/'.$fileId,
                    'thumbnailLink' => 'https://example.com/file-thumb',
                ]],
            ],
        ]);

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: null,
            includeAlbums: true,
            includeItems: true,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame($folderId, $result->albums[0]['id']);
        $this->assertCount(1, $result->items);
        $this->assertSame($fileId, $result->items[0]['id']);
        $this->assertSame('image/jpeg', $result->items[0]['mime_type']);
    }

    public function test_browse_uses_folder_parent_query_when_container_id_is_set(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $containerId = fake()->uuid();

        $this->bindGoogleClient([
            [
                'files' => [[
                    'id' => fake()->uuid(),
                    'name' => 'nested.png',
                    'mimeType' => 'image/png',
                ]],
            ],
        ]);

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: $containerId,
            includeAlbums: false,
            includeItems: true,
        );

        $this->assertSame([], $result->albums);
        $this->assertCount(1, $result->items);
    }

    public function test_browse_can_skip_items(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $this->bindGoogleClient([
            [
                'files' => [[
                    'id' => fake()->uuid(),
                    'name' => 'Album only',
                    'mimeType' => 'application/vnd.google-apps.folder',
                ]],
            ],
        ]);

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: null,
            includeAlbums: true,
            includeItems: false,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame([], $result->items);
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     */
    private function bindGoogleClient(array $payloads): void
    {
        $responses = array_map(
            fn (array $payload): Response => new Response(
                200,
                ['Content-Type' => 'application/json'],
                (string) json_encode($payload, JSON_THROW_ON_ERROR),
            ),
            $payloads,
        );

        $handler = HandlerStack::create(new MockHandler($responses));
        $googleClient = new GoogleClient;
        $googleClient->setHttpClient(new GuzzleClient(['handler' => $handler]));
        $googleClient->setAccessToken([
            'access_token' => fake()->sha256(),
            'created' => time(),
            'expires_in' => 3600,
        ]);

        $accounts = app(StorageAccountRepository::class);
        $this->app->instance(
            GoogleTokenService::class,
            new class($googleClient, $accounts) extends GoogleTokenService
            {
                public function __construct(
                    private readonly GoogleClient $boundClient,
                    StorageAccountRepository $accounts,
                ) {
                    parent::__construct($accounts);
                }

                public function clientForAccount(array $credentials, StorageAccount $account): GoogleClient
                {
                    $this->accessToken($credentials, $account);

                    return $this->boundClient;
                }
            },
        );
    }
}
