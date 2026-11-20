<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Enums\ProjectStatus;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A project. Represents the list item (summary), the detail and the creation result;
 * fields a response does not include are null.
 */
final class Project implements DataObject
{
    /**
     * @param  list<UserBrief>|null  $users  Members, only in the detail.
     * @param  array<array-key, mixed>|null  $configuration
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?ProjectStatus $status = null,
        public readonly ?DateTimeImmutable $dateStarts = null,
        public readonly ?DateTimeImmutable $dateEnds = null,
        public readonly ?DateTimeImmutable $dateFinished = null,
        public readonly ?UserBrief $owner = null,
        public readonly ?int $ownerId = null,
        public readonly ?array $users = null,
        public readonly ?string $slug = null,
        public readonly ?string $type = null,
        public readonly ?array $configuration = null,
        public readonly ?int $usersCount = null,
        public readonly ?int $tasksCount = null,
        public readonly ?int $doneCount = null,
        public readonly ?int $taskGroupsCount = null,
        public readonly ?int $copiedFromGroupId = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'project');
        $owner = UserBrief::fromNullable($payload->nullableObject('owner')?->all());

        return new self(
            id: $payload->int('id'),
            title: $payload->string('title'),
            description: $payload->nullableString('description'),
            status: $payload->nullableEnum('status', ProjectStatus::class),
            dateStarts: $payload->nullableDate('date_starts'),
            dateEnds: $payload->nullableDate('date_ends'),
            dateFinished: $payload->nullableDate('date_finished'),
            owner: $owner,
            ownerId: $payload->nullableInt('owner_id') ?? $owner?->id,
            users: $payload->has('users') ? $payload->list('users', UserBrief::fromArray(...)) : null,
            slug: $payload->nullableString('slug'),
            type: $payload->nullableString('type'),
            configuration: $payload->has('configuration') && is_array($data['configuration']) ? $data['configuration'] : null,
            usersCount: $payload->nullableInt('users_count'),
            tasksCount: $payload->nullableInt('tasks_count'),
            doneCount: $payload->nullableInt('done_count'),
            taskGroupsCount: $payload->nullableInt('task_groups_count'),
            copiedFromGroupId: $payload->nullableInt('copied_from_group_id'),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    /**
     * Completion percentage from the task counters (null when unknown).
     */
    public function progress(): ?int
    {
        if ($this->tasksCount === null || $this->doneCount === null) {
            return null;
        }

        return $this->tasksCount === 0 ? 0 : (int) round($this->doneCount / $this->tasksCount * 100);
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'date_starts' => Format::dateTime($this->dateStarts),
            'date_ends' => Format::dateTime($this->dateEnds),
            'date_finished' => Format::dateTime($this->dateFinished),
            'owner' => $this->owner?->toArray(),
            'owner_id' => $this->ownerId,
            'users' => $this->users === null ? null : Format::list($this->users),
            'slug' => $this->slug,
            'type' => $this->type,
            'configuration' => $this->configuration,
            'users_count' => $this->usersCount,
            'tasks_count' => $this->tasksCount,
            'done_count' => $this->doneCount,
            'task_groups_count' => $this->taskGroupsCount,
            'copied_from_group_id' => $this->copiedFromGroupId,
            'created_at' => Format::dateTime($this->createdAt),
            'updated_at' => Format::dateTime($this->updatedAt),
        ], static fn (mixed $value, string $key): bool => $value !== null || in_array($key, ['description', 'date_starts', 'date_ends'], true), ARRAY_FILTER_USE_BOTH);
    }
}
