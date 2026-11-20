<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * An entry of a project's activity log.
 *
 * `changes` holds the sanitized event data (title, old_status/status, hours,
 * dates, assigned_ids...); it is empty when the event has no changes.
 */
final class ProjectActivity implements DataObject
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?string $description,
        public readonly ?UserBrief $actor,
        public readonly ?UserBrief $affectedUser,
        public readonly ?Reference $project,
        public readonly ?Reference $task,
        public readonly ?Reference $group,
        public readonly ?int $commentId,
        public readonly ?Reference $media,
        public readonly array $changes,
        public readonly ?DateTimeImmutable $occurredAt,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'activity');
        $changes = [];

        foreach ($payload->array('changes') as $key => $value) {
            $changes[(string) $key] = $value;
        }

        return new self(
            id: $payload->int('id'),
            type: $payload->string('type'),
            description: $payload->nullableString('description'),
            actor: UserBrief::fromNullable($payload->nullableObject('actor')?->all()),
            affectedUser: UserBrief::fromNullable($payload->nullableObject('affected_user')?->all()),
            project: Reference::fromNullable($payload->nullableObject('project')?->all()),
            task: Reference::fromNullable($payload->nullableObject('task')?->all()),
            group: Reference::fromNullable($payload->nullableObject('group')?->all()),
            commentId: $payload->nullableInt('comment_id'),
            media: Reference::fromNullable($payload->nullableObject('media')?->all()),
            changes: $changes,
            occurredAt: $payload->nullableDate('occurred_at'),
        );
    }

    public function change(string $key): mixed
    {
        return $this->changes[$key] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'actor' => $this->actor?->toArray(),
            'affected_user' => $this->affectedUser?->toArray(),
            'project' => $this->project?->toArray(),
            'task' => $this->task?->toArray(),
            'group' => $this->group?->toArray(),
            'comment_id' => $this->commentId,
            'media' => $this->media?->toArray(),
            'changes' => $this->changes,
            'occurred_at' => Format::dateTime($this->occurredAt),
        ];
    }
}
