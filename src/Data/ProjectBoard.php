<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Enums\ProjectStatus;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A whole project board in one call: groups in order with their open tasks, and members.
 * Prefer it to fetching tasks one by one.
 */
final class ProjectBoard implements DataObject
{
    /**
     * @param  list<TaskGroup>  $taskGroups
     * @param  list<UserBrief>  $users
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?ProjectStatus $status,
        public readonly array $taskGroups,
        public readonly array $users,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'board');

        return new self(
            id: $payload->int('id'),
            title: $payload->string('title'),
            description: $payload->nullableString('description'),
            status: $payload->nullableEnum('status', ProjectStatus::class),
            taskGroups: $payload->list('task_groups', TaskGroup::fromArray(...)),
            users: $payload->list('users', UserBrief::fromArray(...)),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    /**
     * Every task of the board, in group order.
     *
     * @return list<Task>
     */
    public function tasks(): array
    {
        return array_merge([], ...array_map(static fn (TaskGroup $group): array => $group->tasks, $this->taskGroups));
    }

    public function group(int $id): ?TaskGroup
    {
        foreach ($this->taskGroups as $group) {
            if ($group->id === $id) {
                return $group;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'task_groups' => Format::list($this->taskGroups),
            'users' => Format::list($this->users),
            'updated_at' => Format::dateTime($this->updatedAt),
        ];
    }
}
