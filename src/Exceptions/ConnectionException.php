<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * The request could not be completed (DNS, TLS, timeout, connection reset...).
 *
 * For writes (POST, PATCH, PUT, DELETE) the outcome is uncertain: the server may
 * have applied the change before the connection failed. Refresh the state before
 * retrying, and never blindly retry a creation.
 */
class ConnectionException extends MopuxException
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        ClientExceptionInterface $previous,
    ) {
        parent::__construct(
            sprintf('Could not reach the Mopux API (%s %s): %s', $method, $path, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public function isWriteOutcomeUncertain(): bool
    {
        return ! in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
