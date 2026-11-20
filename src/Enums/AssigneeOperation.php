<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * How a list of user ids changes the assignees of a task.
 */
enum AssigneeOperation: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Replace = 'replace';
}
