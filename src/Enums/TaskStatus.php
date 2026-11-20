<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Status of a task.
 */
enum TaskStatus: int
{
    case Pending = 0;
    case InProgress = 1;
    case Review = 2;
    case Done = 3;

    /** Read-only: computed by Mopux for overdue tasks, cannot be sent. */
    case Delayed = -1;

    public function isWritable(): bool
    {
        return $this !== self::Delayed;
    }

    public function isOpen(): bool
    {
        return $this !== self::Done;
    }
}
