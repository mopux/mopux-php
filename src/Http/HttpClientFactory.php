<?php

declare(strict_types=1);

namespace Mopux\Http;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Mopux\Config;
use Psr\Http\Client\ClientInterface;

/**
 * Builds the default PSR-18 client: Guzzle with the configured timeouts when it is
 * installed, otherwise whatever implementation php-http/discovery finds.
 *
 * @internal
 */
final class HttpClientFactory
{
    public static function make(Config $config): ClientInterface
    {
        if (class_exists(GuzzleClient::class)) {
            return new GuzzleClient([
                'timeout' => $config->timeout,
                'connect_timeout' => $config->connectTimeout,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
