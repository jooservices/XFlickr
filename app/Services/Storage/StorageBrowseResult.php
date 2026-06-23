<?php

declare(strict_types=1);

namespace App\Services\Storage;

final class StorageBrowseResult
{
    /**
     * @param  list<array<string, mixed>>  $albums
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $localMeta
     */
    public function __construct(
        public readonly array $albums,
        public readonly array $items,
        public readonly ?string $albumNextPageToken = null,
        public readonly ?string $itemNextPageToken = null,
        public readonly ?array $localMeta = null,
    ) {}

    /**
     * @return array{albums: list<array<string, mixed>>, items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function toArray(int $perPage): array
    {
        $meta = [
            'per_page' => $perPage,
            'album_next_page_token' => $this->albumNextPageToken,
            'item_next_page_token' => $this->itemNextPageToken,
            'has_more_albums' => $this->albumNextPageToken !== null && $this->albumNextPageToken !== '',
            'has_more_items' => $this->itemNextPageToken !== null && $this->itemNextPageToken !== '',
        ];

        if ($this->localMeta !== null) {
            $meta = array_merge($meta, $this->localMeta);
            $meta['has_more_albums'] = ($this->localMeta['album_page'] ?? 1) < ($this->localMeta['album_last_page'] ?? 1);
            $meta['has_more_items'] = ($this->localMeta['item_page'] ?? 1) < ($this->localMeta['item_last_page'] ?? 1);
        }

        return [
            'albums' => $this->albums,
            'items' => $this->items,
            'meta' => $meta,
        ];
    }
}
