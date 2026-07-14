<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contacts\Dto\ContactImportResult;

/**
 * @mixin ContactImportResult
 */
final class ContactImportResource extends JsonResource
{
    /**
     * @return array{
     *     nsid: string,
     *     username: string|null,
     *     realname: string|null,
     *     already_linked: bool,
     *     crawl_started: bool,
     *     redirect_path: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ContactImportResult $result */
        $result = $this->resource;

        return $result->toArray();
    }
}
