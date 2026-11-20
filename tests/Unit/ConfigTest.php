<?php

declare(strict_types=1);

use Mopux\Config;

it('normalizes the base url and builds the api url', function (string $input): void {
    $config = new Config($input);

    expect($config->baseUrl)->toBe('https://acme.mopux.com')
        ->and($config->apiUrl())->toBe('https://acme.mopux.com/api/v1/');
})->with([
    'plain' => 'https://acme.mopux.com',
    'trailing slash' => 'https://acme.mopux.com/',
    'with api prefix' => 'https://acme.mopux.com/api/v1/',
    'with spaces' => '  https://acme.mopux.com  ',
]);

it('rejects invalid base urls', function (string $input): void {
    new Config($input);
})->with(['acme.mopux.com', 'ftp://acme.mopux.com', ''])->throws(InvalidArgumentException::class);

it('rejects non-positive timeouts and empty tokens', function (): void {
    expect(fn () => new Config('https://a.test', timeout: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Config('https://a.test', connectTimeout: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Config('https://a.test', token: '  '))->toThrow(InvalidArgumentException::class);
});

it('is immutable', function (): void {
    $config = new Config('https://a.test', token: '1|abc');
    $other = $config->withToken('2|def')->withBaseUrl('https://b.test')->withTimeouts(5, 2)->withUserAgent('my-app/1.0');

    expect($config->token())->toBe('1|abc')
        ->and($config->baseUrl)->toBe('https://a.test')
        ->and($other->token())->toBe('2|def')
        ->and($other->baseUrl)->toBe('https://b.test')
        ->and($other->timeout)->toBe(5.0)
        ->and($other->connectTimeout)->toBe(2.0)
        ->and($other->userAgent)->toBe('my-app/1.0');
});
