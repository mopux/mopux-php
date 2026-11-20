<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * HTTP 403: the user lacks permission (role, API access, OAuth scope, billing state).
 */
class AuthorizationException extends ApiException {}
