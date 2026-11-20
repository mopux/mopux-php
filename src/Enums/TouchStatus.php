<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Whether a touch has been answered (`GET touches?status=`).
 */
enum TouchStatus: string
{
    case Pending = 'pending';
    case Answered = 'answered';
}
