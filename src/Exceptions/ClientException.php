<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

/**
 * HTTP Any other 4xx status (400, 405, 409, 413...).
 */
class ClientException extends ApiException {}
