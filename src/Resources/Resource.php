<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Mopux\Http\Connector;

/**
 * Base class of the API resources.
 */
abstract class Resource
{
    public function __construct(protected readonly Connector $connector) {}
}
