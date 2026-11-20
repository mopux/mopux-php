<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeInterface;
use Mopux\Enums\ProjectStatus;
use Mopux\Exceptions\InvalidInputException;

/**
 * Payload of `PATCH projects/{id}`. Only given fields are sent; `null` clears the
 * description and dates. The notify flags are the authenticated user's preferences.
 */
final class ProjectUpdate
{
    public function __construct(
        public readonly string|Missing $title = Missing::Value,
        public readonly string|null|Missing $description = Missing::Value,
        public readonly DateTimeInterface|string|null|Missing $dateStarts = Missing::Value,
        public readonly DateTimeInterface|string|null|Missing $dateEnds = Missing::Value,
        public readonly ProjectStatus|Missing $status = Missing::Value,
        public readonly bool|Missing $notifyComments = Missing::Value,
        public readonly bool|Missing $notifyTasks = Missing::Value,
        public readonly bool|Missing $notifyDocuments = Missing::Value,
    ) {
        $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];

        if (! $this->title instanceof Missing) {
            $payload['title'] = Validate::title($this->title);
        }

        if (! $this->description instanceof Missing) {
            $payload['description'] = $this->description;
        }

        foreach (['date_starts' => $this->dateStarts, 'date_ends' => $this->dateEnds] as $key => $date) {
            if (! $date instanceof Missing) {
                $payload[$key] = $date === null ? null : Validate::date($date, $key);
            }
        }

        if (! $this->status instanceof Missing) {
            $payload['status'] = $this->status->value;
        }

        foreach (['notify_comments' => $this->notifyComments, 'notify_tasks' => $this->notifyTasks, 'notify_documents' => $this->notifyDocuments] as $key => $flag) {
            if (! $flag instanceof Missing) {
                $payload[$key] = $flag;
            }
        }

        if ($payload === []) {
            throw new InvalidInputException('project', 'provide at least one field to update.');
        }

        return $payload;
    }
}
