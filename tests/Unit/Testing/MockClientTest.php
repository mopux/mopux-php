<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Mopux\Config;
use Mopux\Exceptions\NotFoundException;
use Mopux\Exceptions\ValidationException;
use Mopux\Http\Connector;
use Mopux\Http\RetryPolicy;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;
use Mopux\Testing\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;

function mockConnector(MockClient $client): Connector
{
    return new Connector(new Config('https://acme.mopux.com', '1|t', retry: RetryPolicy::none()), $client);
}

it('answers by route pattern before the queue', function (): void {
    $client = new MockClient([
        'GET projects' => MockResponse::ok(['from' => 'route']),
        'tasks/*' => MockResponse::notFound(),
        MockResponse::ok(['from' => 'queue']),
    ]);
    $connector = mockConnector($client);

    expect($connector->get('projects', ['page' => 2])->data)->toBe(['from' => 'route'])
        ->and($connector->get('team')->data)->toBe(['from' => 'queue'])
        ->and(fn () => $connector->delete('tasks/5'))->toThrow(NotFoundException::class);
});

it('consumes route sequences and repeats the last reply', function (): void {
    $client = new MockClient(['GET team' => [MockResponse::ok(1), MockResponse::ok(2)]]);
    $connector = mockConnector($client);

    expect([$connector->get('team')->data, $connector->get('team')->data, $connector->get('team')->data])->toBe([1, 2, 2]);
});

it('supports closures, psr responses and pushed responses', function (): void {
    $client = new MockClient([
        'POST tasks' => fn (RecordedRequest $request) => MockResponse::ok(['echo' => $request->json['title'] ?? null]),
    ]);
    $client->push(new Response(200, [], '{"success":true,"message":"x","data":[42]}'));
    $connector = mockConnector($client);

    expect($connector->post('tasks', ['title' => 'Hola'])->data)->toBe(['echo' => 'Hola'])
        ->and($connector->get('anything')->data)->toBe([42]);
});

it('builds envelope helpers', function (): void {
    $client = new MockClient([MockResponse::validation(['title' => 'Required.'])]);

    try {
        mockConnector($client)->post('tasks', []);
        $this->fail('Expected an exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['title' => ['Required.']]);
    }
});

it('fails loudly when no response is available', function (): void {
    expect(fn () => mockConnector(new MockClient)->get('team'))
        ->toThrow(LogicException::class, 'MockClient has no response for GET team');
});

it('records requests with path, query and json', function (): void {
    $client = new MockClient([MockResponse::ok(), MockResponse::ok()]);
    $connector = mockConnector($client);

    $connector->get('projects', ['search' => 'web', 'users' => [1, 2]]);
    $connector->patch('tasks/7/status', ['status' => 1]);

    $first = $client->recorded()[0];
    expect($first->method)->toBe('GET')
        ->and($first->path)->toBe('projects')
        ->and($first->query)->toBe(['search' => 'web', 'users' => ['1', '2']])
        ->and($first->json)->toBeNull()
        ->and($client->lastRecorded()->json)->toBe(['status' => 1])
        ->and($client->lastRecorded()->header('Authorization'))->toBe('Bearer 1|t')
        ->and($client->lastRequest()->getMethod())->toBe('PATCH')
        ->and($client->requests())->toHaveCount(2);
});

it('asserts sent requests', function (): void {
    $client = new MockClient([MockResponse::ok(), MockResponse::ok()]);
    $connector = mockConnector($client);

    $client->assertNothingSent();
    $connector->patch('tasks/7/status', ['status' => 1]);
    $connector->patch('tasks/8/status', ['status' => 2]);

    $client->assertSent('PATCH tasks/*/status', 2);
    $client->assertSent('tasks/7/status');
    $client->assertSent(fn (RecordedRequest $request) => ($request->json['status'] ?? null) === 2);
    $client->assertNotSent('DELETE tasks/*');
    $client->assertSentCount(2);

    expect(fn () => $client->assertSent('POST tasks'))->toThrow(AssertionFailedError::class, 'Sent: PATCH tasks/7/status, PATCH tasks/8/status')
        ->and(fn () => $client->assertNotSent('PATCH tasks/7/status'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $client->lastRecorded())->not->toThrow(LogicException::class)
        ->and(fn () => (new MockClient)->lastRecorded())->toThrow(LogicException::class);
});

it('matches patterns against the api path', function (string $pattern, bool $expected): void {
    $client = new MockClient([MockResponse::ok()]);
    mockConnector($client)->get('projects/12/task-groups/3/focus');

    expect($client->lastRecorded()->matches($pattern))->toBe($expected);
})->with([
    ['projects/12/task-groups/3/focus', true],
    ['GET projects/*/focus', true],
    ['/projects/*/', true],
    ['POST projects/*', false],
    ['projects/*', true],
    ['projects', false],
]);
