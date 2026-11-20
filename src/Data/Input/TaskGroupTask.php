<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeInterface;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;

/**
 * A task created together with its group (see TaskGroupCreate).
 */
final class TaskGroupTask
{
    /**
     * @param  list<string>|Missing  $tags
     * @param  list<int>|Missing  $assignees  Initial assignees (project members).
     */
    public function __construct(
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
    public function toArray(string $prefix = 'tasks.0.'): array
    {
        $payload = ['title' => Validate::title($this->title, $prefix.'title')]
            + TaskFields::serialize($prefix, $this->description, $this->status, $this->priority, $this->dateStart, $this->dateEnd, $this->hoursExpected, $this->hoursInverted, $this->tags);

        if (! $this->assignees instanceof Missing) {
            $payload['assignees'] = Validate::ids($this->assignees, $prefix.'assignees');
        }

        return $payload;
    }
}
