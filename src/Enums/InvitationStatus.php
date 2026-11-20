<?php

declare(strict_types=1);

namespace Mopux\Enums;

/**
 * Filter of the team invitation list (`GET team/invitations?status=`).
 */
enum InvitationStatus: string
{
    case All = 'all';
    case Pending = 'pending';
    case Accepted = 'accepted';
}
