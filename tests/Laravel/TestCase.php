<?php

declare(strict_types=1);

namespace Mopux\Tests\Laravel;

use Mopux\Laravel\Facades\Mopux;
use Mopux\Laravel\MopuxServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MopuxServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Mopux' => Mopux::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mopux.url', 'https://acme.mopux.com');
        $app['config']->set('mopux.token', '1|laravel-token');
    }
}
