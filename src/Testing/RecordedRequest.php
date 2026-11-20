<?php

declare(strict_types=1);

namespace Mopux\Testing;

use Psr\Http\Message\RequestInterface;

/**
 * A request captured by MockClient, with the API path and decoded payload at hand.
 */
final class RecordedRequest
{
    public readonly string $method;

    /** Path relative to `/api/v1/`, without query string (e.g. `tasks/12`). */
    public readonly string $path;

    /** @var array<array-key, mixed> */
    public readonly array $query;

    /** @var array<array-key, mixed>|null Decoded JSON body, when the body is JSON. */
    public readonly ?array $json;

    public function __construct(public readonly RequestInterface $request)
    {
        $this->method = $request->getMethod();
        $this->path = self::apiPath($request->getUri()->getPath());

        parse_str($request->getUri()->getQuery(), $query);
        $this->query = $query;

        $body = (string) $request->getBody();
        $decoded = $body !== '' && str_contains($request->getHeaderLine('Content-Type'), 'json')
            ? json_decode($body, true)
            : null;
        $this->json = is_array($decoded) ? $decoded : null;

        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }
    }

    public function body(): string
    {
        return (string) $this->request->getBody();
    }

    public function header(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    /**
     * Matches `METHOD path` or `path` patterns; `*` matches any characters.
     */
    public function matches(string $pattern): bool
    {
        $pattern = trim($pattern);
        $method = null;

        if (preg_match('/^([A-Z]+)\s+(.+)$/', $pattern, $parts) === 1) {
            [, $method, $pattern] = $parts;
        }

        if ($method !== null && $method !== $this->method) {
            return false;
        }

        $regex = '#^'.str_replace('\*', '.*', preg_quote(trim($pattern, '/'), '#')).'$#';

        return preg_match($regex, $this->path) === 1;
    }

    private static function apiPath(string $path): string
    {
        $path = ltrim($path, '/');
        $position = strpos($path, 'api/v1/');

        return $position === false ? $path : substr($path, $position + strlen('api/v1/'));
    }
}
