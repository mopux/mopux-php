<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * A response field is missing or has an unexpected type while building a data object.
 */
class InvalidPayloadException extends MopuxException
{
    public function __construct(public readonly string $field, string $expected, mixed $actual)
    {
        parent::__construct(sprintf(
            'Unexpected Mopux API payload: "%s" should be %s, %s given.',
            $field,
            $expected,
            get_debug_type($actual),
        ));
    }
}
