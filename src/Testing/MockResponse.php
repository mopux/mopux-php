<?php

declare(strict_types=1);

namespace Mopux\Testing;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\ResponseInterface;

/**
 * Fake API response for MockClient, following the Mopux response envelope.
 */
final class MockResponse
{
    /**
     * @param  array<array-key, mixed>|string  $body  Array bodies are JSON encoded.
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array|string $body,
        public readonly array $headers = [],
    ) {}

    /**
     * Successful envelope: `{success: true, message, data}`.
     */
    public static function ok(mixed $data = [], string $message = 'OK', int $status = 200): self
    {
        return new self($status, ['success' => true, 'message' => $message, 'data' => $data]);
    }

    /**
     * Error envelope: `{success: false, message, data: {error}}`.
     */
    public static function error(int $status, string $message = 'Error'): self
    {
        return new self($status, ['success' => false, 'message' => $message, 'data' => ['error' => $message]]);
    }

    /**
     * 422 with `data.errors`.
     *
     * @param  array<string, list<string>|string>  $errors
     */
    public static function validation(array $errors, string $message = 'The given data was invalid.'): self
    {
        $errors = array_map(static fn (array|string $messages): array => (array) $messages, $errors);

        return new self(422, [
            'success' => false,
            'message' => $message,
            'data' => ['error' => 'Validation failed.', 'errors' => $errors],
        ]);
    }

    public static function unauthenticated(): self
    {
        return self::error(401, 'Unauthenticated.');
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): self
    {
        return self::error(403, $message);
    }

    public static function notFound(): self
    {
        return self::error(404, 'Resource not found.');
    }

    public static function tooManyRequests(?int $retryAfter = null): self
    {
        return new self(429, ['message' => 'Too Many Attempts.'], $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter]);
    }

    public static function serverError(int $status = 500): self
    {
        return new self($status, ['message' => 'Server Error']);
    }

    /**
     * Raw response with any status and body.
     *
     * @param  array<array-key, mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public static function json(int $status, array|string $body, array $headers = []): self
    {
        return new self($status, $body, $headers);
    }

    public function toPsrResponse(): ResponseInterface
    {
        $body = is_string($this->body) ? $this->body : json_encode($this->body, JSON_THROW_ON_ERROR);

        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse($this->status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream($body));

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
