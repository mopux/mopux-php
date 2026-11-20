<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeInterface;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;

/**
 * Fields to change on a task (`PATCH tasks/{id}` and each item of the batch update).
 *
 * Only given fields are sent. `null` clears description, dates and hours; leave a
 * field out (Missing) to keep it. At least one field is required.
 *
 *     new TaskUpdate(status: TaskStatus::Done, hoursInverted: '01:45');
 *     new TaskUpdate(dateEnd: null, assignees: AssigneeMutation::add([7]));
 */
final class TaskUpdate
{
    /**
     * @param  list<string>|Missing  $tags  Exact set of tags (replaces the current ones).
     */
    public function __construct(
        public readonly string|Missing $title = Missing::Value,
        public readonly string|null|Missing $description = Missing::Value,
        public readonly TaskStatus|Missing $status = Missing::Value,
        public readonly TaskPriority|Missing $priority = Missing::Value,
        public readonly DateTimeInterface|string|null|Missing $dateStart = Missing::Value,
        public readonly DateTimeInterface|string|null|Missing $dateEnd = Missing::Value,
        public readonly string|int|null|Missing $hoursExpected = Missing::Value,
        public readonly string|int|null|Missing $hoursInverted = Missing::Value,
        public readonly array|Missing $tags = Missing::Value,
        public readonly AssigneeMutation|Missing $assignees = Missing::Value,
    ) {
        $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $prefix = ''): array
    {
        $payload = [];

        if (! $this->title instanceof Missing) {
            $payload['title'] = Validate::title($this->title, $prefix.'title');
        }

        $payload += TaskFields::serialize($prefix, $this->description, $this->status, $this->priority, $this->dateStart, $this->dateEnd, $this->hoursExpected, $this->hoursInverted, $this->tags);

        if (! $this->assignees instanceof Missing) {
            $payload['assignees'] = $this->assignees->toArray();
        }

        if ($payload === [] && $prefix === '') {
            throw new InvalidInputException('changes', 'provide at least one field to update.');
        }

        return $payload;
    }
}
