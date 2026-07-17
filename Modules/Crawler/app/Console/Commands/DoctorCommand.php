<?php

declare(strict_types=1);

namespace Modules\Crawler\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Modules\Crawler\Exceptions\FlickrAppNotConfiguredException;
use Modules\Crawler\Support\XFlickrConfig;
use Throwable;

final class DoctorCommand extends Command
{
    protected $signature = 'xflickr:crawler:doctor';

    protected $aliases = ['xflickr:doctor'];

    protected $description = 'Diagnose XFlickr Crawler host integration (Redis, app profile, queue, migrations)';

    public function handle(Migrator $migrator): int
    {
        $checks = [
            'redis' => $this->checkRedis(),
            'app_profile' => $this->checkAppProfile(),
            'queue' => $this->checkQueue(),
            'migrations' => $this->checkMigrations($migrator),
        ];

        foreach ($checks as $name => [$ok, $message]) {
            $this->line(sprintf('[%s] %s: %s', $ok ? 'OK' : 'FAIL', $name, $message));
        }

        $failed = count(array_filter($checks, static fn (array $check): bool => ! $check[0]));

        if ($failed > 0) {
            $this->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->info('All XFlickr doctor checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkRedis(): array
    {
        try {
            $pong = Redis::connection()->ping();

            return [true, is_string($pong) ? $pong : 'reachable'];
        } catch (Throwable $throwable) {
            return [false, $throwable->getMessage()];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkAppProfile(): array
    {
        $profile = XFlickrConfig::defaultAppProfile();

        try {
            $credentials = XFlickrConfig::appCredentials($profile);

            return [true, "profile [{$profile}] resolved for apiKey {$credentials->apiKey}"];
        } catch (FlickrAppNotConfiguredException $exception) {
            return [false, $exception->getMessage()];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $queueName = (string) config('xflickr-crawler.queue', 'xflickr');

        try {
            $size = Queue::connection($connection)->size($queueName);

            return [true, "connection [{$connection}] queue [{$queueName}] size {$size}"];
        } catch (Throwable $throwable) {
            return [false, $throwable->getMessage()];
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function checkMigrations(Migrator $migrator): array
    {
        $ran = $migrator->getRepository()->getRan();
        $pending = [];

        foreach ($migrator->paths() as $path) {
            foreach (array_keys($migrator->getMigrationFiles($path)) as $migration) {
                if (! in_array($migration, $ran, true)) {
                    $pending[] = $migration;
                }
            }
        }

        if ($pending === []) {
            return [true, 'no pending migrations'];
        }

        return [false, 'pending migrations: '.implode(', ', $pending)];
    }
}
