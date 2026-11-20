<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Priority of a task.
 */
enum TaskPriority: int
{
    case Low = 0;
    case Normal = 1;
    case High = 2;
    case Maximum = 9;
}
