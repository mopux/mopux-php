<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Open and completed tasks of one member inside ProjectStatistics.
 */
final class PersonWorkload implements DataObject
{
    public function __construct(
        public readonly UserBrief $user,
        public readonly int $total,
        public readonly int $pending,
        public readonly int $completed,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'person');

        return new self(
            user: UserBrief::fromArray($payload->object('user')->all()),
            total: $payload->int('total'),
            pending: $payload->int('pending'),
            completed: $payload->int('completed'),
        );
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'total' => $this->total,
            'pending' => $this->pending,
            'completed' => $this->completed,
        ];
    }
}
