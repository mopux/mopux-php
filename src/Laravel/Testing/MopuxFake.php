<?php

declare(strict_types=1);

namespace Mopux\Laravel\Testing;

use Closure;
use Mopux\Mopux;
use Mopux\Testing\MockClient;
use Mopux\Testing\RecordedRequest;

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
