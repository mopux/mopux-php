<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Mopux\Data\ApiToken;
use Mopux\Exceptions\NotFoundException;
use Mopux\Support\Payload;

/**
 * API personal tokens of the authenticated user (`/api/v1/auth/tokens`).
 * MCP tokens are managed in the web app and never appear here.
 */
final class AuthTokens extends Resource
{
    /**
     * The user's API tokens, newest first, without their secrets.
     * `current` marks the token of this client (always false with OAuth).
     *
     * @return list<ApiToken>
     */
    public function list(): array
    {
        return Payload::of(['tokens' => $this->connector->get('auth/tokens')->array()])->list('tokens', ApiToken::fromArray(...));
    }

    /**
     * Revokes one of the user's API tokens, for example a leaked one.
     * Revoking the current token logs this client out.
     *
     * @throws NotFoundException The token does not exist, belongs to another user or is an MCP token.
     */
    public function delete(int $tokenId): void
    {
        $this->connector->delete('auth/tokens/'.$tokenId);
    }
}
