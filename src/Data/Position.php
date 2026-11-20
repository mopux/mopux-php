<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * New position of a reordered group or task.
 */
final class Position implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $title,
        public readonly ?int $position,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'position');

        return new self(
            id: $payload->int('id'),
            title: $payload->nullableString('title'),
            position: $payload->nullableInt('position') ?? $payload->nullableInt('group_position'),
        );
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'position' => $this->position];
    }
}
