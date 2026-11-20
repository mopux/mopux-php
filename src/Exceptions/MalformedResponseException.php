<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * The API answered with a body that does not follow the `{success, message, data}` envelope.
 */
class MalformedResponseException extends MopuxException
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly int $statusCode,
        public readonly string $body,
        string $reason = 'unexpected response body',
    ) {
        parent::__construct(sprintf(
            'Malformed Mopux API response (%s %s, HTTP %d): %s.',
            $method,
            $path,
            $statusCode,
            $reason,
        ), $statusCode);
    }
}
