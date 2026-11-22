<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * One API personal token of the authenticated user, without its secret.
 */
final class ApiToken implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $current,
        public readonly ?DateTimeImmutable $lastUsedAt = null,
        public readonly ?DateTimeImmutable $expiresAt = null,
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'api token');

        return new self(
            id: $payload->int('id'),
            name: $payload->string('name'),
            current: $payload->nullableBool('current') ?? false,
            lastUsedAt: $payload->nullableDate('last_used_at'),
            expiresAt: $payload->nullableDate('expires_at'),
            createdAt: $payload->nullableDate('created_at'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'current' => $this->current,
            'last_used_at' => Format::dateTime($this->lastUsedAt),
            'expires_at' => Format::dateTime($this->expiresAt),
            'created_at' => Format::dateTime($this->createdAt),
        ];
    }
}
