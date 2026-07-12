<?php

declare(strict_types=1);

namespace Modules\Transfer\Services;

final readonly class TransferQueueResult
{
    public function __construct(
        public string $flashKey,
        public string $message,
        public int $queuedCount = 0,
    ) {}

    public static function success(string $message, int $queuedCount = 0): self
    {
        return new self('success', $message, $queuedCount);
    }

    public static function error(string $message): self
    {
        return new self('error', $message);
    }
}
