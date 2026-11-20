<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Filter of the notification list (`GET notifications?filter=`).
 */
enum NotificationFilter: string
{
    case All = 'all';
    case Read = 'read';
    case Unread = 'unread';
}
