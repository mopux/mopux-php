<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * A task group (column) of a project. `tasks` is filled by the board and group focus.
 */
final class TaskGroup implements DataObject
{
    /**
     * @param  list<Task>  $tasks
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?int $projectId = null,
        public readonly ?string $description = null,
        public readonly bool $archived = false,
        public readonly ?int $position = null,
        public readonly array $tasks = [],
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'group');

        return new self(
            id: $payload->int('id'),
            title: $payload->string('title'),
            projectId: $payload->nullableInt('project_id'),
            description: $payload->nullableString('description'),
            archived: $payload->nullableBool('status') ?? false,
            position: $payload->nullableInt('position'),
            tasks: $payload->list('tasks', Task::fromArray(...)),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->projectId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->archived,
            'position' => $this->position,
            'tasks' => array_map(static fn (Task $task): array => $task->toArray(), $this->tasks),
        ];
    }
}
