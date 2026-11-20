<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * Where a task can be moved (`GET tasks/{id}/move-options`).
 */
final class MoveOptions implements DataObject
{
    /**
     * @param  list<MoveDestination>  $projects
     */
    public function __construct(
        public readonly int $currentProjectId,
        public readonly int $currentGroupId,
        public readonly array $projects,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'move_options');

        return new self(
            currentProjectId: $payload->int('current_project_id'),
            currentGroupId: $payload->int('current_group_id'),
            projects: $payload->list('projects', MoveDestination::fromArray(...)),
        );
    }

    public function toArray(): array
    {
        return [
            'current_project_id' => $this->currentProjectId,
            'current_group_id' => $this->currentGroupId,
            'projects' => Format::list($this->projects),
        ];
    }
}
