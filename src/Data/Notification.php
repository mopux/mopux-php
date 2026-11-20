<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A notification of the authenticated user. `id` is a UUID.
 */
final class Notification implements DataObject
{
    /**
     * @param  array<array-key, mixed>  $data  Raw notification data.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $title,
        public readonly ?string $message,
        public readonly ?string $route,
        public readonly mixed $karma,
        public readonly array $data,
        public readonly bool $isRead,
        public readonly ?DateTimeImmutable $readAt = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'notification');

        return new self(
            id: $payload->string('id'),
            type: $payload->string('type'),
            title: $payload->nullableString('title'),
            message: $payload->nullableString('message'),
            route: $payload->nullableString('route'),
            karma: $data['karma'] ?? null,
            data: $payload->array('data'),
            isRead: $payload->nullableBool('is_read') ?? $payload->has('read_at') && $data['read_at'] !== null,
            readAt: $payload->nullableDate('read_at'),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'route' => $this->route,
            'karma' => $this->karma,
            'data' => $this->data,
            'is_read' => $this->isRead,
            'read_at' => Format::dateTime($this->readAt),
            'created_at' => Format::dateTime($this->createdAt),
            'updated_at' => Format::dateTime($this->updatedAt),
        ];
    }
}
