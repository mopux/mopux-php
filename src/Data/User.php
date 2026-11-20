<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Support\Payload;

/**
 * The authenticated user (`GET auth/user`). The login response only includes
 * id, name and email; the other fields are null there.
 */
final class User implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
        public readonly ?DateTimeImmutable $lastLoginAt = null,
        public readonly ?string $lastLoginIp = null,
        public readonly ?int $mediaId = null,
        public readonly ?string $avatar = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'user');

        return new self(
            id: $payload->int('id'),
            name: $payload->string('name'),
            email: $payload->string('email'),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
            lastLoginAt: $payload->nullableDate('last_login_at'),
            lastLoginIp: $payload->nullableString('last_login_ip'),
            mediaId: $payload->nullableInt('media_id'),
            avatar: $payload->nullableString('avatar'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'last_login_ip' => $this->lastLoginIp,
            'media_id' => $this->mediaId,
            'avatar' => $this->avatar,
        ];
    }
}
