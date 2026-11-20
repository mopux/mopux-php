<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * An input object was built with values the API would reject (checked before sending).
 */
class InvalidInputException extends MopuxException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct(sprintf('Invalid "%s": %s', $field, $message));
    }
}
