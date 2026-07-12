<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed>|object $resource
 */
final class CrawlRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            /** @var array<string, mixed> $row */
            $row = $this->resource->toArray();

            return $row;
        }

        return [];
    }
}
