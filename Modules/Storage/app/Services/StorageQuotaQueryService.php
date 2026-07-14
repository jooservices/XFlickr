<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Google\Service\Drive;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Storage\Services\Tokens\MicrosoftTokenService;
use Modules\Storage\Support\StorageApiLogger;
use Throwable;

final class StorageQuotaQueryService
{
    private const int CACHE_TTL_SECONDS = 120;

    public function __construct(
        private readonly StorageAccountRepository $accounts,
        private readonly GoogleTokenService $googleTokens,
        private readonly MicrosoftTokenService $microsoftTokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    /**
     * @return array{
     *     generated_at: string,
     *     accounts: list<array{
     *         account: array{id: int, provider: string, label: string, is_default: bool},
     *         status: 'ok'|'unsupported'|'error',
     *         message: string|null,
     *         quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null
     *     }>
     * }
     */
    public function snapshot(): array
    {
        $rows = [];

        foreach ($this->accounts->listOrderedForSettings() as $account) {
            $rows[] = $this->presentAccount($account);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'accounts' => $rows,
        ];
    }

    /**
     * @return array{
     *     account: array{id: int, provider: string, label: string, is_default: bool},
     *     status: 'ok'|'unsupported'|'error',
     *     message: string|null,
     *     quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null
     * }
     */
    private function presentAccount(StorageAccount $account): array
    {
        $summary = [
            'account' => [
                'id' => $account->id,
                'provider' => $account->provider,
                'label' => $account->label,
                'is_default' => (bool) $account->is_default,
            ],
        ];

        $driver = StorageDriver::tryFrom($account->provider);

        if ($driver === null) {
            return [
                ...$summary,
                'status' => 'unsupported',
                'message' => 'Unknown storage provider.',
                'quota' => null,
            ];
        }

        return match ($driver) {
            StorageDriver::GoogleDrive => $this->cachedFetch($account, fn (): array => $this->fetchGoogleDriveQuota($account)),
            StorageDriver::OneDrive => $this->cachedFetch($account, fn (): array => $this->fetchOneDriveQuota($account)),
            StorageDriver::GooglePhotos, StorageDriver::R2 => [
                ...$summary,
                'status' => 'unsupported',
                'message' => $driver->label().' does not expose a usable storage quota API.',
                'quota' => null,
            ],
        };
    }

    /**
     * @param  callable(): array{status: 'ok'|'unsupported'|'error', message: string|null, quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null}  $fetcher
     * @return array{
     *     account: array{id: int, provider: string, label: string, is_default: bool},
     *     status: 'ok'|'unsupported'|'error',
     *     message: string|null,
     *     quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null
     * }
     */
    private function cachedFetch(StorageAccount $account, callable $fetcher): array
    {
        $cacheKey = $this->cacheKey($account->id);
        $payload = $this->readCachedPayload($cacheKey);

        if ($payload === null) {
            $payload = $fetcher();
            Cache::put($cacheKey, $payload, self::CACHE_TTL_SECONDS);
        }

        return [
            'account' => [
                'id' => $account->id,
                'provider' => $account->provider,
                'label' => $account->label,
                'is_default' => (bool) $account->is_default,
            ],
            'status' => $payload['status'],
            'message' => $payload['message'],
            'quota' => $payload['quota'],
        ];
    }

    /**
     * @return array{status: 'ok'|'unsupported'|'error', message: string|null, quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null}|null
     */
    private function readCachedPayload(string $cacheKey): ?array
    {
        $cached = Cache::get($cacheKey);

        if (! is_array($cached)) {
            return null;
        }

        $status = $cached['status'] ?? null;
        if (! is_string($status) || ! in_array($status, ['ok', 'unsupported', 'error'], true)) {
            return null;
        }

        $message = $cached['message'] ?? null;
        if (! is_string($message) && $message !== null) {
            return null;
        }

        $quotaRaw = $cached['quota'] ?? null;
        if ($quotaRaw === null) {
            return [
                'status' => $status,
                'message' => $message,
                'quota' => null,
            ];
        }

        if (! is_array($quotaRaw) || ! array_key_exists('used_bytes', $quotaRaw)) {
            return null;
        }

        $limitRaw = $quotaRaw['limit_bytes'] ?? null;

        return [
            'status' => $status,
            'message' => $message,
            'quota' => $this->quotaPayload(
                (int) $quotaRaw['used_bytes'],
                $limitRaw !== null ? (int) $limitRaw : null,
            ),
        ];
    }

    /**
     * @return array{status: 'ok'|'unsupported'|'error', message: string|null, quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null}
     */
    private function fetchGoogleDriveQuota(StorageAccount $account): array
    {
        try {
            $credentials = $account->credentials ?? [];
            $client = $this->googleTokens->clientForAccount($credentials, $account);
            $service = new Drive($client);
            $about = $service->about->get(['fields' => 'storageQuota']);
            $quota = $about->getStorageQuota();

            if ($quota === null) {
                return [
                    'status' => 'unsupported',
                    'message' => 'Google Drive did not return storage quota.',
                    'quota' => null,
                ];
            }

            $used = (int) ($quota->getUsage() ?? 0);
            $limitRaw = $quota->getLimit();
            $limit = $limitRaw !== null && $limitRaw !== '' ? (int) $limitRaw : null;

            return [
                'status' => 'ok',
                'message' => null,
                'quota' => $this->quotaPayload($used, $limit),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'quota' => null,
            ];
        }
    }

    /**
     * @return array{status: 'ok'|'unsupported'|'error', message: string|null, quota: array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}|null}
     */
    private function fetchOneDriveQuota(StorageAccount $account): array
    {
        try {
            $credentials = $account->credentials ?? [];
            $accessToken = $this->microsoftTokens->accessToken($credentials, $account);
            $endpoint = 'https://graph.microsoft.com/v1.0/me/drive';
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get($endpoint, ['$select' => 'id,quota']);
            $this->apiLogger->logRequest(
                'microsoft',
                'GET',
                $endpoint,
                $startedAt,
                $response,
                null,
                ['account_id' => $account->id, 'operation' => 'storage_quota'],
            );

            if (! $response->successful()) {
                return [
                    'status' => 'error',
                    'message' => 'OneDrive quota request failed (HTTP '.$response->status().').',
                    'quota' => null,
                ];
            }

            $payload = $response->json();
            $quota = is_array($payload) ? ($payload['quota'] ?? null) : null;

            if (! is_array($quota)) {
                return [
                    'status' => 'unsupported',
                    'message' => 'OneDrive did not return storage quota.',
                    'quota' => null,
                ];
            }

            $used = (int) ($quota['used'] ?? 0);
            $limitRaw = $quota['total'] ?? null;
            $limit = $limitRaw !== null ? (int) $limitRaw : null;

            return [
                'status' => 'ok',
                'message' => null,
                'quota' => $this->quotaPayload($used, $limit),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'quota' => null,
            ];
        }
    }

    /**
     * @return array{used_bytes: int, limit_bytes: int|null, remaining_bytes: int|null}
     */
    private function quotaPayload(int $usedBytes, ?int $limitBytes): array
    {
        $remaining = $limitBytes === null ? null : max(0, $limitBytes - $usedBytes);

        return [
            'used_bytes' => max(0, $usedBytes),
            'limit_bytes' => $limitBytes !== null && $limitBytes > 0 ? $limitBytes : null,
            'remaining_bytes' => $remaining,
        ];
    }

    private function cacheKey(int $accountId): string
    {
        return 'xflickr:storage:quota:'.$accountId;
    }
}
