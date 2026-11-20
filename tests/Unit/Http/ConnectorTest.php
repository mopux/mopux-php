<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Mopux\Config;
use Mopux\Exceptions\ApiException;
use Mopux\Exceptions\AuthenticationException;
use Mopux\Exceptions\AuthorizationException;
use Mopux\Exceptions\ClientException;
use Mopux\Exceptions\ConnectionException;
use Mopux\Exceptions\MalformedResponseException;
use Mopux\Exceptions\NotFoundException;
use Mopux\Exceptions\RateLimitException;
use Mopux\Exceptions\ServerException;
use Mopux\Exceptions\UncertainWriteException;
use Mopux\Exceptions\ValidationException;
use Mopux\Http\Connector;
use Mopux\Http\RetryPolicy;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;
use Mopux\Testing\NetworkError;
use Mopux\Version;

function connector(MockClient $client, ?string $token = '1|secret', ?string $userAgent = null, ?RetryPolicy $retry = null): Connector
{
    $factory = new HttpFactory;

    return new Connector(new Config('https://acme.mopux.com', $token, userAgent: $userAgent, retry: $retry ?? RetryPolicy::none()), $client, $factory, $factory);
}

function envelope(mixed $data, string $message = 'OK'): array
{
    return ['success' => true, 'message' => $message, 'data' => $data];
}

it('sends authenticated json requests to the versioned api', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope(['id' => 7], 'Created'))]);

    $response = connector($client, userAgent: 'my-app/2.0')->post('/tasks', ['title' => 'Écrire', 'hours' => 1.0]);

    $request = $client->lastRequest();
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://acme.mopux.com/api/v1/tasks')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer 1|secret')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($request->getHeaderLine('User-Agent'))->toStartWith('mopux-php/'.Version::SDK.' (PHP ')->toEndWith(' my-app/2.0')
        ->and((string) $request->getBody())->toBe('{"title":"Écrire","hours":1.0}')
        ->and($response->statusCode)->toBe(200)
        ->and($response->message)->toBe('Created')
        ->and($response->data)->toBe(['id' => 7])
        ->and($response->array())->toBe(['id' => 7]);
});

it('sends query strings and no authorization header without a token', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope([]))]);

    connector($client, token: null)->get('projects', ['search' => 'web', 'page' => null]);

    expect((string) $client->lastRequest()->getUri())->toBe('https://acme.mopux.com/api/v1/projects?search=web')
        ->and($client->lastRequest()->hasHeader('Authorization'))->toBeFalse()
        ->and($client->lastRequest()->hasHeader('Content-Type'))->toBeFalse();
});

it('encodes an empty json payload as an object', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope([]))]);

    connector($client)->patch('notifications/read-all', []);

    expect((string) $client->lastRequest()->getBody())->toBe('{}');
});

it('sends raw bodies with custom headers', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope([]))]);
    $connector = connector($client);

    $connector->request('POST', 'tasks/1/files', body: $connector->streamFactory()->createStream('--b--'), headers: ['Content-Type' => 'multipart/form-data; boundary=b']);

    expect((string) $client->lastRequest()->getBody())->toBe('--b--')
        ->and($client->lastRequest()->getHeaderLine('Content-Type'))->toBe('multipart/form-data; boundary=b');
});

it('is immutable and shares the http client', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope([])), MockResponse::json(200, envelope([]))]);
    $original = connector($client);

    $original->withToken('2|other')->withBaseUrl('https://beta.mopux.com')->get('team');
    $original->get('team');

    expect((string) $client->requests()[0]->getUri())->toBe('https://beta.mopux.com/api/v1/team')
        ->and($client->requests()[0]->getHeaderLine('Authorization'))->toBe('Bearer 2|other')
        ->and((string) $client->requests()[1]->getUri())->toBe('https://acme.mopux.com/api/v1/team')
        ->and($client->requests()[1]->getHeaderLine('Authorization'))->toBe('Bearer 1|secret');
});

it('maps http error statuses to exceptions', function (int $status, string $method, string $class, array|string $body, string $message): void {
    $client = new MockClient([MockResponse::json($status, $body)]);

    try {
        connector($client)->request($method, 'tasks/5');
        $this->fail('Expected an exception.');
    } catch (ApiException $exception) {
        expect($exception)->toBeInstanceOf($class)
            ->and($exception::class)->toBe($class)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->getCode())->toBe($status)
            ->and($exception->method)->toBe($method)
            ->and($exception->path)->toBe('tasks/5')
            ->and($exception->getMessage())->toStartWith($message);
    }
})->with([
    '401' => [401, 'GET', AuthenticationException::class, ['success' => false, 'message' => 'Unauthenticated.', 'data' => ['error' => 'Unauthenticated.']], 'Unauthenticated. (HTTP 401)'],
    '403 envelope' => [403, 'GET', AuthorizationException::class, ['success' => false, 'message' => 'Insufficient OAuth scope.', 'data' => ['error' => 'Insufficient OAuth scope.']], 'Insufficient OAuth scope.'],
    '403 billing without data' => [403, 'PATCH', AuthorizationException::class, ['success' => false, 'message' => 'Trial expired.'], 'Trial expired.'],
    '404' => [404, 'DELETE', NotFoundException::class, ['success' => false, 'message' => 'Resource not found.', 'data' => ['error' => 'Resource not found.']], 'Resource not found.'],
    '422' => [422, 'POST', ValidationException::class, ['success' => false, 'message' => 'The title field is required.', 'data' => ['error' => 'Validation failed.', 'errors' => ['title' => ['The title field is required.']]]], 'The title field is required.'],
    '429 plain laravel' => [429, 'GET', RateLimitException::class, ['message' => 'Too Many Attempts.'], 'Too Many Attempts. (HTTP 429)'],
    '400' => [400, 'POST', ClientException::class, ['success' => false, 'message' => 'OAuth requires HTTPS.'], 'OAuth requires HTTPS.'],
    '409 non json' => [409, 'PATCH', ClientException::class, 'Conflict!', 'Conflict (HTTP 409)'],
    '500 read' => [500, 'GET', ServerException::class, ['message' => 'Server Error'], 'Server Error (HTTP 500)'],
    '503 read html' => [503, 'GET', ServerException::class, '<html>down</html>', 'Service Unavailable (HTTP 503)'],
    '500 post' => [500, 'POST', UncertainWriteException::class, ['message' => 'Server Error'], 'Server Error (HTTP 500)'],
    '502 patch' => [502, 'PATCH', UncertainWriteException::class, '', 'Bad Gateway (HTTP 502)'],
    '500 delete' => [500, 'DELETE', UncertainWriteException::class, ['message' => 'Server Error'], 'Server Error (HTTP 500)'],
]);

