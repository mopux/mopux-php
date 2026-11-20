<?php

declare(strict_types=1);

use Mopux\Config;
use Mopux\Http\Connector;
use Mopux\Http\RetryPolicy;
use Mopux\Mopux;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

it('builds a client from url and token', function (): void {
    $client = new MockClient([MockResponse::ok(['user' => ['id' => 1, 'name' => 'A', 'email' => 'a@a.test']])]);

    $mopux = new Mopux('https://acme.mopux.com/', '1|t', $client);
    $mopux->auth()->user();

    expect($mopux->config()->baseUrl)->toBe('https://acme.mopux.com')
        ->and((string) $client->lastRequest()->getUri())->toBe('https://acme.mopux.com/api/v1/auth/user')
        ->and($client->lastRecorded()->header('Authorization'))->toBe('Bearer 1|t');
});

it('accepts a config or a connector', function (): void {
    $config = new Config('https://acme.mopux.com', '1|t', retry: RetryPolicy::none());
    $connector = new Connector($config, new MockClient);

    expect((new Mopux($config, httpClient: new MockClient))->config())->toBe($config)
        ->and((new Mopux($connector))->connector())->toBe($connector);
});

it('switches tenant and token immutably', function (): void {
    $client = new MockClient(['auth/user' => MockResponse::ok(['user' => ['id' => 1, 'name' => 'A', 'email' => 'a@a.test']])]);
    $mopux = new Mopux('https://acme.mopux.com', '1|t', $client);

    $other = $mopux->forTenant('https://beta.mopux.com', '2|u');
    $other->auth()->user();
    $mopux->withToken('3|v')->auth()->user();

    expect((string) $client->requests()[0]->getUri())->toBe('https://beta.mopux.com/api/v1/auth/user')
        ->and($client->recorded()[0]->header('Authorization'))->toBe('Bearer 2|u')
        ->and($client->recorded()[1]->header('Authorization'))->toBe('Bearer 3|v')
        ->and($mopux->config()->baseUrl)->toBe('https://acme.mopux.com')
        ->and($mopux->config()->token())->toBe('1|t')
        ->and($mopux->lastLogin())->toBeNull();
});
