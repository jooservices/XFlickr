<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use JOOservices\LaravelController\Http\Controllers\BaseApiController;
use Modules\Transfer\Services\StoredFileStreamService;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StoredFileController extends BaseApiController
{
    public function show(string $uuid, StoredFileStreamService $streams): StreamedResponse|JsonResponse
    {
        $stored = $streams->findViewableOriginal($uuid);
        if ($stored === null) {
            return $this->notFound('Stored file not found.');
        }

        $localPath = $stored->local_path;
        if (! is_string($localPath) || $localPath === '') {
            return $this->notFound('Stored file not found.');
        }

        $filename = $streams->filename($stored);
        $safeFilename = str_replace(["\0", '/', '\\'], '_', $filename);
        $fallback = preg_replace('/[^\x20-\x7e]|[%\/\\\\"]/', '_', $safeFilename) ?: 'photo';
        $fallback = trim($fallback);

        return response()->stream(function () use ($localPath): void {
            $stream = Storage::readStream($localPath);
            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $streams->mimeType($stored),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $filename !== '' ? $filename : 'photo',
                $fallback !== '' ? $fallback : 'photo',
            ),
        ]);
    }
}
