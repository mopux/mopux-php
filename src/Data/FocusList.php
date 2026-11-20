<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * The user's personal focus: pinned tasks and the maximum allowed.
 */
final class FocusList implements DataObject
{
    /**
     * @param  list<Task>  $tasks
     */
    public function __construct(
        public readonly array $tasks,
        public readonly int $limit,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'focus');

        return new self(
            tasks: $payload->list('tasks', Task::fromArray(...)),
            limit: $payload->nullableInt('limit') ?? 0,
        );
    }

    public function isFull(): bool
    {
        return $this->limit > 0 && count($this->tasks) >= $this->limit;
    }

    public function contains(int $taskId): bool
    {
        foreach ($this->tasks as $task) {
            if ($task->id === $taskId) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        return ['tasks' => Format::list($this->tasks), 'limit' => $this->limit];
    }
}
