<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Exceptions\InvalidInputException;

/**
 * Payload of `PATCH tasks/batch`: 1 to 4 tasks updated atomically (all or nothing).
 */
final class TaskBatchUpdate
{
    public const MAX_TASKS = 4;

    /** @var list<TaskBatchItem> */
    public readonly array $items;

    public function __construct(TaskBatchItem ...$items)
    {
        $this->items = array_values($items);
        $this->toArray();
    }

    /**
     * @return array{tasks: list<array{id: int, changes: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        $count = count($this->items);

        if ($count < 1 || $count > self::MAX_TASKS) {
            throw new InvalidInputException('tasks', sprintf('between 1 and %d tasks are required, %d given.', self::MAX_TASKS, $count));
        }

        $ids = array_map(static fn (TaskBatchItem $item): int => $item->id, $this->items);

        if (count($ids) !== count(array_unique($ids))) {
            throw new InvalidInputException('tasks', 'each task can only appear once.');
        }

        $tasks = [];

        foreach ($this->items as $index => $item) {
            $tasks[] = $item->toArray('tasks.'.$index.'.');
        }

        return ['tasks' => $tasks];
    }
}
