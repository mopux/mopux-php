<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * HTTP 401: the token is missing, invalid, expired or revoked.
 */
class AuthenticationException extends ApiException {}
