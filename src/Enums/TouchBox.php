<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Touches received by the user or sent by them (`GET touches?box=`).
 */
enum TouchBox: string
{
    case Received = 'received';
    case Sent = 'sent';
}
