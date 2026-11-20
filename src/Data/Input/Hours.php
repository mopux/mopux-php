<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Exceptions\InvalidInputException;

/**
 * Normalizes durations to the `HH:MM` format the API accepts.
 *
 * Accepts `H:MM`, `HH:MM`, `HH:MM:SS` (as returned by the API) and integer minutes.
 */
final class Hours
{
    public static function normalize(string|int $value, string $field = 'hours'): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidInputException($field, 'minutes cannot be negative.');
            }

            $hours = intdiv($value, 60);
            $minutes = $value % 60;
        } else {
            if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $parts) !== 1) {
                throw new InvalidInputException($field, sprintf('expected HH:MM, "%s" given.', $value));
            }

            $hours = (int) $parts[1];
            $minutes = (int) $parts[2];
        }

        if ($minutes > 59 || $hours > 99) {
            throw new InvalidInputException($field, 'expected at most 99:59.');
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
