<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * A write (POST, PATCH, PUT, DELETE) failed with a 5xx status: the outcome is uncertain.
 *
 * The API does not guarantee that nothing was written. Refresh the state before
 * deciding what to do, and never retry a creation automatically: task creation is
 * not idempotent.
 */
class UncertainWriteException extends ServerException {}
