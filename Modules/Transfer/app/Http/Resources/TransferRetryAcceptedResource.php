<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransferRetryAcceptedResource extends JsonResource
{
    /**
     * @return array{status: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'queued',
        ];
    }
}
