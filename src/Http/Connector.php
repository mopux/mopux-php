<?php

declare(strict_types=1);

namespace Mopux\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use JsonException;
use Mopux\Config;
use Mopux\Exceptions\ConnectionException;
use Mopux\Exceptions\MalformedResponseException;
use Mopux\Exceptions\MopuxException;
use Mopux\Http\Auth\RefreshableTokenProvider;
use Mopux\Http\Auth\TokenProvider;
use Mopux\Version;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Low-level transport: builds PSR-7 requests against `{baseUrl}/api/v1/`, sends them
 * through a PSR-18 client and unwraps the `{success, message, data}` envelope.
 *
 * Immutable: `withToken()` and `withBaseUrl()` return a new instance sharing the HTTP client.
 */
final class Connector
{
    private readonly ClientInterface $client;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly Config $config,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->client = $client ?? HttpClientFactory::make($config);
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function client(): ClientInterface
    {
        return $this->client;
    }

    public function streamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    public function withConfig(Config $config): self
    {
        return new self($config, $this->client, $this->requestFactory, $this->streamFactory);
    }

    public function withToken(string|TokenProvider|null $token): self
    {
        return $this->withConfig($this->config->withToken($token));
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return $this->withConfig($this->config->withBaseUrl($baseUrl));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param  array<array-key, mixed>|null  $json
     */
    public function post(string $path, ?array $json = null): ApiResponse
    {
        return $this->request('POST', $path, json: $json);
    }

    /**
     * @param  array<array-key, mixed>|null  $json
     */
    public function patch(string $path, ?array $json = null): ApiResponse
    {
        return $this->request('PATCH', $path, json: $json);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function delete(string $path, array $query = []): ApiResponse
    {
        return $this->request('DELETE', $path, $query);
    }

    /**
     * Sends a request and returns the unwrapped response, or throws a mapped exception.
     *
     * @param  string  $path  Path relative to `/api/v1/`, e.g. `tasks/12`.
     * @param  array<string, mixed>  $query  Null values are dropped; booleans become 1/0.
     * @param  array<array-key, mixed>|null  $json  JSON body; an empty array is sent as `{}`.
     * @param  StreamInterface|null  $body  Raw body (multipart uploads); requires `Content-Type` in $headers.
     * @param  array<string, string>  $headers
     *
     * @throws MopuxException
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        ?StreamInterface $body = null,
        array $headers = [],
    ): ApiResponse {
        $method = strtoupper($method);
        $path = ltrim($path, '/');
        $request = $this->buildRequest($method, $path, $query, $json, $body, $headers);
        $retry = $this->config->retry;
        $refreshed = false;

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->client->sendRequest($request);
            } catch (ClientExceptionInterface $exception) {
                $delay = $exception instanceof NetworkExceptionInterface && $this->canResend($request)
                    ? $retry->delayAfterConnectionError($method, $attempt)
                    : null;

                if ($delay === null) {
                    throw new ConnectionException($method, $path, $exception);
                }

                $retry->sleep($delay);
                $this->rewind($request);

                continue;
            }

            if ($response->getStatusCode() === 401 && ! $refreshed && $this->canResend($request) && $this->refreshToken()) {
                $refreshed = true;
                $attempt--;
                $this->rewind($request);
                $request = $request->withHeader('Authorization', 'Bearer '.$this->config->token());

                continue;
            }

            $delay = $this->canResend($request)
                ? $retry->delayAfterResponse($method, $response->getStatusCode(), $response->getHeaderLine('Retry-After'), $attempt)
                : null;

            if ($delay === null) {
                return $this->handle($method, $path, $response);
            }

            $retry->sleep($delay);
            $this->rewind($request);
        }
    }

    private function refreshToken(): bool
    {
        $provider = $this->config->tokenProvider;

        return $provider instanceof RefreshableTokenProvider && $provider->refresh();
    }

    private function rewind(RequestInterface $request): void
    {
        if ($request->getBody()->isSeekable()) {
            $request->getBody()->rewind();
        }
    }

    /**
     * A request can be sent again when it has no body or its body can be rewound.
     */
    private function canResend(RequestInterface $request): bool
    {
        $body = $request->getBody();

        return $body->isSeekable() || $body->getSize() === 0;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<array-key, mixed>|null  $json
     * @param  array<string, string>  $headers
     */
    private function buildRequest(
        string $method,
        string $path,
        array $query,
        ?array $json,
        ?StreamInterface $body,
        array $headers,
    ): RequestInterface {
        $uri = $this->config->apiUrl().$path;
        $queryString = Query::build($query);

        if ($queryString !== '') {
            $uri .= '?'.$queryString;
        }

        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent());

        $token = $this->config->token();

        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer '.$token);
        }

        if ($json !== null) {
            $encoded = json_encode(
                $json === [] ? new \stdClass : $json,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
            );

            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($encoded));
        } elseif ($body !== null) {
            $request = $request->withBody($body);
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function handle(string $method, string $path, ResponseInterface $response): ApiResponse
    {
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = $this->decode($raw);

        if ($status >= 400) {
            throw ErrorMapper::map($method, $path, $response, $decoded);
        }

        if ($status < 200 || $status >= 300) {
            throw new MalformedResponseException($method, $path, $status, $raw, 'unexpected HTTP status');
        }

        if ($decoded === null) {
            throw new MalformedResponseException($method, $path, $status, $raw, 'body is not a JSON object');
        }

        if (($decoded['success'] ?? null) !== true) {
            throw new MalformedResponseException($method, $path, $status, $raw, '"success" is not true');
        }

        if (! array_key_exists('data', $decoded)) {
            throw new MalformedResponseException($method, $path, $status, $raw, '"data" is missing');
        }

        $message = $decoded['message'] ?? null;

        return new ApiResponse(
            $method,
            $path,
            $status,
            is_string($message) ? $message : null,
            $decoded['data'],
            $response,
        );
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function decode(string $raw): ?array
    {
        if (trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function userAgent(): string
    {
        $userAgent = sprintf('mopux-php/%s (PHP %s)', Version::SDK, PHP_VERSION);

        return $this->config->userAgent !== null ? $userAgent.' '.$this->config->userAgent : $userAgent;
    }
}
