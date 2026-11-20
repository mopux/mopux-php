<?php

declare(strict_types=1);

use Mopux\Config;
use Mopux\Exceptions\ConnectionException;
use Mopux\Exceptions\RateLimitException;
use Mopux\Exceptions\ServerException;
use Mopux\Exceptions\UncertainWriteException;
use Mopux\Http\Connector;
use Mopux\Http\RetryPolicy;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;
use Mopux\Testing\NetworkError;

/**
 * @param  list<int>  $sleeps  Receives every delay, in milliseconds.
 */
function retryingConnector(MockClient $client, array &$sleeps, int $maxRetries = 2): Connector
{
    $policy = new RetryPolicy(maxRetries: $maxRetries, baseDelayMs: 100, maxDelayMs: 1_000, sleeper: function (int $ms) use (&$sleeps): void {
        $sleeps[] = $ms;
    });

    return new Connector(new Config('https://acme.mopux.com', '1|t', retry: $policy), $client);
}

it('never retries a write that failed with a server error', function (string $method): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::serverError(), MockResponse::ok()]);

    $connector = retryingConnector($client, $sleeps);
    expect(fn () => $connector->request($method, 'tasks', json: ['title' => 'x']))
        ->toThrow(UncertainWriteException::class);

    $client->assertSentCount(1);
    expect($sleeps)->toBe([]);
})->with(['POST', 'PATCH', 'DELETE']);

it('never retries a write that failed with a network error', function (): void {
    $sleeps = [];
    $client = new MockClient([new NetworkError, MockResponse::ok()]);

    try {
        retryingConnector($client, $sleeps)->post('tasks', ['title' => 'x']);
        $this->fail('Expected an exception.');
    } catch (ConnectionException $exception) {
        expect($exception->isWriteOutcomeUncertain())->toBeTrue();
    }

    $client->assertSentCount(1);
});

it('retries a rate limited get honouring retry-after', function (): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::tooManyRequests(2), MockResponse::ok(['id' => 1])]);

    $response = retryingConnector($client, $sleeps)->get('projects/1');

    expect($response->data)->toBe(['id' => 1])
        ->and($sleeps)->toBe([2000]);
    $client->assertSentCount(2);
});

it('retries a rate limited write because the api throttles before running it', function (): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::tooManyRequests(1), MockResponse::ok(['task' => ['id' => 9]])]);

    retryingConnector($client, $sleeps)->post('tasks', ['title' => 'x']);

    $client->assertSentCount(2);
    expect($client->recorded()[1]->json)->toBe(['title' => 'x'])
        ->and($sleeps)->toBe([1000]);
});

it('uses exponential backoff for 429 without retry-after', function (): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::tooManyRequests(), MockResponse::tooManyRequests(), MockResponse::ok()]);

    retryingConnector($client, $sleeps)->get('projects');

    expect($sleeps)->toHaveCount(2)
        ->and($sleeps[0])->toBeGreaterThanOrEqual(100)->toBeLessThanOrEqual(125)
        ->and($sleeps[1])->toBeGreaterThanOrEqual(200)->toBeLessThanOrEqual(250);
});

it('does not wait for a retry-after longer than the limit', function (): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::tooManyRequests(3600), MockResponse::ok()]);

    $connector = retryingConnector($client, $sleeps);
    expect(fn () => $connector->get('projects'))->toThrow(RateLimitException::class);
    $client->assertSentCount(1);
});

it('retries reads after server and network errors up to the limit', function (): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::serverError(503), new NetworkError, MockResponse::serverError(502)]);

    $connector = retryingConnector($client, $sleeps);
    expect(fn () => $connector->get('tasks/my'))->toThrow(ServerException::class);

    $client->assertSentCount(3);
    expect($sleeps)->toHaveCount(2);
});

it('recovers a read after a transient failure', function (): void {
    $sleeps = [];
    $client = new MockClient([new NetworkError, MockResponse::ok(['ok' => true])]);

    expect(retryingConnector($client, $sleeps)->get('team')->data)->toBe(['ok' => true]);
});

it('can be disabled', function (): void {
    $sleeps = [];
    $client = new MockClient([MockResponse::tooManyRequests(1), MockResponse::ok()]);

    $connector = retryingConnector($client, $sleeps, maxRetries: 0);
    expect(fn () => $connector->get('projects'))->toThrow(RateLimitException::class);
    $client->assertSentCount(1);
});

it('is enabled by default with two retries', function (): void {
    $policy = (new Config('https://a.test'))->retry;

    expect($policy->maxRetries)->toBe(2)
        ->and(RetryPolicy::none()->maxRetries)->toBe(0)
        ->and(fn () => new RetryPolicy(maxRetries: -1))->toThrow(InvalidArgumentException::class);
});
