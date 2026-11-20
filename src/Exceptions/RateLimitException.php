<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * HTTP 429: too many requests (login 30/min, rest of the API 120/min per token).
 */
class RateLimitException extends ApiException
{
    /**
     * Seconds to wait before retrying, from the `Retry-After` header, when present.
     */
    public readonly ?int $retryAfter;

    /**
     * @param  array<array-key, mixed>|null  $body
     */
    public function __construct(
        string $message,
        int $statusCode,
        string $method,
        string $path,
        ?array $body,
        ResponseInterface $response,
    ) {
        parent::__construct($message, $statusCode, $method, $path, $body, $response);

        $this->retryAfter = self::parseRetryAfter($response->getHeaderLine('Retry-After'));
    }

    public static function parseRetryAfter(string $header, ?int $now = null): ?int
    {
        $header = trim($header);

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $timestamp = strtotime($header);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - ($now ?? time()));
    }
}
