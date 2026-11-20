<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * An invitation to join the team. `accepted` maps the API's boolean `status`
 * and `role` its `rol` field.
 */
final class TeamInvitation implements DataObject
{
    /**
     * @param  array<array-key, mixed>  $log
     */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly bool $accepted,
        public readonly int $role,
        public readonly ?string $hash,
        public readonly array $log,
        public readonly ?int $invitedBy,
        public readonly ?UserBrief $invitedByUser,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'invitation');

        return new self(
            id: $payload->int('id'),
            email: $payload->string('email'),
            accepted: $payload->nullableBool('status') ?? false,
            role: $payload->nullableInt('rol') ?? 0,
            hash: $payload->nullableString('hash'),
            log: $payload->array('log'),
            invitedBy: $payload->nullableInt('invited_by'),
            invitedByUser: UserBrief::fromNullable($payload->nullableObject('invited_by_user')?->all()),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'status' => $this->accepted,
            'rol' => $this->role,
            'hash' => $this->hash,
            'log' => $this->log,
            'invited_by' => $this->invitedBy,
            'invited_by_user' => $this->invitedByUser?->toArray(),
            'created_at' => Format::dateTime($this->createdAt),
            'updated_at' => Format::dateTime($this->updatedAt),
        ];
    }
}
