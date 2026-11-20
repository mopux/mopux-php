<?php

declare(strict_types=1);

namespace Mopux\Http;

use BackedEnum;
use DateTimeInterface;

/**
 * Builds query strings the way Laravel expects them.
 *
 * @internal
 */
final class Query
{
    /**
     * Null values are dropped, booleans become 1/0, backed enums use their value,
     * dates use `Y-m-d` and lists are sent as `key[]=a&key[]=b`.
     *
     * @param  array<string, mixed>  $query
     */
    public static function build(array $query): string
    {
        $normalized = self::normalize($query);

        if ($normalized === []) {
            return '';
        }

        $string = http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);

        // key%5B0%5D=a -> key%5B%5D=a for list values.
        return (string) preg_replace('/%5B\d+%5D=/', '%5B%5D=', $string);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function normalize(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[$key] = match (true) {
                is_bool($value) => $value ? 1 : 0,
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d'),
                is_array($value) => self::normalize($value),
                default => $value,
            };
        }

        return $normalized;
    }
}
