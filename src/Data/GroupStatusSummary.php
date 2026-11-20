<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Number of tasks per status in a task group (`GET .../task-groups/{id}/status-summary`).
 */
final class GroupStatusSummary implements DataObject
{
    public function __construct(
        public readonly TaskGroup $group,
        public readonly StatusCounts $counts,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data);

        return new self(
            group: TaskGroup::fromArray($payload->object('group')->all()),
            counts: StatusCounts::fromArray($payload->object('summary')->all()),
        );
    }

    public function toArray(): array
    {
        return [
            'group' => ['id' => $this->group->id, 'project_id' => $this->group->projectId, 'title' => $this->group->title],
            'summary' => $this->counts->toArray(),
        ];
    }
}
