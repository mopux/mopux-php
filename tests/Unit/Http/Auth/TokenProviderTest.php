<?php

declare(strict_types=1);

use Mopux\Config;
use Mopux\Exceptions\AuthenticationException;
use Mopux\Exceptions\MopuxException;
use Mopux\Http\Auth\CallbackToken;
use Mopux\Http\Auth\StaticToken;
use Mopux\Http\Connector;
use Mopux\Http\RetryPolicy;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

it('wraps string tokens in a static provider', function (): void {
    $config = new Config('https://a.test', '5|abc');

    expect($config->tokenProvider)->toBeInstanceOf(StaticToken::class)
        ->and($config->token())->toBe('5|abc')
        ->and((new StaticToken('5|abc'))->isPersonalAccessToken())->toBeTrue()
        ->and((new StaticToken('eyJ0eXAi'))->isPersonalAccessToken())->toBeFalse()
        ->and((new Config('https://a.test'))->token())->toBeNull()
        ->and(fn () => new StaticToken(''))->toThrow(InvalidArgumentException::class);
});

it('resolves callback tokens on every request', function (): void {
    $tokens = ['first', 'second'];
    $client = new MockClient([MockResponse::ok(), MockResponse::ok()]);
    $connector = new Connector(new Config('https://a.test', new CallbackToken(function () use (&$tokens): string {
        return array_shift($tokens) ?? 'none';
    }), retry: RetryPolicy::none()), $client);

    $connector->get('team');
    $connector->get('team');

    expect($client->recorded()[0]->header('Authorization'))->toBe('Bearer first')
        ->and($client->recorded()[1]->header('Authorization'))->toBe('Bearer second');
});

it('rejects empty callback tokens', function (): void {
    expect(fn () => (new CallbackToken(fn () => ''))->token())->toThrow(MopuxException::class);
});

it('refreshes the token once after a 401 and resends the request', function (): void {
    $current = 'expired';
    $refreshes = 0;
    $provider = new CallbackToken(
        function () use (&$current): string {
            return $current;
        },
        refresh: function () use (&$current, &$refreshes): bool {
            $refreshes++;
            $current = 'fresh';

            return true;
        },
    );
    $client = new MockClient([MockResponse::unauthenticated(), MockResponse::ok(['task' => ['id' => 1]])]);
    $connector = new Connector(new Config('https://a.test', $provider, retry: RetryPolicy::none()), $client);

    $connector->post('tasks', ['title' => 'x']);

    expect($refreshes)->toBe(1)
        ->and($client->recorded()[0]->header('Authorization'))->toBe('Bearer expired')
        ->and($client->recorded()[1]->header('Authorization'))->toBe('Bearer fresh')
        ->and($client->recorded()[1]->json)->toBe(['title' => 'x']);
});

it('gives up when the refreshed token is also rejected or cannot refresh', function (bool $canRefresh, int $expectedRequests): void {
    $provider = new CallbackToken(fn (): string => 'token', refresh: fn (): bool => $canRefresh);
    $client = new MockClient([MockResponse::unauthenticated(), MockResponse::unauthenticated(), MockResponse::unauthenticated()]);
    $connector = new Connector(new Config('https://a.test', $provider, retry: RetryPolicy::none()), $client);

    expect(fn () => $connector->get('team'))->toThrow(AuthenticationException::class);
    $client->assertSentCount($expectedRequests);
})->with([
    'refresh succeeds but token still invalid' => [true, 2],
    'refresh not possible' => [false, 1],
]);

it('does not refresh static tokens', function (): void {
    $client = new MockClient([MockResponse::unauthenticated(), MockResponse::ok()]);

    expect(fn () => (new Connector(new Config('https://a.test', '1|t', retry: RetryPolicy::none()), $client))->get('team'))
        ->toThrow(AuthenticationException::class);
    $client->assertSentCount(1);
});
