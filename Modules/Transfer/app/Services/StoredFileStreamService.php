<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Repositories\StoredFileRepository;

final class StoredFileStreamService
{
    public function __construct(
        private readonly StoredFileRepository $storedFiles,
    ) {}

    public function findViewableOriginal(string $uuid): ?StoredFile
    {
        $stored = $this->storedFiles->findByUuid($uuid);
        if ($stored === null) {
            return null;
        }

        if ($stored->status !== StoredFileStatus::Completed->value) {
            return null;
        }

        $localPath = $stored->local_path;
        if (! is_string($localPath) || $localPath === '' || ! Storage::exists($localPath)) {
            return null;
        }

        return $stored;
    }

    public function mimeType(StoredFile $stored): string
    {
        if (is_string($stored->mime_type) && $stored->mime_type !== '') {
            return $stored->mime_type;
        }

        $localPath = $stored->local_path;
        if (! is_string($localPath) || $localPath === '') {
            return 'application/octet-stream';
        }

        $detected = Storage::mimeType($localPath);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }

    public function filename(StoredFile $stored): string
    {
        if (is_string($stored->original_name) && $stored->original_name !== '') {
            return $stored->original_name;
        }

        $localPath = $stored->local_path;
        if (is_string($localPath) && $localPath !== '') {
            return basename($localPath);
        }

        return 'photo';
    }
}
