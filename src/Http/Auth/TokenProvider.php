<?php

declare(strict_types=1);

namespace Mopux\Http\Auth;

/**
 * Supplies the bearer token sent with every request.
 *
 * Implement it to read tokens from a cache, a database or an OAuth flow. The API
 * accepts Sanctum personal access tokens (`id|secret`) and, when the installation
 * enables OAuth, Passport access tokens with the `mopux:read` scope for reads and
 * `mopux:write` for writes. Tokens created for the MCP connector are rejected.
 */
interface TokenProvider
{
    public function token(): string;
}
