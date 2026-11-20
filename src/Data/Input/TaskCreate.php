<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeInterface;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;

/**
 * Payload of `POST tasks`. The operation is atomic: on a known error nothing is created.
 *
 *     new TaskCreate(projectId: 12, groupId: 7, title: 'Write docs',
 *         priority: TaskPriority::High, hoursExpected: '2:30', assignees: [3, 5]);
 */
final class TaskCreate
{
    /**
     * @param  list<string>|Missing  $tags  At most 20.
     * @param  list<int>|Missing  $assignees  Initial assignees (project members).
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $groupId,
        public readonly string $title,
        public readonly string|null|Missing $description = Missing::Value,
        public readonly TaskStatus|Missing $status = Missing::Value,
        public readonly TaskPriority|Missing $priority = Missing::Value,
        public readonly DateTimeInterface|string|null|Missing $dateStart = Missing::Value,
        public readonly DateTimeInterface|string|null|Missing $dateEnd = Missing::Value,
        public readonly string|int|null|Missing $hoursExpected = Missing::Value,
        public readonly string|int|null|Missing $hoursInverted = Missing::Value,
        public readonly array|Missing $tags = Missing::Value,
        public readonly array|Missing $assignees = Missing::Value,
    ) {
        $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'project_id' => Validate::id($this->projectId, 'project_id'),
            'group_id' => Validate::id($this->groupId, 'group_id'),
            'title' => Validate::title($this->title),
        ] + TaskFields::serialize('', $this->description, $this->status, $this->priority, $this->dateStart, $this->dateEnd, $this->hoursExpected, $this->hoursInverted, $this->tags);

        if (! $this->assignees instanceof Missing) {
            $payload['assignees'] = Validate::ids($this->assignees, 'assignees');
        }

        return $payload;
    }
}
