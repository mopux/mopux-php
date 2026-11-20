<?php

declare(strict_types=1);

namespace Mopux\Support;

use DateTimeInterface;
use Mopux\Data\DataObject;

/**
 * Serialization helpers shared by the data objects.
 *
 * @internal
 */
final class Format
{
    public static function dateTime(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    public static function date(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * @param  list<DataObject>  $items
     * @return list<array<string, mixed>>
     */
    public static function list(array $items): array
    {
        return array_map(static fn (DataObject $item): array => $item->toArray(), $items);
    }
}
