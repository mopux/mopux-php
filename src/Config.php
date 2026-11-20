<?php

declare(strict_types=1);

namespace Mopux;

use InvalidArgumentException;

/**
 * Immutable client configuration.
 *
 * `baseUrl` is the tenant URL (for example `https://acme.mopux.com`); the client
 * appends `/api/v1/` itself. Timeouts are applied when the SDK builds the default
 * Guzzle client; a PSR-18 client passed in by the caller keeps its own settings.
 */
final class Config
{
    public readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        public readonly ?string $token = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly ?string $userAgent = null,
    ) {
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);

        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new InvalidArgumentException('Mopux timeouts must be greater than zero.');
        }

        if ($token !== null && trim($token) === '') {
            throw new InvalidArgumentException('The Mopux API token cannot be an empty string.');
        }
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return new self($baseUrl, $this->token, $this->timeout, $this->connectTimeout, $this->userAgent);
    }

    public function withToken(?string $token): self
    {
        return new self($this->baseUrl, $token, $this->timeout, $this->connectTimeout, $this->userAgent);
    }

    public function withTimeouts(float $timeout, float $connectTimeout): self
    {
        return new self($this->baseUrl, $this->token, $timeout, $connectTimeout, $this->userAgent);
    }

    public function withUserAgent(?string $userAgent): self
    {
        return new self($this->baseUrl, $this->token, $this->timeout, $this->connectTimeout, $userAgent);
    }

    /**
     * Base URL of the versioned API, always ending with a slash.
     */
    public function apiUrl(): string
    {
        return $this->baseUrl.'/api/'.Version::API.'/';
    }

    private static function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        // Accept URLs that already include the API prefix.
        $baseUrl = (string) preg_replace('#/api(/v1)?$#', '', $baseUrl);

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            throw new InvalidArgumentException(sprintf(
                'The Mopux base URL must be an absolute http(s) URL, "%s" given.',
                $baseUrl,
            ));
        }

        return $baseUrl;
    }
}
