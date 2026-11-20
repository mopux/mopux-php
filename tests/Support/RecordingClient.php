<?php

declare(strict_types=1);

namespace Mopux\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal PSR-18 client for internal tests: returns queued responses and records requests.
 */
final class RecordingClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue;

    public function __construct(ResponseInterface|ClientExceptionInterface ...$responses)
    {
        $this->queue = array_values($responses);
    }

    /**
     * @param  array<array-key, mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public static function json(int $status, array|string $body, array $headers = []): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'] + $headers,
            is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? throw new \LogicException('No queued response.');

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function last(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)] ?? throw new \LogicException('No request sent.');
    }
}
