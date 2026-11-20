<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeInterface;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;

/**
 * Serializes the optional task fields shared by create, update, batch and group creation.
 *
 * @internal
 */
final class TaskFields
{
    /**
     * @param  list<string>|Missing  $tags
     * @return array<string, mixed>
     */
    public static function serialize(
        string $prefix,
        string|null|Missing $description,
        TaskStatus|Missing $status,
        TaskPriority|Missing $priority,
        DateTimeInterface|string|null|Missing $dateStart,
        DateTimeInterface|string|null|Missing $dateEnd,
        string|int|null|Missing $hoursExpected,
        string|int|null|Missing $hoursInverted,
        array|Missing $tags,
    ): array {
        $fields = [];

        if (! $description instanceof Missing) {
            $fields['description'] = $description;
        }

        if (! $status instanceof Missing) {
            $fields['status'] = Validate::writableStatus($status, $prefix.'status');
        }

        if (! $priority instanceof Missing) {
            $fields['priority'] = $priority->value;
        }

        foreach (['date_start' => $dateStart, 'date_end' => $dateEnd] as $key => $date) {
            if (! $date instanceof Missing) {
                $fields[$key] = $date === null ? null : Validate::date($date, $prefix.$key);
            }
        }

        foreach (['hours_expected' => $hoursExpected, 'hours_inverted' => $hoursInverted] as $key => $hours) {
            if (! $hours instanceof Missing) {
                $fields[$key] = $hours === null ? null : Hours::normalize($hours, $prefix.$key);
            }
        }

        if (! $tags instanceof Missing) {
            $fields['tags'] = Validate::tags($tags, $prefix.'tags');
        }

        return $fields;
    }
}
