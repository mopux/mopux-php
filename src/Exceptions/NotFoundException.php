<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * HTTP 404: the resource or the tenant does not exist, or is not visible to the user.
 */
class NotFoundException extends ApiException {}
