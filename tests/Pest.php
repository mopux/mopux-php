<?php

declare(strict_types=1);
use Mopux\Config;
use Mopux\Http\RetryPolicy;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

/*
|--------------------------------------------------------------------------
| Test configuration
|--------------------------------------------------------------------------
*/

/**
 * Decoded JSON fixture from tests/fixtures.
 *
 * @return array<array-key, mixed>
 */
function apiFixture(string $name): array
{
    $decoded = json_decode((string) file_get_contents(__DIR__.'/fixtures/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);

    return is_array($decoded) ? $decoded : throw new LogicException("Fixture {$name} is not an object.");
}

/**
 * Client wired to a MockClient, without retries.
 */
function mopux(MockClient $client, string $token = '1|test'): Mopux\Mopux
{
    return new Mopux\Mopux(
        new Config('https://acme.mopux.com', $token, retry: RetryPolicy::none()),
        httpClient: $client,
    );
}

/**
 * Successful envelope around a fixture.
 */
function okFixture(string $name, string $message = 'OK'): MockResponse
{
    return MockResponse::ok(apiFixture($name), $message);
}
