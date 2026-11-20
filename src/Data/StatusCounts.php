<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Number of tasks per status (`pending`, `doing`, `revision`, `done` and, when present, `delayed`/`total`).
 */
final class StatusCounts implements DataObject
{
    public function __construct(
        public readonly int $pending,
        public readonly int $inProgress,
        public readonly int $review,
        public readonly int $done,
        public readonly ?int $delayed = null,
        public readonly ?int $total = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'counts');

        return new self(
            pending: $payload->nullableInt('pending') ?? 0,
            inProgress: $payload->nullableInt('doing') ?? 0,
            review: $payload->nullableInt('revision') ?? 0,
            done: $payload->nullableInt('done') ?? 0,
            delayed: $payload->nullableInt('delayed'),
            total: $payload->nullableInt('total'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'pending' => $this->pending,
            'doing' => $this->inProgress,
            'revision' => $this->review,
            'done' => $this->done,
            'delayed' => $this->delayed,
            'total' => $this->total,
        ], static fn (?int $value): bool => $value !== null);
    }
}
