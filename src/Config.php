<?php

declare(strict_types=1);

namespace Mopux;

use InvalidArgumentException;
use Mopux\Http\Auth\StaticToken;
use Mopux\Http\Auth\TokenProvider;
use Mopux\Http\RetryPolicy;

/**
 * Immutable client configuration.
 *
 * `baseUrl` is the tenant URL (for example `https://acme.mopux.com`); the client
 * appends `/api/v1/` itself. The token can be a Sanctum personal access token
 * (`id|secret`), an OAuth access token or a TokenProvider that resolves it lazily.
 * Timeouts are applied when the SDK builds the default Guzzle client; a PSR-18
 * client passed in by the caller keeps its own settings.
 */
final class Config
{
    public readonly string $baseUrl;

    public readonly ?TokenProvider $tokenProvider;

    public function __construct(
        string $baseUrl,
        string|TokenProvider|null $token = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly ?string $userAgent = null,
        public readonly RetryPolicy $retry = new RetryPolicy,
    ) {
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
        $this->tokenProvider = is_string($token) ? new StaticToken($token) : $token;

        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new InvalidArgumentException('Mopux timeouts must be greater than zero.');
        }
    }

    /**
     * Current bearer token, or null for unauthenticated requests (login).
     */
    public function token(): ?string
    {
        return $this->tokenProvider?->token();
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return new self($baseUrl, $this->tokenProvider, $this->timeout, $this->connectTimeout, $this->userAgent, $this->retry);
    }

    public function withToken(string|TokenProvider|null $token): self
    {
        return new self($this->baseUrl, $token, $this->timeout, $this->connectTimeout, $this->userAgent, $this->retry);
    }

    public function withTimeouts(float $timeout, float $connectTimeout): self
    {
        return new self($this->baseUrl, $this->tokenProvider, $timeout, $connectTimeout, $this->userAgent, $this->retry);
    }

    public function withUserAgent(?string $userAgent): self
    {
        return new self($this->baseUrl, $this->tokenProvider, $this->timeout, $this->connectTimeout, $userAgent, $this->retry);
    }

    public function withRetry(RetryPolicy $retry): self
    {
        return new self($this->baseUrl, $this->tokenProvider, $this->timeout, $this->connectTimeout, $this->userAgent, $retry);
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
