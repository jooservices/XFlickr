<?php

declare(strict_types=1);

namespace Modules\Storage\Dto;

use Modules\Storage\Enums\ConnectionCheckStatus;

final class ConnectionCheck
{
    /**
     * @param  list<string>  $details
     */
    public function __construct(
        public readonly string $name,
        public readonly ConnectionCheckStatus $status,
        public readonly string $message,
        public readonly array $details = [],
    ) {}

    /**
     * @param  list<string>  $details
     */
    public static function passed(string $name, string $message, array $details = []): self
    {
        return new self($name, ConnectionCheckStatus::Passed, $message, $details);
    }

    /**
     * @param  list<string>  $details
     */
    public static function warning(string $name, string $message, array $details = []): self
    {
        return new self($name, ConnectionCheckStatus::Warning, $message, $details);
    }

    /**
     * @param  list<string>  $details
     */
    public static function failed(string $name, string $message, array $details = []): self
    {
        return new self($name, ConnectionCheckStatus::Failed, $message, $details);
    }
}
