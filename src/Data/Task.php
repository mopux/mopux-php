<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A task. The same object represents the three shapes returned by the API:
 *
 * - detail (show, create, update...): includes `tags` and `createdAt`.
 * - summary (my tasks, focus): no tags.
 * - board (board, group focus): includes positions, no project/group reference.
 *
 * Fields a shape does not include are null. Hours use `HH:MM:SS`.
 */
final class Task implements DataObject
{
    /**
     * @param  list<UserBrief>  $assignees
     * @param  list<string>|null  $tags  Null when the response does not include tags.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly TaskStatus $status,
        public readonly TaskPriority $priority,
        public readonly ?DateTimeImmutable $dateStart = null,
        public readonly ?DateTimeImmutable $dateEnd = null,
        public readonly ?string $hoursExpected = null,
        public readonly ?string $hoursInverted = null,
        public readonly ?int $assignedTo = null,
        public readonly ?Reference $project = null,
        public readonly ?Reference $group = null,
        public readonly array $assignees = [],
        public readonly ?array $tags = null,
        public readonly ?int $commentsCount = null,
        public readonly ?int $mediasCount = null,
        public readonly ?int $projectPosition = null,
        public readonly ?int $groupPosition = null,
        public readonly ?int $statusPosition = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'task');

        return new self(
            id: $payload->int('id'),
            title: $payload->string('title'),
            description: $payload->nullableString('description'),
            status: $payload->enum('status', TaskStatus::class),
            priority: $payload->nullableEnum('priority', TaskPriority::class) ?? TaskPriority::Low,
            dateStart: $payload->nullableDate('date_start'),
            dateEnd: $payload->nullableDate('date_end'),
            hoursExpected: $payload->nullableString('hours_expected'),
            hoursInverted: $payload->nullableString('hours_inverted'),
            assignedTo: $payload->nullableInt('assigned_to'),
            project: Reference::fromNullable($payload->nullableObject('project')?->all()),
            group: Reference::fromNullable($payload->nullableObject('group')?->all()),
            assignees: $payload->list('assignees', UserBrief::fromArray(...)),
            tags: $payload->has('tags') ? $payload->stringList('tags') : null,
            commentsCount: $payload->nullableInt('comments_count'),
            mediasCount: $payload->nullableInt('medias_count'),
            projectPosition: $payload->nullableInt('project_position'),
            groupPosition: $payload->nullableInt('group_position'),
            statusPosition: $payload->nullableInt('status_position'),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    public function isDone(): bool
    {
        return $this->status === TaskStatus::Done;
    }

    /**
     * @return list<int>
     */
    public function assigneeIds(): array
    {
        return array_map(static fn (UserBrief $user): int => $user->id, $this->assignees);
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'date_start' => Format::date($this->dateStart),
            'date_end' => Format::date($this->dateEnd),
            'hours_expected' => $this->hoursExpected,
            'hours_inverted' => $this->hoursInverted,
            'assigned_to' => $this->assignedTo,
            'project' => $this->project?->toArray(),
            'group' => $this->group?->toArray(),
            'assignees' => Format::list($this->assignees),
            'tags' => $this->tags,
            'comments_count' => $this->commentsCount,
            'medias_count' => $this->mediasCount,
            'project_position' => $this->projectPosition,
            'group_position' => $this->groupPosition,
            'status_position' => $this->statusPosition,
            'created_at' => Format::dateTime($this->createdAt),
            'updated_at' => Format::dateTime($this->updatedAt),
        ], static fn (mixed $value, string $key): bool => $value !== null || in_array($key, self::NULLABLE_KEYS, true), ARRAY_FILTER_USE_BOTH);
    }

    /** Keys always serialized, even when null. */
    private const NULLABLE_KEYS = ['description', 'date_start', 'date_end', 'hours_expected', 'hours_inverted', 'assigned_to'];
}
