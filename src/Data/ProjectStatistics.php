<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * Progress and workload of a project (`GET projects/{id}/summary`).
 *
 * `withoutTasks` counts members with no tasks; `multipleAssignees` tells whether
 * some task has more than one assignee.
 */
final class ProjectStatistics implements DataObject
{
    /**
     * @param  list<GroupStatistics>  $groups
     * @param  list<PersonWorkload>  $people
     */
    public function __construct(
        public readonly Reference $project,
        public readonly int $total,
        public readonly int $completed,
        public readonly int $pending,
        public readonly int $progress,
        public readonly array $groups,
        public readonly array $people,
        public readonly UnassignedTasks $unassigned,
        public readonly int $withoutTasks,
        public readonly bool $multipleAssignees,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'summary');

        return new self(
            project: Reference::fromArray($payload->object('project')->all()),
            total: $payload->int('total'),
            completed: $payload->int('completed'),
            pending: $payload->int('pending'),
            progress: $payload->int('progress'),
            groups: $payload->list('groups', GroupStatistics::fromArray(...)),
            people: $payload->list('people', PersonWorkload::fromArray(...)),
            unassigned: UnassignedTasks::fromArray($payload->array('unassigned')),
            withoutTasks: $payload->nullableInt('without_tasks') ?? 0,
            multipleAssignees: $payload->nullableBool('multiple_assignees') ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'project' => $this->project->toArray(),
            'total' => $this->total,
            'completed' => $this->completed,
            'pending' => $this->pending,
            'progress' => $this->progress,
            'groups' => Format::list($this->groups),
            'people' => Format::list($this->people),
            'unassigned' => $this->unassigned->toArray(),
            'without_tasks' => $this->withoutTasks,
            'multiple_assignees' => $this->multipleAssignees,
        ];
    }
}
