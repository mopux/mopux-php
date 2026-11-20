<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * Result of moving a task: the task in its new place and the warnings
 * (e.g. assignees that are not members of the destination project).
 */
final class MovedTask implements DataObject
{
    /**
     * @param  list<MoveWarning>  $warnings
     */
    public function __construct(
        public readonly Task $task,
        public readonly array $warnings,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            task: Task::fromArray($data),
            warnings: Payload::of($data)->list('warnings', MoveWarning::fromArray(...)),
        );
    }

    public function toArray(): array
    {
        return $this->task->toArray() + ['warnings' => Format::list($this->warnings)];
    }
}
