<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test configuration
|--------------------------------------------------------------------------
*/

/**
 * Decoded JSON fixture from tests/fixtures.
 *
 * @return array<array-key, mixed>
 */
function apiFixture(string $name): array
{
    $decoded = json_decode((string) file_get_contents(__DIR__.'/fixtures/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);

    return is_array($decoded) ? $decoded : throw new LogicException("Fixture {$name} is not an object.");
}
