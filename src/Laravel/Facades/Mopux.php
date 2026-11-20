<?php

declare(strict_types=1);

namespace Mopux\Laravel\Facades;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Facade;
use Mopux\Config;
use Mopux\Http\RetryPolicy;
use Mopux\Laravel\Testing\MopuxFake;
use Mopux\Mopux as MopuxClient;
use Mopux\Testing\MockClient;

/**
 * @method static \Mopux\Resources\Auth auth()
 * @method static \Mopux\Resources\Projects projects()
 * @method static \Mopux\Resources\TaskGroups taskGroups()
 * @method static \Mopux\Resources\Tasks tasks()
 * @method static \Mopux\Resources\Comments comments()
 * @method static \Mopux\Resources\Notifications notifications()
 * @method static \Mopux\Resources\Touches touches()
 * @method static \Mopux\Resources\Team team()
 * @method static \Mopux\Resources\TeamInvitations teamInvitations()
 * @method static MopuxClient forTenant(string $baseUrl, string|\Mopux\Http\Auth\TokenProvider|null $token)
 * @method static MopuxClient withToken(string|\Mopux\Http\Auth\TokenProvider|null $token)
 * @method static Config config()
 * @method static void assertSent(string|Closure $expectation, ?int $times = null)
 * @method static void assertNotSent(string|Closure $expectation)
 * @method static void assertSentCount(int $count)
 * @method static void assertNothingSent()
 *
 * @see MopuxClient
 *
 * @phpstan-import-type MockReply from MockClient
 */
final class Mopux extends Facade
{
    /**
     * Replaces the client with one that answers from a MockClient and records requests.
     * Also rebinds `Mopux\Mopux` in the container, so injected clients are faked too.
     *
     * @param  array<array-key, MockReply|list<MockReply>>  $responses  Same format as the MockClient constructor.
     */
    public static function fake(array $responses = []): MopuxFake
    {
        $client = new MockClient($responses);

        $app = self::getFacadeApplication();
        $config = $app?->bound('config') === true ? $app->make('config') : null;
        $configured = $config instanceof Repository ? $config->get('mopux.url') : null;
        $url = is_string($configured) && $configured !== '' ? $configured : 'https://mopux.test';
        $token = $config instanceof Repository ? $config->get('mopux.token') : null;

        $mopux = new MopuxClient(
            new Config($url, is_string($token) && $token !== '' ? $token : 'fake|token', retry: RetryPolicy::none()),
            httpClient: $client,
        );
        $fake = new MopuxFake($mopux, $client);

        $app?->instance(MopuxClient::class, $mopux);
        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'mopux';
    }
}
