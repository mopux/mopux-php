<?php

declare(strict_types=1);

namespace Mopux\Http\Auth;

use Closure;
use Mopux\Exceptions\MopuxException;

/**
 * Resolves the token with a closure on every request, and optionally refreshes it.
 *
 *     new CallbackToken(
 *         fn () => cache('mopux.token'),
 *         refresh: fn () => $oauth->refresh(),   // returns bool
 *     );
 */
final class CallbackToken implements RefreshableTokenProvider
{
    /**
     * @param  Closure(): string  $resolve
     * @param  (Closure(): bool)|null  $refresh
     */
    public function __construct(
        private readonly Closure $resolve,
        private readonly ?Closure $refresh = null,
    ) {}

    public function token(): string
    {
        $token = ($this->resolve)();

        if (trim($token) === '') {
            throw new MopuxException('The Mopux token callback returned an empty token.');
        }

        return $token;
    }

    public function refresh(): bool
    {
        return $this->refresh !== null && ($this->refresh)();
    }
}
