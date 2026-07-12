<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests\Api;

use App\Http\Requests\Request;
use Modules\Contacts\Support\ContactGraphRuntimeConfig;

final class ContactGraphSnapshotRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direct_limit' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function directLimit(ContactGraphRuntimeConfig $config): int
    {
        if (! $this->has('direct_limit')) {
            return $config->initialDirectLimit();
        }

        return max(0, (int) $this->query('direct_limit'));
    }
}
