<?php

declare(strict_types=1);

namespace Modules\Flickr\Dto;

/**
 * Structured Flickr API diagnostic report for CLI (and unit tests).
 *
 * @phpstan-type Entry array{
 *     type: 'section'|'line'|'warn'|'probe',
 *     text?: string,
 *     method?: string,
 *     mode?: 'raw'|'crawl',
 *     ok?: bool,
 *     ms?: int,
 *     total?: int|null,
 *     code?: int|null,
 *     message?: string,
 * }
 */
final readonly class FlickrApiAuditReport
{
    /**
     * @param  list<Entry>  $entries
     */
    public function __construct(
        public string $connectionKey,
        public ?string $username,
        public string $appProfile,
        public string $apiKeyHint,
        public array $entries,
    ) {}
}
