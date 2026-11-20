<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Filter of the project activity log (`GET projects/{id}/activity?filter=`).
 */
enum ActivityFilter: string
{
    case All = 'all';
    case TaskCompleted = 'task_completed';
    case Comments = 'comments';
    case Documents = 'documents';
    case DateStart = 'date_start';
    case DateEnd = 'date_end';
    case TaskDeleted = 'task_deleted';
}