it('explains that a failed write has an uncertain outcome', function (): void {
    $client = new MockClient([MockResponse::json(500, ['message' => 'Server Error'])]);

    expect(fn () => connector($client)->post('tasks', ['title' => 'x']))
        ->toThrow(UncertainWriteException::class, 'refresh the state before retrying');
});

it('exposes validation errors and the error envelope', function (): void {
    $client = new MockClient([MockResponse::json(422, [
        'success' => false,
        'message' => 'The title field is required. (and 1 more error)',
        'data' => ['error' => 'Validation failed.', 'errors' => ['title' => ['The title field is required.'], 'hours_expected' => ['Bad format.', 'Too long.']]],
    ])]);

    try {
        connector($client)->post('tasks', []);
        $this->fail('Expected an exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['title' => ['The title field is required.'], 'hours_expected' => ['Bad format.', 'Too long.']])
            ->and($exception->firstError('hours_expected'))->toBe('Bad format.')
            ->and($exception->firstError('missing'))->toBeNull()
            ->and($exception->error())->toBe('Validation failed.')
            ->and($exception->body)->toBeArray()
            ->and($exception->response->getStatusCode())->toBe(422);
    }
});

it('reads retry-after from rate limit responses', function (): void {
    $client = new MockClient([MockResponse::json(429, ['message' => 'Too Many Attempts.'], ['Retry-After' => '42'])]);

    try {
        connector($client)->get('projects');
        $this->fail('Expected an exception.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter)->toBe(42);
    }
});

it('parses retry-after http dates', function (): void {
    $now = strtotime('2026-11-20 10:00:00 GMT');

    expect(RateLimitException::parseRetryAfter('Fri, 20 Nov 2026 10:00:30 GMT', $now))->toBe(30)
        ->and(RateLimitException::parseRetryAfter('Fri, 20 Nov 2026 09:00:00 GMT', $now))->toBe(0)
        ->and(RateLimitException::parseRetryAfter(''))->toBeNull()
        ->and(RateLimitException::parseRetryAfter('soon'))->toBeNull();
});

it('rejects malformed successful responses', function (MockResponse|Response $response, string $reason): void {
    $client = new MockClient([$response]);

    expect(fn () => connector($client)->get('team'))->toThrow(MalformedResponseException::class, $reason);
})->with([
    'not json' => [new Response(200, [], '<html></html>'), 'body is not a JSON object'],
    'empty body' => [new Response(204), 'body is not a JSON object'],
    'success false' => [MockResponse::json(200, ['success' => false, 'message' => 'x', 'data' => []]), '"success" is not true'],
    'missing data' => [MockResponse::json(200, ['success' => true, 'message' => 'x']), '"data" is missing'],
    'redirect' => [new Response(302, ['Location' => 'https://acme.mopux.com/login']), 'unexpected HTTP status'],
]);

it('throws when data is not an array and an array was expected', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope('text'))]);

    $response = connector($client)->get('tasks/markdown-help');

    expect($response->data)->toBe('text')
        ->and(fn () => $response->array())->toThrow(MalformedResponseException::class, '"data" is not an object or array');
});

it('reads nested data members', function (): void {
    $client = new MockClient([MockResponse::json(200, envelope(['user' => ['id' => 1]]))]);

    $response = connector($client)->get('auth/user');

    expect($response->arrayAt('user'))->toBe(['id' => 1])
        ->and(fn () => $response->arrayAt('token'))->toThrow(MalformedResponseException::class, '"data.token" is missing');
});

it('wraps transport failures and flags uncertain writes', function (string $method, bool $uncertain): void {
    $networkError = new NetworkError('Connection timed out');
    $client = new MockClient([$networkError]);

    try {
        connector($client)->request($method, 'tasks');
        $this->fail('Expected an exception.');
    } catch (ConnectionException $exception) {
        expect($exception->getMessage())->toContain('Connection timed out')->toContain($method.' tasks')
            ->and($exception->getPrevious())->toBeInstanceOf(NetworkError::class)
            ->and($exception->isWriteOutcomeUncertain())->toBe($uncertain);
    }
})->with([
    'get' => ['GET', false],
    'post' => ['POST', true],
    'delete' => ['DELETE', true],
]);
