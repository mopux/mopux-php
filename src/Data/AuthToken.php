<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Result of `POST auth/login`: a new Sanctum personal access token and its user.
 *
 * Store the token securely; the API only returns it once.
 */
final class AuthToken implements DataObject
{
    public function __construct(
        public readonly string $token,
        public readonly string $tokenType,
        public readonly User $user,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data);

        return new self(
            token: $payload->string('token'),
            tokenType: $payload->nullableString('token_type') ?? 'Bearer',
            user: User::fromArray($payload->object('user')->all()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'token_type' => $this->tokenType,
            'user' => $this->user->toArray(),
        ];
    }
}
