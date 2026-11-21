<?php

declare(strict_types=1);

namespace Mopux;

/**
 * Package version, sent in the User-Agent header of every request.
 */
final class Version
{
    public const SDK = '0.1.0';

    /**
     * API version targeted by this client (matches `/api/v1`).
     */
    public const API = 'v1';
}
