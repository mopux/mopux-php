<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Tasks without assignee inside ProjectStatistics.
 */
final class UnassignedTasks implements DataObject
{
    public function __construct(
        public readonly int $total,
        public readonly int $pending,
        public readonly int $completed,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'unassigned');

        return new self(
            total: $payload->nullableInt('total') ?? 0,
            pending: $payload->nullableInt('pending') ?? 0,
            completed: $payload->nullableInt('completed') ?? 0,
        );
    }

    public function toArray(): array
    {
        return ['total' => $this->total, 'pending' => $this->pending, 'completed' => $this->completed];
    }
}
