<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Status of a project.
 */
enum ProjectStatus: int
{
    case InProgress = 0;
    case Done = 1;
    case Archived = 2;
    case Delayed = -1;
}
