<?php

declare(strict_types=1);

namespace Mopux;

use Mopux\Data\AuthToken;
use Mopux\Http\Auth\TokenProvider;
use Mopux\Http\Connector;
use Mopux\Resources\Auth;
use Mopux\Resources\Comments;
use Mopux\Resources\Notifications;
use Mopux\Resources\Projects;
use Mopux\Resources\TaskGroups;
use Mopux\Resources\Tasks;
use Mopux\Resources\Team;
use Mopux\Resources\TeamInvitations;
use Mopux\Resources\Touches;
use Psr\Http\Client\ClientInterface;

/**
 * Entry point of the Mopux API client.
 *
 *     $mopux = new Mopux('https://acme.mopux.com', $token);
 *     $mopux = Mopux::login('https://acme.mopux.com', $email, $password, 'my-app');
 *
 * Instances are immutable; `forTenant()` and `withToken()` return new clients that
 * share the underlying HTTP client.
 */
final class Mopux
{
    private readonly Connector $connector;

    private ?AuthToken $lastLogin = null;

    /**
     * @param  string|Config|Connector  $baseUrl  Tenant URL, a full Config or a ready Connector.
     * @param  string|TokenProvider|null  $token  Ignored when a Config or Connector is given.
     * @param  ClientInterface|null  $httpClient  Any PSR-18 client (defaults to Guzzle or discovery).
     */
    public function __construct(
        string|Config|Connector $baseUrl,
        string|TokenProvider|null $token = null,
        ?ClientInterface $httpClient = null,
    ) {
        $this->connector = match (true) {
            $baseUrl instanceof Connector => $baseUrl,
            $baseUrl instanceof Config => new Connector($baseUrl, $httpClient),
            default => new Connector(new Config($baseUrl, $token), $httpClient),
        };
    }

    /**
     * Logs in with email and password and returns a client authenticated with the new token.
     *
     * Read the token with `$mopux->config()->token()` to store it and avoid logging in again.
     */
    public static function login(
        string|Config $baseUrl,
        string $email,
        string $password,
        ?string $tokenName = 'mopux-php',
        ?ClientInterface $httpClient = null,
    ): self {
        $guest = new self($baseUrl instanceof Config ? $baseUrl->withToken(null) : $baseUrl, null, $httpClient);
        $token = $guest->auth()->login($email, $password, $tokenName);

        return $guest->withToken($token->token)->withLastLogin($token);
    }

    public function forTenant(string $baseUrl, string|TokenProvider|null $token): self
    {
        return new self($this->connector->withConfig($this->connector->config()->withBaseUrl($baseUrl)->withToken($token)));
    }

    public function withToken(string|TokenProvider|null $token): self
    {
        return new self($this->connector->withToken($token));
    }

    public function withConfig(Config $config): self
    {
        return new self($this->connector->withConfig($config));
    }

    public function config(): Config
    {
        return $this->connector->config();
    }

    public function connector(): Connector
    {
        return $this->connector;
    }

    /**
     * Token and user returned by Mopux::login(), when this client was created that way.
     */
    public function lastLogin(): ?AuthToken
    {
        return $this->lastLogin;
    }

    public function auth(): Auth
    {
        return new Auth($this->connector);
    }

    public function projects(): Projects
    {
        return new Projects($this->connector);
    }

    public function taskGroups(): TaskGroups
    {
        return new TaskGroups($this->connector);
    }

    public function tasks(): Tasks
    {
        return new Tasks($this->connector);
    }

    public function comments(): Comments
    {
        return new Comments($this->connector);
    }

    public function notifications(): Notifications
    {
        return new Notifications($this->connector);
    }

    public function touches(): Touches
    {
        return new Touches($this->connector);
    }

    public function team(): Team
    {
        return new Team($this->connector);
    }

    public function teamInvitations(): TeamInvitations
    {
        return new TeamInvitations($this->connector);
    }

    private function withLastLogin(AuthToken $token): self
    {
        $this->lastLogin = $token;

        return $this;
    }
}
