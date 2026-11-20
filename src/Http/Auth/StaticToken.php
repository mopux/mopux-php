<?php

declare(strict_types=1);

namespace Mopux\Http\Auth;

use InvalidArgumentException;

/**
 * A fixed bearer token.
 */
final class StaticToken implements TokenProvider
{
    public function __construct(private readonly string $token)
    {
        if (trim($token) === '') {
            throw new InvalidArgumentException('The Mopux API token cannot be an empty string.');
        }
    }

    public function token(): string
    {
        return $this->token;
    }

    /**
     * Sanctum personal access tokens look like `id|secret`; anything else is treated as OAuth.
     */
    public function isPersonalAccessToken(): bool
    {
        return str_contains($this->token, '|');
    }
}
