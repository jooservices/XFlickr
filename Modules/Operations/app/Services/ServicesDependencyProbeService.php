<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use MongoDB\Laravel\Connection as MongoConnection;
use Throwable;

final class ServicesDependencyProbeService
{
    private const string CACHE_PREFIX = 'xflickr:operations:dependency:';

    private const int CACHE_SECONDS = 30;

    /**
     * @return array{
     *     mysql: array{ok: bool, latency_ms: int|null, detail: string|null},
     *     redis: array{ok: bool, latency_ms: int|null, detail: string|null},
     *     mongodb: array{ok: bool, latency_ms: int|null, detail: string|null},
     * }
     */
    public function probeAll(): array
    {
        return [
            'mysql' => $this->remember('mysql', fn (): array => $this->probeMysql()),
            'redis' => $this->remember('redis', fn (): array => $this->probeRedis()),
            'mongodb' => $this->remember('mongodb', fn (): array => $this->probeMongoDb()),
        ];
    }

    /**
     * @param  callable(): array{ok: bool, latency_ms: int|null, detail: string|null}  $probe
     * @return array{ok: bool, latency_ms: int|null, detail: string|null}
     */
    private function remember(string $key, callable $probe): array
    {
        /** @var array{ok: bool, latency_ms: int|null, detail: string|null} $result */
        $result = Cache::remember(
            self::CACHE_PREFIX.$key,
            self::CACHE_SECONDS,
            static function () use ($probe): array {
                return $probe();
            },
        );

        return $result;
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, detail: string|null}
     */
    private function probeMysql(): array
    {
        $started = microtime(true);

        try {
            DB::select('select 1');

            return $this->result(true, $started, null);
        } catch (Throwable $throwable) {
            return $this->result(false, $started, $throwable->getMessage());
        }
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, detail: string|null}
     */
    private function probeRedis(): array
    {
        $started = microtime(true);

        try {
            $pong = Redis::connection()->ping();
            $ok = $pong === true || $pong === 'PONG' || $pong === '+PONG';

            return $this->result($ok, $started, $ok ? null : 'Unexpected ping response');
        } catch (Throwable $throwable) {
            return $this->result(false, $started, $throwable->getMessage());
        }
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, detail: string|null}
     */
    private function probeMongoDb(): array
    {
        $started = microtime(true);

        try {
            $connection = DB::connection('mongodb');

            if (! $connection instanceof MongoConnection) {
                return $this->result(false, $started, 'MongoDB connection is not configured.');
            }

            $connection->getDatabase()->command(['ping' => 1]);

            return $this->result(true, $started, null);
        } catch (Throwable $throwable) {
            return $this->result(false, $started, $throwable->getMessage());
        }
    }

    /**
     * @return array{ok: bool, latency_ms: int|null, detail: string|null}
     */
    private function result(bool $ok, float $started, ?string $detail): array
    {
        return [
            'ok' => $ok,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'detail' => $detail,
        ];
    }
}
