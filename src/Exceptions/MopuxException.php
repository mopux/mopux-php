<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

use RuntimeException;

/**
 * Base class of every exception thrown by the SDK. Catch it to handle any failure.
 */
class MopuxException extends RuntimeException {}
