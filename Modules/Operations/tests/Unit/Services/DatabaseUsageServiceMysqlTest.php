<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Operations\Services\DatabaseUsageService;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

final class DatabaseUsageServiceMysqlTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        DB::clearResolvedInstances();
        $this->app->forgetInstance('db');

        parent::tearDown();
    }

    #[Test]
    public function mysql_usage_returns_schema_metrics_when_default_driver_is_mysql(): void
    {
        config(['database.default' => 'mysql']);

        $pdo = Mockery::mock(PDO::class);
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabaseName')->andReturn('xflickr_test');
        $connection->shouldReceive('getPdo')->andReturn($pdo);

        DB::shouldReceive('connection')->withNoArgs()->andReturn($connection);
        DB::shouldReceive('selectOne')
            ->with(Mockery::on(static fn (string $sql): bool => str_contains($sql, 'information_schema')))
            ->andReturn((object) ['size_bytes' => 4096]);
        DB::shouldReceive('selectOne')
            ->with('SHOW STATUS LIKE ?', ['Threads_connected'])
            ->andReturn((object) ['Value' => '3']);
        DB::shouldReceive('selectOne')
            ->with('SHOW VARIABLES LIKE ?', ['max_connections'])
            ->andReturn((object) ['Value' => '151']);
        DB::shouldReceive('select')
            ->andReturn([(object) ['name' => 'users', 'size_bytes' => 2048]]);

        $result = $this->invokePrivate('mysqlUsage');

        $this->assertSame('ok', $result['status']);
        $this->assertSame('mysql', $result['driver']);
        $this->assertSame('xflickr_test', $result['database']);
        $this->assertSame(4096, $result['size_bytes']);
        $this->assertSame(3, $result['connections_current']);
        $this->assertSame(151, $result['connections_max']);
        $this->assertSame([['name' => 'users', 'size_bytes' => 2048]], $result['tables']);
    }

    #[Test]
    public function mysql_usage_returns_error_when_connection_fails(): void
    {
        config(['database.default' => 'mysql']);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabaseName')->andReturn('xflickr_test');
        $connection->shouldReceive('getPdo')->andThrow(new RuntimeException('connection refused'));

        DB::shouldReceive('connection')->withNoArgs()->andReturn($connection);

        $result = $this->invokePrivate('mysqlUsage');

        $this->assertSame('error', $result['status']);
        $this->assertSame('connection refused', $result['error']);
        $this->assertNull($result['size_bytes']);
    }

    #[Test]
    public function mysql_helper_methods_return_null_when_queries_fail(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new RuntimeException('query failed'));
        DB::shouldReceive('select')->andThrow(new RuntimeException('query failed'));

        $this->assertNull($this->invokePrivate('mysqlSchemaSizeBytes'));
        $this->assertSame([], $this->invokePrivate('mysqlTopTables'));
        $this->assertNull($this->invokePrivate('mysqlStatusInt', 'Threads_connected'));
        $this->assertNull($this->invokePrivate('mysqlVariableInt', 'max_connections'));
    }

    #[Test]
    public function sqlite_size_bytes_returns_null_when_pragma_fails(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new RuntimeException('pragma failed'));

        $this->assertNull($this->invokePrivate('sqliteSizeBytes'));
    }

    #[Test]
    public function mongodb_usage_returns_error_when_connection_is_not_mongodb(): void
    {
        config(['database.connections.mongodb.database' => 'xflickr_test']);

        $connection = Mockery::mock(Connection::class);
        DB::shouldReceive('connection')->with('mongodb')->andReturn($connection);

        $result = $this->invokePrivate('mongodbUsage');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('MongoDB connection is not configured', (string) $result['error']);
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod(DatabaseUsageService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(app(DatabaseUsageService::class), ...$args);
    }
}
