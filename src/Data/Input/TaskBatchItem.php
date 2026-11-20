<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Exceptions\InvalidInputException;

/**
 * One task of a batch update: its id, the field changes and, optionally, a move
 * to another group (`taskGroupId`) or project (`destinationProjectId` + `taskGroupId`).
 */
final class TaskBatchItem
{
    public function __construct(
        public readonly int $id,
        public readonly ?TaskUpdate $changes = null,
        public readonly ?int $taskGroupId = null,
        public readonly ?int $destinationProjectId = null,
    ) {
        $this->toArray();
    }

    /**
     * @return array{id: int, changes: array<string, mixed>}
     */
    public function toArray(string $prefix = 'tasks.0.'): array
    {
        Validate::id($this->id, $prefix.'id');
        $changes = $this->changes?->toArray($prefix.'changes.') ?? [];

        if ($this->destinationProjectId !== null) {
            if ($this->taskGroupId === null) {
                throw new InvalidInputException($prefix.'changes.task_group_id', 'is required when moving to another project.');
            }

            $changes['destination_project_id'] = Validate::id($this->destinationProjectId, $prefix.'changes.destination_project_id');
        }

        if ($this->taskGroupId !== null) {
            $changes['task_group_id'] = Validate::id($this->taskGroupId, $prefix.'changes.task_group_id');
        }

        if ($changes === []) {
            throw new InvalidInputException($prefix.'changes', 'provide at least one field to update.');
        }

        return ['id' => $this->id, 'changes' => $changes];
    }
}
