<?php

declare(strict_types=1);

use Mopux\Config;
use Mopux\Data\AuthToken;
use Mopux\Data\User;
use Mopux\Exceptions\AuthenticationException;
use Mopux\Exceptions\AuthorizationException;
use Mopux\Exceptions\ValidationException;
use Mopux\Http\RetryPolicy;
use Mopux\Mopux;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

function loginResponse(): MockResponse
{
    return MockResponse::ok([
        'token' => '12|plain-text-token',
        'token_type' => 'Bearer',
        'user' => ['id' => 3, 'name' => 'Ada', 'email' => 'ada@acme.test'],
    ], 'User login successfully.');
}

function authConfig(?string $token = null): Config
{
    return new Config('https://acme.mopux.com', $token, retry: RetryPolicy::none());
}

it('logs in and returns an authenticated client', function (): void {
    $client = new MockClient(['POST auth/login' => loginResponse(), 'GET auth/user' => MockResponse::ok(['user' => ['id' => 3, 'name' => 'Ada', 'email' => 'ada@acme.test']])]);

    $mopux = Mopux::login(authConfig(), 'ada@acme.test', 'secret', 'my-app', $client);
    $mopux->auth()->user();

    $login = $client->recorded()[0];
    expect($login->json)->toBe(['email' => 'ada@acme.test', 'password' => 'secret', 'token_name' => 'my-app'])
        ->and($login->request->hasHeader('Authorization'))->toBeFalse()
        ->and($client->lastRecorded()->header('Authorization'))->toBe('Bearer 12|plain-text-token')
        ->and($mopux->config()->token())->toBe('12|plain-text-token')
        ->and($mopux->lastLogin())->toBeInstanceOf(AuthToken::class)
        ->and($mopux->lastLogin()?->user->email)->toBe('ada@acme.test');
});

it('returns the token data from the auth resource', function (): void {
    $client = new MockClient([loginResponse()]);

    $token = (new Mopux(authConfig(), httpClient: $client))->auth()->login('ada@acme.test', 'secret');

    expect($token->token)->toBe('12|plain-text-token')
        ->and($token->tokenType)->toBe('Bearer')
        ->and($token->user->id)->toBe(3)
        ->and($token->user->name)->toBe('Ada')
        ->and($token->user->createdAt)->toBeNull()
        ->and($token->toArray()['user']['email'])->toBe('ada@acme.test')
        ->and($client->lastRecorded()->json)->toBe(['email' => 'ada@acme.test', 'password' => 'secret']);
});

it('throws on wrong credentials', function (): void {
    $client = new MockClient([MockResponse::json(401, ['success' => false, 'message' => 'Unauthorised.', 'data' => ['error' => 'Unauthorised']])]);

    expect(fn () => Mopux::login(authConfig(), 'ada@acme.test', 'wrong', httpClient: $client))
        ->toThrow(AuthenticationException::class, 'Unauthorised.');
});

it('throws when api access is not enabled', function (): void {
    $client = new MockClient([MockResponse::forbidden('API access is not enabled for this account.')]);

    expect(fn () => Mopux::login(authConfig(), 'ada@acme.test', 'secret', httpClient: $client))
        ->toThrow(AuthorizationException::class, 'API access is not enabled');
});

it('throws validation errors on login', function (): void {
    $client = new MockClient([MockResponse::validation(['email' => 'The email field must be a valid email address.'])]);

    try {
        Mopux::login(authConfig(), 'not-an-email', 'secret', httpClient: $client);
        $this->fail('Expected an exception.');
    } catch (ValidationException $exception) {
        expect($exception->firstError('email'))->toBe('The email field must be a valid email address.');
    }
});

it('gets the authenticated user', function (): void {
    $client = new MockClient(['GET auth/user' => MockResponse::ok(['user' => [
        'id' => 3,
        'name' => 'Ada',
        'email' => 'ada@acme.test',
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => null,
        'last_login_at' => '2026-11-20 09:00:00',
        'last_login_ip' => '10.0.0.1',
        'media_id' => 8,
        'avatar' => 'https://acme.mopux.com/a.png',
        'future_field' => 'ignored',
    ]])]);

    $user = (new Mopux(authConfig('1|t'), httpClient: $client))->auth()->user();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->createdAt?->format('Y-m-d H:i:s'))->toBe('2026-01-02 03:04:05')
        ->and($user->updatedAt)->toBeNull()
        ->and($user->lastLoginAt?->format('Y-m-d'))->toBe('2026-11-20')
        ->and($user->lastLoginIp)->toBe('10.0.0.1')
        ->and($user->mediaId)->toBe(8)
        ->and($user->avatar)->toBe('https://acme.mopux.com/a.png')
        ->and($user->toArray()['created_at'])->toBe('2026-01-02 03:04:05');
});

it('logs out', function (): void {
    $client = new MockClient(['POST auth/logout' => MockResponse::ok([], 'User logout successfully.')]);

    (new Mopux(authConfig('1|t'), httpClient: $client))->auth()->logout();

    $client->assertSent('POST auth/logout', 1);
    expect($client->lastRecorded()->body())->toBe('');
});

it('lists and revokes the API tokens of the user', function (): void {
    $client = new MockClient([
        'GET auth/tokens' => MockResponse::ok([
            ['id' => 9, 'name' => 'desktop', 'current' => false, 'last_used_at' => '2026-11-21 10:00:00', 'expires_at' => null, 'created_at' => '2026-11-01 09:00:00'],
            ['id' => 7, 'name' => 'script', 'current' => true, 'last_used_at' => null, 'expires_at' => null, 'created_at' => '2026-10-01 09:00:00'],
        ]),
        'DELETE auth/tokens/9' => MockResponse::ok([], 'API token revoked successfully.'),
    ]);
    $tokens = (new Mopux(authConfig('7|secret'), httpClient: $client))->auth()->tokens();

    $list = $tokens->list();
    $tokens->delete(9);

    expect($list)->toHaveCount(2)
        ->and($list[0]->id)->toBe(9)
        ->and($list[0]->current)->toBeFalse()
        ->and($list[0]->lastUsedAt?->format('Y-m-d H:i:s'))->toBe('2026-11-21 10:00:00')
        ->and($list[1]->current)->toBeTrue()
        ->and($list[1]->lastUsedAt)->toBeNull()
        ->and($list[1]->toArray()['name'])->toBe('script')
        ->and($client->lastRecorded()->request->getMethod())->toBe('DELETE')
        ->and((string) $client->lastRecorded()->request->getUri())->toEndWith('/api/v1/auth/tokens/9');
});

it('reports a token of another user as not found', function (): void {
    $client = new MockClient([MockResponse::notFound()]);

    expect(fn () => (new Mopux(authConfig('7|secret'), httpClient: $client))->auth()->tokens()->delete(5))
        ->toThrow(\Mopux\Exceptions\NotFoundException::class);
});
