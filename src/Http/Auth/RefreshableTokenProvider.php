<?php

declare(strict_types=1);

namespace Mopux\Http\Auth;

/**
 * A token provider that can obtain a new token (e.g. an OAuth refresh).
 *
 * When a request is rejected with 401, the client calls refresh() once and sends
 * the request again with the new token. Repeating it is safe: authentication is
 * checked before the API runs the request.
 */
interface RefreshableTokenProvider extends TokenProvider
{
    /**
     * Obtains a new token; return false when it cannot be refreshed.
     */
    public function refresh(): bool;
}
