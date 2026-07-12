<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Flickr\Dto\FlickrTokenHealthResult;

/**
 * @property-read FlickrTokenHealthResult|array{token_valid: bool|null}|null $resource
 */
final class FlickrTokenHealthResource extends JsonResource
{
    /**
     * @return array{token_valid: bool|null, error_code?: int|null, error_message?: string|null, user_nsid?: string|null}
     */
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return [
                'token_valid' => $this->resource['token_valid'] ?? null,
            ];
        }

        /** @var FlickrTokenHealthResult $result */
        $result = $this->resource;

        return [
            'token_valid' => $result->valid,
            'error_code' => $result->errorCode,
            'error_message' => $result->errorMessage,
            'user_nsid' => $result->userNsid,
        ];
    }
}
