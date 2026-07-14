<?php

declare(strict_types=1);

namespace Modules\Operations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;
use Throwable;

final class DatabaseUsageService
{
    private const string SNAPSHOT_CACHE_KEY = 'xflickr:database:usage';

    private const string HISTORY_CACHE_KEY = 'xflickr:database:usage:history';

    private const int SNAPSHOT_TTL_SECONDS = 60;

    private const int HISTORY_SAMPLE_INTERVAL_SECONDS = 60;

    private const int HISTORY_RETENTION_SECONDS = 86_400;

    /**
     * @return array{
     *     mysql: array<string, mixed>,
     *     mongodb: array<string, mixed>,
     *     history: list<array{t: int, mysql_size_bytes: int|null, mysql_connections: int|null, mongodb_size_bytes: int|null}>,
     * }
     */
    public function snapshot(): array
    {
        return Cache::remember(self::SNAPSHOT_CACHE_KEY, self::SNAPSHOT_TTL_SECONDS, function (): array {
            $mysql = $this->mysqlUsage();
            $mongodb = $this->mongodbUsage();
            $this->recordHistory($mysql, $mongodb);

            return [
                'mysql' => $mysql,
                'mongodb' => $mongodb,
                'history' => $this->history(),
            ];
        });
    }

    /**
     * @return array{
     *     status: 'ok'|'error',
     *     driver: string,
     *     database: string|null,
     *     size_bytes: int|null,
     *     connections_current: int|null,
     *     connections_max: int|null,
     *     tables: list<array{name: string, size_bytes: int}>,
     *     error: string|null,
     * }
     */
    private function mysqlUsage(): array
    {
        $driver = (string) config('database.default');
        $database = null;

        try {
            $connection = DB::connection();
            $database = $connection->getDatabaseName();
            $connection->getPdo();

            if ($driver === 'sqlite') {
                return [
                    'status' => 'ok',
                    'driver' => $driver,
                    'database' => $database !== '' ? $database : ':memory:',
                    'size_bytes' => $this->sqliteSizeBytes(),
                    'connections_current' => null,
                    'connections_max' => null,
                    'tables' => [],
                    'error' => null,
                ];
            }

            return [
                'status' => 'ok',
                'driver' => $driver,
                'database' => $database,
                'size_bytes' => $this->mysqlSchemaSizeBytes(),
                'connections_current' => $this->mysqlStatusInt('Threads_connected'),
                'connections_max' => $this->mysqlVariableInt('max_connections'),
                'tables' => $this->mysqlTopTables(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'driver' => $driver,
                'database' => $database,
                'size_bytes' => null,
                'connections_current' => null,
                'connections_max' => null,
                'tables' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array{
     *     status: 'ok'|'error',
     *     driver: string,
     *     database: string|null,
     *     size_bytes: int|null,
     *     collections: int|null,
     *     objects: int|null,
     *     error: string|null,
     * }
     */
    private function mongodbUsage(): array
    {
        $database = config('database.connections.mongodb.database');
        $databaseName = is_string($database) ? $database : null;

        try {
            $connection = DB::connection('mongodb');

            if (! $connection instanceof MongoConnection) {
                throw new \RuntimeException('MongoDB connection is not configured.');
            }

            $stats = $connection->getDatabase()->command(['dbStats' => 1])->toArray()[0] ?? null;
            $statsArray = json_decode(json_encode($stats), true);
            if (! is_array($statsArray)) {
                $statsArray = [];
            }

            $dataSize = $statsArray['dataSize'] ?? null;
            $collections = $statsArray['collections'] ?? null;
            $objects = $statsArray['objects'] ?? null;

            return [
                'status' => 'ok',
                'driver' => 'mongodb',
                'database' => $databaseName,
                'size_bytes' => is_numeric($dataSize) ? (int) $dataSize : null,
                'collections' => is_numeric($collections) ? (int) $collections : null,
                'objects' => is_numeric($objects) ? (int) $objects : null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'driver' => 'mongodb',
                'database' => $databaseName,
                'size_bytes' => null,
                'collections' => null,
                'objects' => null,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function mysqlSchemaSizeBytes(): ?int
    {
        try {
            $row = DB::selectOne(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS size_bytes
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()',
            );

            return isset($row->size_bytes) ? (int) $row->size_bytes : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{name: string, size_bytes: int}>
     */
    private function mysqlTopTables(int $limit = 5): array
    {
        try {
            $rows = DB::select(
                'SELECT table_name AS name, COALESCE(data_length + index_length, 0) AS size_bytes
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 ORDER BY size_bytes DESC
                 LIMIT ?',
                [$limit],
            );

            return array_map(
                static fn (object $row): array => [
                    'name' => (string) $row->name,
                    'size_bytes' => (int) $row->size_bytes,
                ],
                $rows,
            );
        } catch (Throwable) {
            return [];
        }
    }

    private function mysqlStatusInt(string $variable): ?int
    {
        try {
            $row = DB::selectOne('SHOW STATUS LIKE ?', [$variable]);

            return isset($row->Value) ? (int) $row->Value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function mysqlVariableInt(string $variable): ?int
    {
        try {
            $row = DB::selectOne('SHOW VARIABLES LIKE ?', [$variable]);

            return isset($row->Value) ? (int) $row->Value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function sqliteSizeBytes(): ?int
    {
        try {
            $pageCount = DB::selectOne('PRAGMA page_count');
            $pageSize = DB::selectOne('PRAGMA page_size');
            $pages = isset($pageCount->page_count) ? (int) $pageCount->page_count : 0;
            $size = isset($pageSize->page_size) ? (int) $pageSize->page_size : 0;

            return $pages * $size;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $mysql
     * @param  array<string, mixed>  $mongodb
     */
    private function recordHistory(array $mysql, array $mongodb): void
    {
        /** @var list<array{t: int, mysql_size_bytes: int|null, mysql_connections: int|null, mongodb_size_bytes: int|null}> $history */
        $history = Cache::get(self::HISTORY_CACHE_KEY, []);
        if (! is_array($history)) {
            $history = [];
        }

        $now = now()->getTimestamp();
        $last = $history === [] ? null : $history[array_key_last($history)];

        if (is_array($last) && isset($last['t']) && ($now - (int) $last['t']) < self::HISTORY_SAMPLE_INTERVAL_SECONDS) {
            return;
        }

        $history[] = [
            't' => $now,
            'mysql_size_bytes' => isset($mysql['size_bytes']) && is_numeric($mysql['size_bytes'])
                ? (int) $mysql['size_bytes']
                : null,
            'mysql_connections' => isset($mysql['connections_current']) && is_numeric($mysql['connections_current'])
                ? (int) $mysql['connections_current']
                : null,
            'mongodb_size_bytes' => isset($mongodb['size_bytes']) && is_numeric($mongodb['size_bytes'])
                ? (int) $mongodb['size_bytes']
                : null,
        ];

        $cutoff = $now - self::HISTORY_RETENTION_SECONDS;
        $history = array_values(array_filter(
            $history,
            static fn (array $point): bool => ($point['t'] ?? 0) >= $cutoff,
        ));

        Cache::forever(self::HISTORY_CACHE_KEY, $history);
    }

    /**
     * @return list<array{t: int, mysql_size_bytes: int|null, mysql_connections: int|null, mongodb_size_bytes: int|null}>
     */
    private function history(): array
    {
        $history = Cache::get(self::HISTORY_CACHE_KEY, []);

        if (! is_array($history)) {
            return [];
        }

        /** @var list<array{t: int, mysql_size_bytes: int|null, mysql_connections: int|null, mongodb_size_bytes: int|null}> $history */
        return array_values($history);
    }
}
