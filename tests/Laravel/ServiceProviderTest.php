<?php

declare(strict_types=1);

use Mopux\Exceptions\MopuxException;
use Mopux\Laravel\Facades\Mopux as MopuxFacade;
use Mopux\Laravel\MopuxServiceProvider;
use Mopux\Mopux;
use Mopux\Resources\Tasks;

it('registers the client as a singleton built from config', function (): void {
    $mopux = app(Mopux::class);

    expect($mopux)->toBeInstanceOf(Mopux::class)
        ->and(app(Mopux::class))->toBe($mopux)
        ->and(app('mopux'))->toBe($mopux)
        ->and($mopux->config()->baseUrl)->toBe('https://acme.mopux.com')
        ->and($mopux->config()->token())->toBe('1|laravel-token')
        ->and($mopux->config()->timeout)->toBe(30.0)
        ->and($mopux->config()->retry->maxRetries)->toBe(2);
});

it('merges the package configuration', function (): void {
    expect(config('mopux.connect_timeout'))->toBe(10.0)
        ->and(config('mopux.retry.max_retry_after_seconds'))->toBe(60);
});

it('applies custom configuration', function (): void {
    config([
        'mopux.url' => 'https://beta.mopux.com/',
        'mopux.token' => null,
        'mopux.timeout' => 5,
        'mopux.connect_timeout' => '2.5',
        'mopux.user_agent' => 'Intranet',
        'mopux.retry.max_retries' => 0,
    ]);
    app()->forgetInstance(Mopux::class);

    $config = app(Mopux::class)->config();

    expect($config->baseUrl)->toBe('https://beta.mopux.com')
        ->and($config->token())->toBeNull()
        ->and($config->timeout)->toBe(5.0)
        ->and($config->connectTimeout)->toBe(2.5)
        ->and($config->userAgent)->toBe('Intranet')
        ->and($config->retry->maxRetries)->toBe(0);
});

it('explains a missing url when the client is resolved', function (): void {
    config(['mopux.url' => null]);
    app()->forgetInstance(Mopux::class);

    expect(fn () => app(Mopux::class))->toThrow(MopuxException::class, 'set MOPUX_URL');
});

it('publishes the config file', function (): void {
    expect(MopuxServiceProvider::pathsToPublish(MopuxServiceProvider::class, 'mopux-config'))
        ->toHaveCount(1)
        ->and(array_values(MopuxServiceProvider::pathsToPublish(MopuxServiceProvider::class, 'mopux-config'))[0])->toBe(config_path('mopux.php'));
});

it('resolves resources through the facade', function (): void {
    expect(MopuxFacade::config()->baseUrl)->toBe('https://acme.mopux.com')
        ->and(MopuxFacade::tasks())->toBeInstanceOf(Tasks::class)
        ->and(MopuxFacade::getFacadeRoot())->toBe(app(Mopux::class));
});

it('creates tenant clients without touching the default one', function (): void {
    $tenant = MopuxFacade::forTenant('https://beta.mopux.com', '9|beta');

    expect($tenant)->toBeInstanceOf(Mopux::class)
        ->and($tenant->config()->baseUrl)->toBe('https://beta.mopux.com')
        ->and($tenant->config()->token())->toBe('9|beta')
        ->and(app(Mopux::class)->config()->baseUrl)->toBe('https://acme.mopux.com')
        ->and($tenant->config()->retry->maxRetries)->toBe(2);
});

it('is registered by package discovery', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composer['extra']['laravel']['providers'])->toBe([MopuxServiceProvider::class])
        ->and($composer['extra']['laravel']['aliases'])->toBe(['Mopux' => MopuxFacade::class]);
});
