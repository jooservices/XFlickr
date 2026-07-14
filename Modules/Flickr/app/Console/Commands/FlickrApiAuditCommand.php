<?php

declare(strict_types=1);

namespace Modules\Flickr\Console\Commands;

use Illuminate\Console\Command;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Flickr\Dto\FlickrApiAuditReport;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Throwable;

final class FlickrApiAuditCommand extends Command
{
    protected $signature = 'xflickr:flickr:audit-api
                            {connection_key? : Flickr NSID (defaults to active connection)}
                            {--contact= : Contact NSID to probe with people.getPhotos}
                            {--url= : Flickr profile URL to resolve NSID via flickr.urls.lookupUser}
                            {--photo-id= : Known photo id to verify visibility via photos.getInfo}';

    protected $description = 'Probe Flickr API methods for a connection and optional contact NSID';

    public function handle(FlickrTokenHealthService $tokenHealth): int
    {
        $connection = $this->resolveConnection();
        if ($connection === null) {
            $this->error('No Flickr connection found.');

            return self::FAILURE;
        }

        try {
            XFlickrConfig::appCredentials($connection->app_profile ?: 'main');
        } catch (Throwable $exception) {
            $this->error('App credentials missing: '.$exception->getMessage());

            return self::FAILURE;
        }

        $report = $tokenHealth->auditEndpoints(
            $connection,
            (string) ($this->option('contact') ?: ''),
            (string) ($this->option('url') ?: ''),
            trim((string) ($this->option('photo-id') ?: '')),
        );

        $this->renderReport($report);

        return self::SUCCESS;
    }

    private function renderReport(FlickrApiAuditReport $report): void
    {
        $this->info("Connection: {$report->connectionKey} ({$report->username})");
        $this->line("Profile: {$report->appProfile} · API key: {$report->apiKeyHint}");

        foreach ($report->entries as $entry) {
            match ($entry['type']) {
                'section' => $this->renderSection((string) ($entry['text'] ?? '')),
                'line' => $this->line((string) ($entry['text'] ?? '')),
                'warn' => $this->warn((string) ($entry['text'] ?? '')),
                'probe' => $this->renderProbe($entry),
                default => null,
            };
        }
    }

    private function renderSection(string $text): void
    {
        $this->line('');
        $this->info($text);
    }

    /**
     * @param  array{type: 'probe', method?: string, mode?: string, ok?: bool, ms?: int, total?: int|null, code?: int|null, message?: string}  $entry
     */
    private function renderProbe(array $entry): void
    {
        $method = (string) ($entry['method'] ?? '');
        $mode = (string) ($entry['mode'] ?? 'raw');
        $ok = (bool) ($entry['ok'] ?? false);
        $ms = (int) ($entry['ms'] ?? 0);
        $total = $entry['total'] ?? null;
        $code = $entry['code'] ?? null;
        $message = (string) ($entry['message'] ?? 'unknown error');
        $suffix = $mode === 'crawl' ? ' (crawl)' : '';

        if ($ok) {
            $totalLabel = $total === null ? '—' : (string) $total;
            $this->line("<fg=green>✓</> {$method}{$suffix} · {$ms}ms · total={$totalLabel}");

            return;
        }

        $this->line("<fg=red>✗</> {$method}{$suffix} · {$ms}ms · code={$code} · {$message}");
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
}
