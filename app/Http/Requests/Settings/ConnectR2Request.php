<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Request;

final class ConnectR2Request extends Request
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => is_string($this->input('label')) ? trim($this->input('label')) : $this->input('label'),
            'access_key_id' => is_string($this->input('access_key_id')) ? trim($this->input('access_key_id')) : $this->input('access_key_id'),
            'secret_access_key' => is_string($this->input('secret_access_key')) ? trim($this->input('secret_access_key')) : $this->input('secret_access_key'),
            'bucket' => is_string($this->input('bucket')) ? trim($this->input('bucket')) : $this->input('bucket'),
            'endpoint' => is_string($this->input('endpoint')) ? trim($this->input('endpoint')) : $this->input('endpoint'),
            'region' => is_string($this->input('region')) ? trim($this->input('region')) : ($this->input('region') ?? 'auto'),
            'prefix' => is_string($this->input('prefix')) ? trim($this->input('prefix')) : ($this->input('prefix') ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'access_key_id' => ['required', 'string', 'max:2048'],
            'secret_access_key' => ['required', 'string', 'max:2048'],
            'bucket' => ['required', 'string', 'max:255'],
            'endpoint' => ['required', 'url', 'max:2048'],
            'region' => ['nullable', 'string', 'max:64'],
            'prefix' => ['nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function credentials(): array
    {
        $validated = $this->validated();

        return [
            'access_key_id' => $validated['access_key_id'],
            'secret_access_key' => $validated['secret_access_key'],
            'bucket' => $validated['bucket'],
            'endpoint' => $validated['endpoint'],
            'region' => $validated['region'] ?? 'auto',
            'prefix' => $validated['prefix'] ?? '',
        ];
    }
}
