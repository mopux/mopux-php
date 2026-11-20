<?php

declare(strict_types=1);

namespace Mopux\Testing;

use Closure;
use LogicException;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Fake PSR-18 client for tests: answers with queued or route-matched responses and
 * records every request so it can be asserted.
 *
 *     $client = new MockClient([
 *         'GET projects' => MockResponse::ok(['data' => [], 'meta' => [], 'links' => []]),
 *         'POST tasks' => MockResponse::ok(['task' => [...]], 'Task created'),
 *         'tasks/*' => fn (RecordedRequest $request) => MockResponse::notFound(),
 *     ]);
 *
 * String keys are route patterns (`METHOD path` or `path`, `*` wildcard, query ignored);
 * list items form a queue consumed in order when no route matches. A response can be a
 * MockResponse, a PSR-7 response, a PSR-18 exception (e.g. NetworkError) or a closure
 * receiving the RecordedRequest; a list of them under one route is consumed in sequence.
 *
 * @phpstan-type MockReply MockResponse|ResponseInterface|ClientExceptionInterface|Closure(RecordedRequest): (MockResponse|ResponseInterface)
 */
final class MockClient implements ClientInterface
{
    /** @var list<MockReply> */
    private array $queue = [];

    /** @var array<string, list<MockReply>> */
    private array $routes = [];

    /** @var array<string, MockReply> Last reply of each route, repeated once the sequence is exhausted. */
    private array $lastRouteReply = [];

    /** @var list<RecordedRequest> */
    private array $recorded = [];

    /**
     * @param  array<array-key, MockReply|list<MockReply>>  $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $key => $response) {
            if (is_string($key)) {
                $this->addRoute($key, $response);
            } elseif (is_array($response)) {
                array_push($this->queue, ...$response);
            } else {
                $this->queue[] = $response;
            }
        }
    }

    /**
     * Appends responses to the queue.
     *
     * @param  MockReply  ...$responses
     */
    public function push(MockResponse|ResponseInterface|ClientExceptionInterface|Closure ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * @param  MockReply|list<MockReply>  $response
     */
    public function addRoute(string $pattern, MockResponse|ResponseInterface|ClientExceptionInterface|Closure|array $response): self
    {
        $replies = is_array($response) ? $response : [$response];
        $this->routes[$pattern] = array_merge($this->routes[$pattern] ?? [], $replies);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $recorded = new RecordedRequest($request);
        $this->recorded[] = $recorded;

        $reply = $this->replyFor($recorded);

        if ($reply instanceof Closure) {
            $reply = $reply($recorded);
        }

        if ($reply instanceof NetworkError) {
            throw $reply->forRequest($request);
        }

        if ($reply instanceof ClientExceptionInterface) {
            throw $reply;
        }

        return $reply instanceof MockResponse ? $reply->toPsrResponse() : $reply;
    }

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_map(static fn (RecordedRequest $recorded): RequestInterface => $recorded->request, $this->recorded);
    }

    public function lastRequest(): RequestInterface
    {
        return $this->lastRecorded()->request;
    }

    public function lastRecorded(): RecordedRequest
    {
        return $this->recorded[array_key_last($this->recorded) ?? -1] ?? throw new LogicException('MockClient has not received any request.');
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)  $expectation  Route pattern or callback.
     */
    public function assertSent(string|Closure $expectation, ?int $times = null): void
    {
        $count = count($this->matching($expectation));
        $label = is_string($expectation) ? sprintf('"%s"', $expectation) : 'matching the callback';

        if ($times === null) {
            self::assert($count > 0, sprintf('Expected a request %s to be sent, none was. Sent: %s.', $label, $this->summary()));

            return;
        }

        self::assert($count === $times, sprintf('Expected %d request(s) %s, %d sent. Sent: %s.', $times, $label, $count, $this->summary()));
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)  $expectation
     */
    public function assertNotSent(string|Closure $expectation): void
    {
        $label = is_string($expectation) ? sprintf('"%s"', $expectation) : 'matching the callback';

        self::assert($this->matching($expectation) === [], sprintf('Unexpected request %s was sent.', $label));
    }

    public function assertSentCount(int $count): void
    {
        self::assert(count($this->recorded) === $count, sprintf('Expected %d request(s), %d sent. Sent: %s.', $count, count($this->recorded), $this->summary()));
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    /**
     * @return MockReply
     */
    private function replyFor(RecordedRequest $request): MockResponse|ResponseInterface|ClientExceptionInterface|Closure
    {
        foreach ($this->routes as $pattern => $replies) {
            if (! $request->matches($pattern)) {
                continue;
            }

            $reply = array_shift($this->routes[$pattern]);

            if ($reply === null) {
                return $this->lastRouteReply[$pattern];
            }

            return $this->lastRouteReply[$pattern] = $reply;
        }

        return array_shift($this->queue) ?? throw new LogicException(sprintf(
            'MockClient has no response for %s %s. Add a route or queue a response.',
            $request->method,
            $request->path,
        ));
    }

    /**
     * @param  string|(Closure(RecordedRequest): bool)  $expectation
     * @return list<RecordedRequest>
     */
    private function matching(string|Closure $expectation): array
    {
        return array_values(array_filter(
            $this->recorded,
            static fn (RecordedRequest $request): bool => is_string($expectation) ? $request->matches($expectation) : $expectation($request),
        ));
    }

    private function summary(): string
    {
        if ($this->recorded === []) {
            return 'none';
        }

        return implode(', ', array_map(static fn (RecordedRequest $request): string => $request->method.' '.$request->path, $this->recorded));
    }

    private static function assert(bool $condition, string $message): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue($condition, $message);

            return;
        }

        if (! $condition) {
            throw new MockAssertionFailed($message);
        }
    }
}
