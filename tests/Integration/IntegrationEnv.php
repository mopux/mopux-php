<?php

declare(strict_types=1);

namespace Mopux\Tests\Integration;

use GuzzleHttp\Client;
use Mopux\Config;
use Mopux\Http\RetryPolicy;
use Mopux\Mopux;

/**
 * Reads the integration settings from the environment or tests/Integration/.env.
 */
final class IntegrationEnv
{
    /** @var array<string, string>|null */
    private static ?array $file = null;

    public static function get(string $key): ?string
    {
        $value = getenv($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        self::$file ??= self::readFile(__DIR__.'/.env');
        $value = self::$file[$key] ?? '';

        return $value === '' ? null : $value;
    }

    public static function configured(): bool
    {
        return self::get('MOPUX_INTEGRATION_URL') !== null
            && (self::get('MOPUX_INTEGRATION_TOKEN') !== null
                || (self::get('MOPUX_INTEGRATION_EMAIL') !== null && self::get('MOPUX_INTEGRATION_PASSWORD') !== null));
    }

    /**
     * A client for the configured tenant, logging in when no token is given.
     */
    public static function client(): Mopux
    {
        $url = (string) self::get('MOPUX_INTEGRATION_URL');
        $options = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => false,
            'verify' => self::get('MOPUX_INTEGRATION_INSECURE') !== '1',
        ];

        // Resolve the tenant host to another address (e.g. the web server container
        // when PHP runs inside Docker/Podman and *.test points to localhost).
        $resolve = self::get('MOPUX_INTEGRATION_RESOLVE');

        if ($resolve !== null) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'http' ? 80 : 443);
            $options['curl'] = [CURLOPT_RESOLVE => [$host.':'.$port.':'.gethostbyname($resolve)]];
        }

        $http = new Client($options);
        $config = new Config($url, userAgent: 'mopux-php-integration-tests', retry: new RetryPolicy(maxRetries: 2));
        $token = self::get('MOPUX_INTEGRATION_TOKEN');

        if ($token !== null) {
            return new Mopux($config->withToken($token), httpClient: $http);
        }

        return Mopux::login($config, (string) self::get('MOPUX_INTEGRATION_EMAIL'), (string) self::get('MOPUX_INTEGRATION_PASSWORD'), 'mopux-php-integration', $http);
    }

    /**
     * @return array<string, string>
     */
    private static function readFile(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), '"\'');
        }

        return $values;
    }
}
