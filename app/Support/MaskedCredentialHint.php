<?php

declare(strict_types=1);

namespace App\Support;

final class MaskedCredentialHint
{
    public static function for(string $value, int $visibleTail = 4): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (strlen($trimmed) <= $visibleTail) {
            return str_repeat('*', strlen($trimmed));
        }

        return str_repeat('*', max(4, strlen($trimmed) - $visibleTail)).substr($trimmed, -$visibleTail);
    }

    public static function leadingAndTrailing(string $value): string
    {
        if (strlen($value) <= 8) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4).'…'.substr($value, -4);
    }
}
