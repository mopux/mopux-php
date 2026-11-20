<?php

declare(strict_types=1);

namespace Mopux\Http;

use Mopux\Exceptions\MalformedResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * A successful API response, unwrapped from the `{success, message, data}` envelope.
 */
final class ApiResponse
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly int $statusCode,
        public readonly ?string $message,
        public readonly mixed $data,
        public readonly ResponseInterface $response,
    ) {}

    /**
     * The `data` member as an array; throws when the API returned something else.
     *
     * @return array<array-key, mixed>
     */
    public function array(): array
    {
        if (! is_array($this->data)) {
            throw new MalformedResponseException(
                $this->method,
                $this->path,
                $this->statusCode,
                (string) $this->response->getBody(),
                '"data" is not an object or array',
            );
        }

        return $this->data;
    }

    /**
     * A nested member of `data` as an array (for example `user` in `{data: {user: {...}}}`).
     *
     * @return array<array-key, mixed>
     */
    public function arrayAt(string $key): array
    {
        $value = $this->array()[$key] ?? null;

        if (! is_array($value)) {
            throw new MalformedResponseException(
                $this->method,
                $this->path,
                $this->statusCode,
                (string) $this->response->getBody(),
                sprintf('"data.%s" is missing or not an object', $key),
            );
        }

        return $value;
    }

    public function header(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }
}
