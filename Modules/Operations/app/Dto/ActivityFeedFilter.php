<?php

declare(strict_types=1);

namespace Modules\Operations\Dto;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ActivityFeedFilter
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $level = null,
        public readonly ?string $actionPrefix = null,
        public readonly ?string $correlationId = null,
        public readonly ?DateTimeInterface $from = null,
        public readonly ?DateTimeInterface $to = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}

    /**
     * @param  array{
     *     type?: string|null,
     *     level?: string|null,
     *     action_prefix?: string|null,
     *     correlation_id?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     page?: int,
     *     per_page?: int
     * }  $input
     */
    public static function fromArray(array $input): self
    {
        $perPage = min(50, max(1, (int) ($input['per_page'] ?? 15)));
        $page = max(1, (int) ($input['page'] ?? 1));

        return new self(
            type: self::nullableString($input['type'] ?? null),
            level: self::nullableString($input['level'] ?? null),
            actionPrefix: self::nullableString($input['action_prefix'] ?? null),
            correlationId: self::nullableString($input['correlation_id'] ?? null),
            from: self::parseDate($input['from'] ?? null) ?? CarbonImmutable::now()->subDay(),
            to: self::parseDate($input['to'] ?? null),
            page: $page,
            perPage: $perPage,
        );
    }

    public function withoutLevel(): self
    {
        return new self(
            type: $this->type,
            level: null,
            actionPrefix: $this->actionPrefix,
            correlationId: $this->correlationId,
            from: $this->from,
            to: $this->to,
            page: $this->page,
            perPage: $this->perPage,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function parseDate(mixed $value): ?DateTimeInterface
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
