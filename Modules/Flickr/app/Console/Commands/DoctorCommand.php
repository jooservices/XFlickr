<?php

declare(strict_types=1);

namespace Modules\Flickr\Console\Commands;

use App\Support\MaskedCredentialHint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Facades\FlickrService;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Throwable;

final class DoctorCommand extends Command
{
    protected $signature = 'xflickr:flickr:doctor';

    protected $description = 'Check MySQL, Redis, MongoDB, storage disk, and Flickr connection health';

    public function handle(FlickrTokenHealthService $tokenHealth): int
    {
        $this->info('XFlickr doctor');
        $failed = false;

        if ($this->check('MySQL', function (): void {
            DB::connection()->getPdo();
        })) {
            $failed = true;
        }

        if ($this->check('Redis', function (): void {
            Redis::connection()->ping();
        })) {
            $failed = true;
        }

        if ($this->check('MongoDB config store', function (): void {
            RuntimeConfig::has('xflickr_app.main');
        })) {
            $failed = true;
        }

        if ($this->check('Local storage disk', function (): void {
            Storage::disk('local')->put('xflickr-doctor.probe', 'ok');
            Storage::disk('local')->delete('xflickr-doctor.probe');
        })) {
            $failed = true;
        }

        if ($this->checkFlickrConnectionTokens($tokenHealth)) {
            $failed = true;
        }

        if ($failed) {
            $this->error('One or more checks failed.');

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    private function checkFlickrConnectionTokens(FlickrTokenHealthService $tokenHealth): bool
    {
        $failed = false;

        try {
            $connections = FlickrService::connections()->list();
        } catch (Throwable $exception) {
            $this->line("<fg=red>✗</> Flickr connections: {$exception->getMessage()}");

            return true;
        }

        $connected = $connections->filter(
            fn ($connection): bool => $connection->disconnected_at === null && $connection->token_payload !== '',
        );

        if ($connected->isEmpty()) {
            $this->line('<fg=green>✓</> Flickr connections (none connected)');

            return false;
        }

        foreach ($connected as $connection) {
            $label = $connection->username ?? $connection->connection_key;
            $profile = $connection->app_profile ?? 'main';

            try {
                $credentials = XFlickrConfig::appCredentials($profile);
                $hint = MaskedCredentialHint::leadingAndTrailing($credentials->apiKey);
            } catch (Throwable) {
                $hint = 'unknown';
            }

            $result = $tokenHealth->probe($connection);

            if ($result->valid) {
                $this->line("<fg=green>✓</> Flickr token [{$label}] profile={$profile} key={$hint}");
            } else {
                $message = $result->errorMessage ?? 'invalid token';
                $this->line("<fg=red>✗</> Flickr token [{$label}] profile={$profile} key={$hint}: {$message}");
                $failed = true;
            }
        }

        return $failed;
    }

    private function check(string $label, callable $callback): bool
    {
        try {
            $callback();
            $this->line("<fg=green>✓</> {$label}");

            return false;
        } catch (Throwable $exception) {
            $this->line("<fg=red>✗</> {$label}: {$exception->getMessage()}");

            return true;
        }
    }
}
