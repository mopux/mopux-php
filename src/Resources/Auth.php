<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Mopux\Data\AuthToken;
use Mopux\Data\User;
use Mopux\Exceptions\AuthenticationException;
use Mopux\Exceptions\AuthorizationException;
use Mopux\Exceptions\ValidationException;

/**
 * Authentication endpoints (`/api/v1/auth/*`).
 */
final class Auth extends Resource
{
    /**
     * Exchanges credentials for a new Sanctum personal access token (30 requests/min).
     *
     * The user needs the "API use" permission. Store the returned token: the API
     * shows it only once. Use Mopux::login() to get an authenticated client directly.
     *
     * @throws AuthenticationException Wrong credentials.
     * @throws AuthorizationException API access is not enabled for the user.
     * @throws ValidationException Missing email or password.
     */
    public function login(string $email, string $password, ?string $tokenName = null): AuthToken
    {
        $payload = ['email' => $email, 'password' => $password];

        if ($tokenName !== null) {
            $payload['token_name'] = $tokenName;
        }

        return AuthToken::fromArray($this->connector->post('auth/login', $payload)->array());
    }

    /**
     * Revokes the current token (Sanctum token deleted, OAuth token and refresh token revoked).
     */
    public function logout(): void
    {
        $this->connector->post('auth/logout');
    }

    /**
     * The user that owns the current token.
     */
    public function user(): User
    {
        return User::fromArray($this->connector->get('auth/user')->arrayAt('user'));
    }
}
