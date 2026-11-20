<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * A member of the installation's team.
 */
final class TeamMember implements DataObject
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $avatar,
        public readonly bool $isActive,
        public readonly array $roles,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'member');

        return new self(
            id: $payload->int('id'),
            name: $payload->string('name'),
            email: $payload->nullableString('email'),
            avatar: $payload->nullableString('avatar'),
            isActive: $payload->nullableBool('is_active') ?? true,
            roles: $payload->stringList('roles'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'is_active' => $this->isActive,
            'roles' => $this->roles,
        ];
    }
}
