<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A project a task can be moved to, with its groups and the warnings of moving there.
 */
final class MoveDestination implements DataObject
{
    /**
     * @param  list<TaskGroup>  $groups
     * @param  list<MoveWarning>  $warnings
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly array $groups,
        public readonly array $warnings,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'project');

        return new self(
            id: $payload->int('id'),
            title: $payload->string('title'),
            groups: $payload->list('groups', TaskGroup::fromArray(...)),
            warnings: $payload->list('warnings', MoveWarning::fromArray(...)),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'groups' => array_map(static fn (TaskGroup $group): array => ['id' => $group->id, 'title' => $group->title, 'position' => $group->position], $this->groups),
            'warnings' => Format::list($this->warnings),
        ];
    }
}
