<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;

final class UpdateSpiderModeRequest extends Request
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'max_depth' => ['required', 'integer', 'min:0', 'max:10'],
            'max_new_contacts_per_run' => ['required', 'integer', 'min:1', 'max:500'],
            'max_contacts_total' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function maxDepth(): int
    {
        return (int) $this->input('max_depth');
    }

    public function maxNewContactsPerRun(): int
    {
        return (int) $this->input('max_new_contacts_per_run');
    }

    public function maxContactsTotal(): int
    {
        return (int) $this->input('max_contacts_total');
    }

    /**
     * @return array{enabled: bool, max_depth: int, max_new_contacts_per_run: int, max_contacts_total: int}
     */
    public function spiderSettings(): array
    {
        return [
            'enabled' => $this->enabled(),
            'max_depth' => $this->maxDepth(),
            'max_new_contacts_per_run' => $this->maxNewContactsPerRun(),
            'max_contacts_total' => $this->maxContactsTotal(),
        ];
    }
}
