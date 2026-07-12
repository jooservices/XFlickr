<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests\Api;

use App\Http\Requests\Request;

final class ContactGraphExpandRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_nsid' => ['required', 'string'],
        ];
    }

    public function contactNsid(): string
    {
        return (string) $this->input('contact_nsid');
    }
}
