<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class FlickrRateLimitSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $snapshot */
        $snapshot = $this->resource;

        return $snapshot;
    }
}
