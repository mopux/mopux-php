<?php

declare(strict_types=1);

namespace Mopux\Http;

use Mopux\Exceptions\ApiException;
use Mopux\Exceptions\AuthenticationException;
use Mopux\Exceptions\AuthorizationException;
use Mopux\Exceptions\ClientException;
use Mopux\Exceptions\NotFoundException;
use Mopux\Exceptions\RateLimitException;
use Mopux\Exceptions\ServerException;
use Mopux\Exceptions\UncertainWriteException;
use Mopux\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps an HTTP error response to the matching SDK exception.
 *
 * The API wraps most errors as `{success: false, message, data: {error, errors?}}`,
 * but some (429, 500, billing 403) use Laravel's plain `{message}` body; both are handled.
 *
 * @internal
 */
final class ErrorMapper
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  array<array-key, mixed>|null  $body
     */
    public static function map(string $method, string $path, ResponseInterface $response, ?array $body): ApiException
    {
        $status = $response->getStatusCode();
        $message = self::message($status, $response, $body);
        $isWrite = ! in_array($method, self::SAFE_METHODS, true);

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => AuthorizationException::class,
            $status === 404 => NotFoundException::class,
            $status === 422 => ValidationException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 && $isWrite => UncertainWriteException::class,
            $status >= 500 => ServerException::class,
            default => ClientException::class,
        };

        if ($class === UncertainWriteException::class) {
            $message .= ' The write outcome is uncertain: refresh the state before retrying.';
        }

        return new $class($message, $status, $method, $path, $body, $response);
    }

    /**
     * @param  array<array-key, mixed>|null  $body
     */
    private static function message(int $status, ResponseInterface $response, ?array $body): string
    {
        $message = $body['message'] ?? null;

        if (! is_string($message) || trim($message) === '') {
            $data = $body['data'] ?? null;
            $message = is_array($data) ? ($data['error'] ?? null) : null;
        }

        if (! is_string($message) || trim($message) === '') {
            $reason = $response->getReasonPhrase();
            $message = $reason !== '' ? $reason : 'Mopux API error';
        }

        return sprintf('%s (HTTP %d)', $message, $status);
    }
}
