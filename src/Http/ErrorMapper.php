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
 * API 1.2.0 wraps every error as `{success: false, message, data: {error, code?, errors?}}`.
 * Older servers answer some errors (429, 500, billing 403) with a plain `{message}` body;
 * both are handled.
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
     * The API message followed by the details it carries elsewhere: `data.error` when it adds
     * something (e.g. the reason behind a generic "Forbidden") and, for 422, the field errors
     * not already included in the message.
     *
     * @param  array<array-key, mixed>|null  $body
     */
    private static function message(int $status, ResponseInterface $response, ?array $body): string
    {
        $message = $body['message'] ?? null;
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $error = is_string($data['error'] ?? null) && trim($data['error']) !== '' ? trim($data['error']) : null;

        if (! is_string($message) || trim($message) === '') {
            $message = $error;
        }

        if (! is_string($message) || trim($message) === '') {
            $reason = $response->getReasonPhrase();
            $message = $reason !== '' ? $reason : 'Mopux API error';
        }

        $message = trim($message);
        $details = $status === 422 ? self::validationMessages($data['errors'] ?? null) : array_filter([$error]);

        foreach (array_unique($details) as $detail) {
            if (! str_contains($message, $detail)) {
                $message .= ' '.$detail;
            }
        }

        return sprintf('%s (HTTP %d)', $message, $status);
    }

    /**
     * @return list<string>
     */
    private static function validationMessages(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $messages = [];

        array_walk_recursive($errors, static function (mixed $value) use (&$messages): void {
            if (is_string($value) && trim($value) !== '') {
                $messages[] = trim($value);
            }
        });

        return $messages;
    }
}
