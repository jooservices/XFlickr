<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;

final class ToggleGlobalCrawlPauseRequest extends Request
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'paused' => ['required', 'boolean'],
        ];
    }

    public function paused(): bool
    {
        return $this->boolean('paused');
    }
}
