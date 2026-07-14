<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

final class FlickrResponseHelper
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function listItems(array $data, string ...$keys): array
    {
        $node = $data;
        foreach ($keys as $key) {
            if (! is_array($node) || ! array_key_exists($key, $node)) {
                return [];
            }
            $node = $node[$key];
        }

        if (! is_array($node)) {
            return [];
        }

        if ($node === []) {
            return [];
        }

        if (array_is_list($node)) {
            /** @var list<array<string, mixed>> $node */
            return array_values(array_filter($node, is_array(...)));
        }

        /** @var array<string, mixed> $node */
        return [$node];
    }
}
