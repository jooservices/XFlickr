<?php

declare(strict_types=1);

namespace App\Http\Requests\Storage;

use App\Http\Requests\Request;

final class ReauthorizeStorageRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'return_url' => $this->sanitizeReturnUrl($this->query('return_url')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'return_url' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function returnUrl(): ?string
    {
        $returnUrl = $this->validated('return_url');

        return is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : null;
    }

    private function sanitizeReturnUrl(mixed $returnUrl): ?string
    {
        if (! is_string($returnUrl) || $returnUrl === '') {
            return null;
        }

        if (! str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, '//')) {
            return null;
        }

        return $returnUrl;
    }
}
