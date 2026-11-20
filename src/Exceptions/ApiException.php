<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * The API answered with an HTTP error status. Subclasses map specific statuses.
 */
class ApiException extends MopuxException
{
    /**
     * @param  array<array-key, mixed>|null  $body  Decoded JSON body, when it was valid JSON.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $method,
        public readonly string $path,
        public readonly ?array $body,
        public readonly ResponseInterface $response,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Value of `data.error` in the error envelope, when present.
     */
    public function error(): ?string
    {
        $data = $this->body['data'] ?? null;
        $error = is_array($data) ? ($data['error'] ?? null) : null;

        return is_string($error) ? $error : null;
    }
}
