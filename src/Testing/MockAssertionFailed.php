<?php

declare(strict_types=1);

namespace Mopux\Testing;

use AssertionError;

/**
 * Thrown by MockClient assertions when PHPUnit is not available.
 */
final class MockAssertionFailed extends AssertionError {}
