<?php

declare(strict_types=1);

namespace Modules\Storage\Adapters;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use Modules\Storage\Contracts\StorageVerifiable;
use Modules\Storage\Dto\ConnectionCheck;
use Modules\Storage\Dto\ConnectionReport;
use Modules\Storage\Dto\StorageBrowseResult;
use Modules\Storage\Dto\StorageDeleteOptions;
use Modules\Storage\Dto\StorageListOptions;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\OAuthConnectionChecks;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\Tokens\MicrosoftTokenService;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;

final class OneDriveAdapter extends AbstractFlysystemAdapter implements StorageVerifiable
{
    private const string GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        StorageAccount $account,
        StorageFlysystemFactory $flysystem,
        private readonly MicrosoftTokenService $tokens,
        private readonly OAuthConnectionChecks $oauthChecks,
        private readonly StorageApiLogger $apiLogger,
    ) {
        parent::__construct($account, $flysystem);
    }

    public function delete(array $itemIds, ?StorageDeleteOptions $options = null): array
    {
        $credentials = $this->account->credentials ?? [];
        $accessToken = $this->tokens->accessToken($credentials, $this->account);

        $deleted = [];
        $failed = [];

        foreach ($itemIds as $itemId) {
            $endpoint = self::GRAPH_BASE.'/me/drive/items/'.urlencode($itemId);
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->delete($endpoint);
            $this->apiLogger->logRequest(
                'onedrive',
                'DELETE',
                $endpoint,
                $startedAt,
                $response,
                null,
                ['account_id' => $this->account->id, 'item_id' => $itemId],
            );

            if ($response->successful() || $response->status() === 404) {
                $deleted[] = $itemId;

                continue;
            }

            $message = $response->json('error.message');

            $failed[] = [
                'id' => $itemId,
                'message' => is_string($message) && $message !== '' ? $message : 'OneDrive delete failed.',
            ];
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    public function list(?StorageListOptions $options = null): StorageBrowseResult
    {
        $options ??= new StorageListOptions;
        $credentials = $this->account->credentials ?? [];
        $accessToken = $this->tokens->accessToken($credentials, $this->account);

        $path = $options->containerId !== null && $options->containerId !== ''
            ? "/me/drive/items/{$options->containerId}/children"
            : '/me/drive/root/children';

        $query = [
            '$top' => min($options->perPage, 100),
            '$select' => 'id,name,folder,file,size,lastModifiedDateTime,webUrl,thumbnails',
        ];

        $albumResult = $options->includeAlbums
            ? $this->fetchAlbumPage($accessToken, $path, $query, $options->albumPageToken, $options->itemPageToken)
            : ['albums' => [], 'nextPageToken' => null];

        $itemResult = $options->includeItems
            ? $this->fetchItemPage($accessToken, $path, $query, $options->itemPageToken)
            : ['items' => [], 'nextPageToken' => null];

        return new StorageBrowseResult(
            albums: $albumResult['albums'],
            items: $itemResult['items'],
            albumNextPageToken: $albumResult['nextPageToken'],
            itemNextPageToken: $itemResult['nextPageToken'],
        );
    }

    public function verify(): ConnectionReport
    {
        $driver = StorageDriver::OneDrive;

        if ($this->account->provider !== $driver->value) {
            throw new RuntimeException('Account is not a OneDrive storage account.');
        }

        $authorization = $this->oauthChecks->authorization($this->account);
        $checks = [
            $this->oauthChecks->oauthApp($this->account, $driver),
            $authorization,
        ];

        if ($this->oauthChecks->probeAllowed($authorization)) {
            $checks[] = $this->driveCheck();
        }

        return new ConnectionReport(
            accountId: $this->account->id,
            accountLabel: $this->account->label,
            provider: $driver->value,
            providerLabel: $driver->label(),
            checks: $checks,
        );
    }

    // ---- Browse internals ----

    /**
     * @param  array<string, mixed>  $query
     * @return array{albums: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function fetchAlbumPage(
        string $accessToken,
        string $path,
        array $query,
        ?string $albumPageToken,
        ?string $itemPageToken,
    ): array {
        if ($albumPageToken !== null && $albumPageToken !== '') {
            $folderResponse = $this->loggedGet($accessToken, $albumPageToken);
        } elseif ($itemPageToken === null || $itemPageToken === '') {
            $folderResponse = $this->loggedGet(
                $accessToken,
                self::GRAPH_BASE.$path,
                array_merge($query, [
                    '$filter' => 'folder ne null',
                ]),
            );
        } else {
            return ['albums' => [], 'nextPageToken' => null];
        }

        if (! $folderResponse->successful()) {
            throw new RuntimeException($this->errorMessage($folderResponse));
        }

        $folderPayload = $folderResponse->json();

        return [
            'albums' => $this->foldersFromPayload(is_array($folderPayload) ? $folderPayload : []),
            'nextPageToken' => isset($folderPayload['@odata.nextLink']) ? (string) $folderPayload['@odata.nextLink'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{items: list<array<string, mixed>>, nextPageToken: string|null}
     */
    private function fetchItemPage(
        string $accessToken,
        string $path,
        array $query,
        ?string $itemPageToken,
    ): array {
        if ($itemPageToken !== null && $itemPageToken !== '') {
            $fileResponse = $this->loggedGet($accessToken, $itemPageToken);
        } else {
            $fileResponse = $this->loggedGet(
                $accessToken,
                self::GRAPH_BASE.$path,
                array_merge($query, [
                    '$filter' => 'file ne null',
                ]),
            );
        }

        if (! $fileResponse->successful()) {
            throw new RuntimeException($this->errorMessage($fileResponse));
        }

        $filePayload = $fileResponse->json();

        return [
            'items' => $this->filesFromPayload(is_array($filePayload) ? $filePayload : []),
            'nextPageToken' => isset($filePayload['@odata.nextLink']) ? (string) $filePayload['@odata.nextLink'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function loggedGet(string $accessToken, string $url, array $query = []): Response
    {
        $startedAt = microtime(true);
        $response = $query === []
            ? Http::withToken($accessToken)->get($url)
            : Http::withToken($accessToken)->get($url, $query);
        $this->apiLogger->logRequest(
            'onedrive',
            'GET',
            $url,
            $startedAt,
            $response,
            null,
            ['account_id' => $this->account->id],
        );

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function foldersFromPayload(array $payload): array
    {
        $values = is_array($payload['value'] ?? null) ? $payload['value'] : [];
        $albums = [];

        foreach ($values as $entry) {
            if (! is_array($entry) || ! isset($entry['folder'])) {
                continue;
            }

            $albums[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'title' => (string) ($entry['name'] ?? 'Untitled folder'),
                'cover_thumbnail_url' => $this->thumbnailUrl($entry),
                'media_items_count' => isset($entry['folder']['childCount']) ? (int) $entry['folder']['childCount'] : null,
            ];
        }

        return $albums;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function filesFromPayload(array $payload): array
    {
        $values = is_array($payload['value'] ?? null) ? $payload['value'] : [];
        $items = [];

        foreach ($values as $entry) {
            if (! is_array($entry) || ! isset($entry['file'])) {
                continue;
            }

            $mimeType = isset($entry['file']['mimeType']) ? (string) $entry['file']['mimeType'] : '';
            if ($mimeType !== '' && ! str_starts_with($mimeType, 'image/')) {
                continue;
            }

            $items[] = [
                'id' => (string) ($entry['id'] ?? ''),
                'name' => (string) ($entry['name'] ?? 'Untitled'),
                'mime_type' => $mimeType !== '' ? $mimeType : null,
                'thumbnail_url' => $this->thumbnailUrl($entry),
                'size' => isset($entry['size']) ? (int) $entry['size'] : null,
                'modified_at' => isset($entry['lastModifiedDateTime']) ? (string) $entry['lastModifiedDateTime'] : null,
                'path' => null,
                'web_url' => isset($entry['webUrl']) ? (string) $entry['webUrl'] : null,
            ];
        }

        return $items;
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? $message : 'OneDrive browse failed.';
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function thumbnailUrl(array $entry): ?string
    {
        $thumbnails = $entry['thumbnails'] ?? null;
        if (! is_array($thumbnails) || $thumbnails === []) {
            return null;
        }

        $first = $thumbnails[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        foreach (['medium', 'small', 'large'] as $size) {
            $url = $first[$size]['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    // ---- Verify internals ----

    private function driveCheck(): ConnectionCheck
    {
        try {
            $accessToken = $this->tokens->accessToken($this->account->credentials ?? [], $this->account);
        } catch (RuntimeException $e) {
            return ConnectionCheck::failed('Drive API', 'OneDrive probe failed: '.$e->getMessage());
        }

        $endpoint = self::GRAPH_BASE.'/me/drive';
        $startedAt = microtime(true);
        $response = Http::withToken($accessToken)->timeout(30)->get($endpoint, [
            '$select' => 'id,driveType,owner,quota',
        ]);
        $this->apiLogger->logRequest('onedrive', 'GET', $endpoint, $startedAt, $response, null, ['account_id' => $this->account->id]);

        if (! $response->successful()) {
            $message = $response->json('error.message');

            return ConnectionCheck::failed(
                'Drive API',
                'OneDrive probe failed: '.(is_string($message) && $message !== '' ? $message : 'OneDrive drive request failed.'),
            );
        }

        return ConnectionCheck::passed('Drive API', 'OneDrive API is reachable.', $this->driveDetails($response->json()));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<string>
     */
    private function driveDetails(?array $payload): array
    {
        $payload ??= [];
        $details = [];

        $owner = trim((string) ($payload['owner']['user']['displayName'] ?? ''));
        if ($owner !== '') {
            $details[] = "Connected as: {$owner}";
        }

        $driveId = trim((string) ($payload['id'] ?? ''));
        if ($driveId !== '') {
            $driveType = trim((string) ($payload['driveType'] ?? ''));
            $details[] = 'Drive: '.$driveId.($driveType !== '' ? " ({$driveType})" : '');
        }

        $quota = is_array($payload['quota'] ?? null) ? $payload['quota'] : [];
        if (isset($quota['used'])) {
            $used = Number::fileSize((int) $quota['used']);
            $details[] = isset($quota['total'])
                ? "Storage used: {$used} of ".Number::fileSize((int) $quota['total'])
                : "Storage used: {$used}";
        }

        return $details;
    }
}
