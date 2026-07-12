<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests\Api;

use App\Http\Requests\Request;

final class UpdateContactAnnotationRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'starred' => ['sometimes', 'boolean'],
        ];
    }

    public function hasNoteUpdate(): bool
    {
        return $this->has('note');
    }

    public function hasStarredUpdate(): bool
    {
        return $this->has('starred');
    }

    public function noteValue(): ?string
    {
        if (! $this->hasNoteUpdate()) {
            return null;
        }

        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }

    public function starredValue(): ?bool
    {
        if (! $this->hasStarredUpdate()) {
            return null;
        }

        return $this->boolean('starred');
    }
}
