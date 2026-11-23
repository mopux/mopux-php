<?php

declare(strict_types=1);

namespace Mopux\Laravel\Testing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mopux\Exceptions\MopuxException;
use Mopux\Mopux;
use Mopux\Testing\MockClient;
use Mopux\Testing\RecordedRequest;
use Mopux\Webhooks\WebhookEventType;
use Mopux\Webhooks\WebhookSignature;

/**
 * What `Mopux::fake()` installs behind the facade: the real client wired to a
 * MockClient, plus the MockClient assertions.
 *
 *     Mopux::fake(['GET tasks/my' => MockResponse::ok([...])]);
 *     // ... code under test ...
 *     Mopux::assertSent('GET tasks/my');
 *
 * @mixin Mopux
 */
final class MopuxFake
{
    public function __construct(
        public readonly Mopux $mopux,
        public readonly MockClient $client,
    ) {}

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->mopux->{$method}(...$arguments);
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)  $expectation
     */
    public function assertSent(string|Closure $expectation, ?int $times = null): void
    {
        $this->client->assertSent($expectation, $times);
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)  $expectation
     */
    public function assertNotSent(string|Closure $expectation): void
    {
        $this->client->assertNotSent($expectation);
    }

    /**
     * Build a signed incoming request with the configured endpoint secret.
     *
     * @param  array<string, mixed>  $data
     */
    public static function webhook(WebhookEventType|string $event, array $data, string $uri = '/webhooks/mopux'): Request
    {
        $secret = config('mopux.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new MopuxException('Set config("mopux.webhook_secret") before creating a signed webhook.');
        }

        $event = $event instanceof WebhookEventType ? $event->value : $event;
        $timestamp = time();
        $body = json_encode([
            'id' => (string) Str::uuid(),
            'event' => $event,
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z', $timestamp),
            'installation' => 'mopux.test',
            'actor' => null,
            'data' => (object) $data,
        ], JSON_THROW_ON_ERROR);

        return Request::create($uri, 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MOPUX_EVENT' => $event,
            'HTTP_X_MOPUX_DELIVERY' => (string) Str::uuid(),
            'HTTP_X_MOPUX_SIGNATURE' => WebhookSignature::sign($body, $secret, $timestamp),
        ], content: $body);
    }

    public function assertSentCount(int $count): void
    {
        $this->client->assertSentCount($count);
    }

    public function assertNothingSent(): void
    {
        $this->client->assertNothingSent();
    }

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->client->recorded();
    }
}
