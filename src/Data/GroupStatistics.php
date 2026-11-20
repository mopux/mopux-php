<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Progress of one task group inside ProjectStatistics.
 */
final class GroupStatistics implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly bool $archived,
        public readonly int $total,
        public readonly int $progress,
        public readonly StatusCounts $counts,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'group');

        return new self(
            id: $payload->int('id'),
            title: $payload->string('title'),
            archived: $payload->nullableBool('status') ?? false,
            total: $payload->int('total'),
            progress: $payload->int('progress'),
            counts: StatusCounts::fromArray($payload->array('counts')),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->archived,
            'total' => $this->total,
            'progress' => $this->progress,
            'counts' => $this->counts->toArray(),
        ];
    }
}
