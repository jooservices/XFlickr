<?php

declare(strict_types=1);

namespace Modules\Flickr\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\Flickr;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Services\FlickrClientFactory;
use JOOservices\XFlickrCrawler\Support\FlickrCrawlQueryParams;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;
use Throwable;

final class FlickrApiAuditCommand extends Command
{
    protected $signature = 'xflickr:flickr:audit-api
                            {connection_key? : Flickr NSID (defaults to active connection)}
                            {--contact= : Contact NSID to probe with people.getPhotos}
                            {--url= : Flickr profile URL to resolve NSID via flickr.urls.lookupUser}
                            {--photo-id= : Known photo id to verify visibility via photos.getInfo}';

    protected $description = 'Probe Flickr API methods for a connection and optional contact NSID';

    public function handle(FlickrClientFactory $clients): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No Flickr connection found.');

            return self::FAILURE;
        }

        $contactNsid = (string) ($this->option('contact') ?: '');
        $profileUrl = (string) ($this->option('url') ?: '');
        $photoId = trim((string) ($this->option('photo-id') ?: ''));

        try {
            $credentials = XFlickrConfig::appCredentials($connection->app_profile ?: 'main');
            $apiKeyHint = substr($credentials->apiKey, 0, 4).'…'.substr($credentials->apiKey, -4);
        } catch (Throwable $exception) {
            $this->error('App credentials missing: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Connection: {$connection->connection_key} ({$connection->username})");
        $this->line("Profile: {$connection->app_profile} · API key: {$apiKeyHint}");

        $client = $clients->forConnection($connection->connection_key);
        $anonymousClient = FlickrFactory::make(FlickrConfig::from([
            'apiKey' => $credentials->apiKey,
            'apiSecret' => $credentials->apiSecret,
        ]));

        $this->probe($client, 'flickr.test.login', []);
        $this->probe($client, 'flickr.contacts.getList', ['page' => 1, 'per_page' => 5]);
        $this->probeCrawl($client, 'flickr.people.getPhotos',
            FlickrCrawlQueryParams::peoplePhotos($connection->connection_key, 1, 5),
        );
        $this->probeCrawl($client, 'flickr.people.getPhotos',
            FlickrCrawlQueryParams::peoplePhotos('24662369@N07', 1, 5),
        );

        if ($profileUrl !== '') {
            $this->line('');
            $this->info("URL lookup: {$profileUrl}");
            $lookup = $this->probeWithResponse($client, 'flickr.urls.lookupUser', ['url' => $profileUrl]);
            $resolvedNsid = is_string($lookup['data']['user']['id'] ?? null) ? $lookup['data']['user']['id'] : null;
            if ($resolvedNsid !== null) {
                $this->line("  → resolved NSID: {$resolvedNsid}");
                if ($contactNsid !== '' && $contactNsid !== $resolvedNsid) {
                    $this->warn("  Contact NSID {$contactNsid} differs from URL lookup {$resolvedNsid}");
                }
                if ($contactNsid === '') {
                    $contactNsid = $resolvedNsid;
                }
            }
        }

        if ($contactNsid !== '') {
            $this->line('');
            $this->info("Contact probes: {$contactNsid}");

            $info = $this->probeWithResponse($client, 'flickr.people.getInfo', ['user_id' => $contactNsid]);
            $photosCount = $info['data']['person']['photos']['count']['_content'] ?? null;
            $pathAlias = $info['data']['person']['path_alias'] ?? null;
            $username = $info['data']['person']['username']['_content'] ?? null;
            $this->line("  getInfo · username={$username} · path_alias={$pathAlias} · photos.count={$photosCount}");

            if (is_string($pathAlias) && $pathAlias !== '') {
                $byUsername = $this->probeWithResponse($client, 'flickr.people.findByUsername', ['username' => $pathAlias]);
                $usernameNsid = $byUsername['data']['user']['id'] ?? $byUsername['data']['user']['nsid'] ?? null;
                if (is_string($usernameNsid) && $usernameNsid !== $contactNsid) {
                    $this->warn("  findByUsername('{$pathAlias}') → {$usernameNsid} (differs from URL NSID; use NSID or urls.lookupUser)");
                }
            }

            $this->probeCrawl($client, 'flickr.people.getPhotos',
                FlickrCrawlQueryParams::peoplePhotos($contactNsid, 1, 5),
            );
            $this->probe($client, 'flickr.people.getPhotos', array_merge(
                FlickrCrawlQueryParams::peoplePhotos($contactNsid, 1, 5),
                ['safe_search' => 1],
            ));
            $this->probeCrawl($client, 'flickr.favorites.getList',
                FlickrCrawlQueryParams::favoritesList($contactNsid, 1, 5),
            );
            $this->probeCrawl($client, 'flickr.photosets.getList',
                FlickrCrawlQueryParams::photosetsList($contactNsid, 1, 5),
            );
            $this->probeCrawl($client, 'flickr.galleries.getList',
                FlickrCrawlQueryParams::galleriesList($contactNsid, 1, 5),
            );
            $this->probe($anonymousClient, 'flickr.people.getPublicPhotos', [
                'user_id' => $contactNsid,
                'page' => 1,
                'per_page' => 5,
            ]);
        }

        if ($photoId !== '') {
            $this->line('');
            $this->info("Photo visibility probe: {$photoId}");
            $signedPhoto = $this->probeWithResponse($client, 'flickr.photos.getInfo', ['photo_id' => $photoId]);
            $anonPhoto = $this->probeWithResponse($anonymousClient, 'flickr.photos.getInfo', ['photo_id' => $photoId]);

            if ($signedPhoto['ok'] && $anonPhoto['ok']) {
                $this->line('  <fg=green>✓</> App key can read photo '.$photoId);
            } else {
                $this->warn('  photos.getInfo failed for photo '.$photoId.' with the configured app key/token.');
            }
        }

        return self::SUCCESS;
    }

    private function resolveConnection(): ?Connection
    {
        $key = (string) ($this->argument('connection_key') ?? '');

        if ($key !== '') {
            return Connection::query()->where('connection_key', $key)->first();
        }

        return Connection::query()
            ->whereNull('disconnected_at')
            ->where('token_payload', '!=', '')
            ->where('is_active', true)
            ->first()
            ?? Connection::query()
                ->whereNull('disconnected_at')
                ->where('token_payload', '!=', '')
                ->orderByDesc('connected_at')
                ->first();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function probeCrawl(Flickr $client, string $method, array $params): ?int
    {
        $started = hrtime(true);

        try {
            $response = FlickrCrawlQueryParams::call($client, $method, $params);
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $data = $response->data ?? [];
            $total = $this->extractTotal($method, $data);

            if ($response->ok) {
                $totalLabel = $total === null ? '—' : (string) $total;
                $this->line("<fg=green>✓</> {$method} (crawl) · {$ms}ms · total={$totalLabel}");
            } else {
                $code = $response->error?->code;
                $message = $response->error !== null ? $response->error->message : 'unknown error';
                $this->line("<fg=red>✗</> {$method} (crawl) · {$ms}ms · code={$code} · {$message}");
            }

            return $total;
        } catch (Throwable $exception) {
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $this->line("<fg=red>✗</> {$method} (crawl) · {$ms}ms · {$exception->getMessage()}");

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function probe(Flickr $client, string $method, array $params): ?int
    {
        $result = $this->probeWithResponse($client, $method, $params);
        $ms = $result['ms'];
        $total = $result['total'];
        $ok = $result['ok'];
        $code = $result['code'];
        $message = $result['message'];

        if ($ok) {
            $totalLabel = $total === null ? '—' : (string) $total;
            $this->line("<fg=green>✓</> {$method} · {$ms}ms · total={$totalLabel}");
        } else {
            $this->line("<fg=red>✗</> {$method} · {$ms}ms · code={$code} · {$message}");
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok: bool, ms: int, total: ?int, code: ?int, message: string, data: array<string, mixed>}
     */
    private function probeWithResponse(Flickr $client, string $method, array $params): array
    {
        $started = hrtime(true);

        try {
            $response = $client->raw()->call($method, $params);
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);
            $data = $response->data ?? [];

            return [
                'ok' => $response->ok,
                'ms' => $ms,
                'total' => $this->extractTotal($method, $data),
                'code' => $response->error?->code,
                'message' => $response->error !== null ? $response->error->message : 'unknown error',
                'data' => $data,
            ];
        } catch (Throwable $exception) {
            $ms = (int) round((hrtime(true) - $started) / 1_000_000);

            return [
                'ok' => false,
                'ms' => $ms,
                'total' => null,
                'code' => null,
                'message' => $exception->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractTotal(string $method, array $data): ?int
    {
        if ($method === 'flickr.contacts.getList') {
            return isset($data['contacts']['total']) ? (int) $data['contacts']['total'] : null;
        }

        if ($method === 'flickr.photosets.getList') {
            return isset($data['photosets']['total']) ? (int) $data['photosets']['total'] : null;
        }

        if ($method === 'flickr.galleries.getList') {
            return isset($data['galleries']['total']) ? (int) $data['galleries']['total'] : null;
        }

        if (str_contains($method, 'getPhotos') || str_contains($method, 'favorites')) {
            return isset($data['photos']['total']) ? (int) $data['photos']['total'] : null;
        }

        return null;
    }
}
