<?php

declare(strict_types=1);

namespace Mopux\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mopux\Config;
use Mopux\Exceptions\MopuxException;
use Mopux\Http\RetryPolicy;
use Mopux\Mopux;

/**
 * Registers a Mopux client built from config/mopux.php.
 *
 * Inject `Mopux\Mopux` or use the `Mopux` facade. For several installations
 * (multi-tenant), call `forTenant($url, $token)` on it.
 */
final class MopuxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/mopux.php', 'mopux');

        $this->app->singleton(Mopux::class, static function (Application $app): Mopux {
            /** @var Repository $config */
            $config = $app->make('config');

            return new Mopux(self::config($config));
        });

        $this->app->alias(Mopux::class, 'mopux');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/mopux.php' => $this->app->configPath('mopux.php'),
            ], ['mopux', 'mopux-config']);
        }
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [Mopux::class, 'mopux'];
    }

    private static function config(Repository $config): Config
    {
        $url = $config->get('mopux.url');

        if (! is_string($url) || trim($url) === '') {
            throw new MopuxException('The Mopux URL is not configured: set MOPUX_URL or config("mopux.url").');
        }

        $token = $config->get('mopux.token');
        $userAgent = $config->get('mopux.user_agent');

        return new Config(
            baseUrl: $url,
            token: is_string($token) && trim($token) !== '' ? $token : null,
            timeout: self::float($config->get('mopux.timeout'), 30.0),
            connectTimeout: self::float($config->get('mopux.connect_timeout'), 10.0),
            userAgent: is_string($userAgent) && $userAgent !== '' ? $userAgent : null,
            retry: new RetryPolicy(
                maxRetries: self::int($config->get('mopux.retry.max_retries'), 2),
                baseDelayMs: self::int($config->get('mopux.retry.base_delay_ms'), 250),
                maxDelayMs: self::int($config->get('mopux.retry.max_delay_ms'), 5000),
                maxRetryAfterSeconds: self::int($config->get('mopux.retry.max_retry_after_seconds'), 60),
            ),
        );
    }

    private static function float(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    private static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
