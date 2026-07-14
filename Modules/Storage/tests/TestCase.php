<?php

declare(strict_types=1);

namespace Modules\Storage\Tests;

use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase as HostTestCase;

abstract class TestCase extends HostTestCase
{
    use SafeRefreshDatabase;

    protected function loadModuleFixture(string $relativePath): string
    {
        $path = __DIR__.'/Fixtures/'.$relativePath;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Fixture not found: {$relativePath}");
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadJsonFixture(string $relativePath): array
    {
        $decoded = json_decode($this->loadModuleFixture($relativePath), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Fixture must decode to array: {$relativePath}");
        }

        return $decoded;
    }

    /**
     * Bind a real GoogleTokenService that uses a Guzzle MockHandler-backed Google Client
     * after refresh path tokenization. Prefer factory credentials with a future expires_at
     * so accessToken() short-circuits without refresh for most provider tests.
     *
     * @param  list<Response|array{0: int, 1?: array<string, string>, 2?: string}>  $responses
     */
    protected function bindGoogleClient(array $responses): void
    {
        $queue = array_map(
            static fn (Response|array $response): Response => $response instanceof Response
                ? $response
                : new Response($response[0], $response[1] ?? [], $response[2] ?? ''),
            $responses,
        );
        $handler = HandlerStack::create(new MockHandler($queue));
        $http = new GuzzleClient(['handler' => $handler]);
        $client = new GoogleClient;
        $client->setHttpClient($http);

        $tokens = new class(app(StorageAccountRepository::class), $client) extends GoogleTokenService
        {
            public function __construct(
                StorageAccountRepository $accounts,
                private readonly GoogleClient $googleClient,
            ) {
                parent::__construct($accounts);
            }

            public function clientForAccount(array $credentials, StorageAccount $account): GoogleClient
            {
                $accessToken = $this->accessToken($credentials, $account);
                $this->googleClient->setAccessToken([
                    'access_token' => $accessToken,
                ]);

                return $this->googleClient;
            }
        };

        $this->app->instance(GoogleTokenService::class, $tokens);
    }

    /**
     * Hand-rolled seam: real in-memory Flysystem disk behind StorageFlysystemFactory.
     *
     * @return array{disk: Filesystem, factory: StorageFlysystemFactory}
     */
    protected function bindInMemoryDisk(?callable $configureFactory = null): array
    {
        $adapter = new InMemoryFilesystemAdapter;
        $league = new LeagueFilesystem($adapter);
        $disk = new FilesystemAdapter($league, $adapter, ['driver' => 'memory']);

        $factory = \Mockery::mock(StorageFlysystemFactory::class);
        $factory->shouldReceive('diskForAccount')->andReturn($disk)->byDefault();

        if ($configureFactory !== null) {
            $configureFactory($factory, $disk);
        }

        $this->app->instance(StorageFlysystemFactory::class, $factory);

        return ['disk' => $disk, 'factory' => $factory];
    }
}
