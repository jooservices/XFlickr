<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use JOOservices\XFlickrCrawler\Facades\FlickrService;
use Throwable;

final class DoctorCommand extends Command
{
    protected $signature = 'xflickr:doctor';

    protected $description = 'Check MySQL, Redis, MongoDB, storage disk, and Flickr connection health';

    public function handle(): int
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

        if ($this->check('Flickr connections', function (): void {
            FlickrService::connections()->list();
        })) {
            $failed = true;
        }

        if ($failed) {
            $this->error('One or more checks failed.');

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
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
