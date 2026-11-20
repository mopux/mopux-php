<?php

declare(strict_types=1);

namespace Mopux\Data;

/**
 * An immutable object built from an API payload.
 */
interface DataObject
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): static;

    /**
     * The object in the API's snake_case shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
