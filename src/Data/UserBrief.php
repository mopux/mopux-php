<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * A user as embedded in other objects (assignees, owners, authors, actors...).
 * `email` is only present in a few places (e.g. the author of a team invitation).
 */
final class UserBrief implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $name = null,
        public readonly ?string $avatar = null,
        public readonly ?string $email = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'user');

        return new self(
            id: $payload->int('id'),
            name: $payload->nullableString('name'),
            avatar: $payload->nullableString('avatar'),
            email: $payload->nullableString('email'),
        );
    }

    /**
     * The API embeds missing users as `{id: null, name: null}`; those become null.
     *
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromNullable(?array $data): ?self
    {
        return $data === null || ($data['id'] ?? null) === null ? null : self::fromArray($data);
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'email' => $this->email,
        ], static fn (mixed $value, string $key): bool => $value !== null || $key !== 'email', ARRAY_FILTER_USE_BOTH);
    }
}
