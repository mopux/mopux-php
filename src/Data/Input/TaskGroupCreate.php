<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Exceptions\InvalidInputException;

/**
 * Payload of `POST projects/{id}/task-groups`: a group, optionally with up to 4
 * tasks created atomically.
 */
final class TaskGroupCreate
{
    public const MAX_TASKS = 4;

    /** @var list<TaskGroupTask> */
    public readonly array $tasks;

    public function __construct(public readonly string $title, TaskGroupTask ...$tasks)
    {
        $this->tasks = array_values($tasks);
        $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['title' => Validate::title($this->title)];

        if (count($this->tasks) > self::MAX_TASKS) {
            throw new InvalidInputException('tasks', sprintf('at most %d tasks can be created with a group.', self::MAX_TASKS));
        }

        if ($this->tasks !== []) {
            $payload['tasks'] = [];

            foreach ($this->tasks as $index => $task) {
                $payload['tasks'][] = $task->toArray('tasks.'.$index.'.');
            }
        }

        return $payload;
    }
}
