<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
final class FlickrRateLimitUsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $usage */
        $usage = $this->resource;

        return $usage;
    }
}
