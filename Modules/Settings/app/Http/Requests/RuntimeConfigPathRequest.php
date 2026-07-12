<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;

final class RuntimeConfigPathRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $path = $this->route('path');

        $this->merge([
            'path' => is_string($path) ? trim(urldecode($path)) : $path,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255'],
        ];
    }

    public function configPath(): string
    {
        return (string) $this->validated('path');
    }
}
