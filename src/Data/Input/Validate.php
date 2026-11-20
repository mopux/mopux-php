<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeImmutable;
use DateTimeInterface;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;

/**
 * Client-side checks mirroring the API validation rules.
 *
 * @internal
 */
final class Validate
{
    public const MAX_TITLE = 255;

    public const MAX_TAGS = 20;

    public const MAX_TAG_LENGTH = 255;

    public static function title(string $title, string $field = 'title'): string
    {
        if (trim($title) === '') {
            throw new InvalidInputException($field, 'cannot be empty.');
        }

        if (mb_strlen($title) > self::MAX_TITLE) {
            throw new InvalidInputException($field, sprintf('must be at most %d characters.', self::MAX_TITLE));
        }

        return $title;
    }

    public static function id(int $id, string $field): int
    {
        if ($id < 1) {
            throw new InvalidInputException($field, 'must be a positive integer.');
        }

        return $id;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<int>
     */
    public static function ids(array $ids, string $field): array
    {
        $normalized = [];

        foreach (array_values($ids) as $index => $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidInputException($field.'.'.$index, 'must be a positive integer.');
            }

            $normalized[] = $id;
        }

        if (count($normalized) !== count(array_unique($normalized))) {
            throw new InvalidInputException($field, 'contains duplicated ids.');
        }

        return $normalized;
    }

    /**
     * @param  array<array-key, mixed>  $tags
     * @return list<string>
     */
    public static function tags(array $tags, string $field = 'tags'): array
    {
        if (count($tags) > self::MAX_TAGS) {
            throw new InvalidInputException($field, sprintf('at most %d tags are allowed.', self::MAX_TAGS));
        }

        $normalized = [];

        foreach (array_values($tags) as $index => $tag) {
            if (! is_string($tag) || trim($tag) === '' || mb_strlen($tag) > self::MAX_TAG_LENGTH) {
                throw new InvalidInputException($field.'.'.$index, sprintf('must be a non-empty string of at most %d characters.', self::MAX_TAG_LENGTH));
            }

            $normalized[] = $tag;
        }

        return $normalized;
    }

    public static function date(DateTimeInterface|string $date, string $field): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidInputException($field, sprintf('expected a Y-m-d date, "%s" given.', $date));
        }

        return $date;
    }

    public static function writableStatus(TaskStatus $status, string $field = 'status'): int
    {
        if (! $status->isWritable()) {
            throw new InvalidInputException($field, 'the delayed status (-1) is read-only.');
        }

        return $status->value;
    }
}
