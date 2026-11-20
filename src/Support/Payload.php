<?php

declare(strict_types=1);

namespace Mopux\Support;

use DateTimeImmutable;
use DateTimeZone;
use Mopux\Exceptions\InvalidPayloadException;

/**
 * Typed reader over a decoded API object, used by the data objects.
 *
 * Unknown keys are ignored so the SDK keeps working when the API adds fields.
 * Required getters throw InvalidPayloadException when a field is missing or has
 * the wrong type; `nullable*` getters return null for missing or null values.
 *
 * @internal
 */
final class Payload
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(private readonly array $data, private readonly string $path = '') {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function of(array $data, string $path = ''): self
    {
        return new self($data, $path);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key) ?? throw $this->invalid($key, 'an integer');
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            is_float($value) && floor($value) === $value => (int) $value,
            default => throw $this->invalid($key, 'an integer', $value),
        };
    }

    public function string(string $key): string
    {
        return $this->nullableString($key) ?? throw $this->invalid($key, 'a string');
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value) || is_float($value) => (string) $value,
            default => throw $this->invalid($key, 'a string', $value),
        };
    }

    public function bool(string $key): bool
    {
        return $this->nullableBool($key) ?? throw $this->invalid($key, 'a boolean');
    }

    public function nullableBool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_bool($value) => $value,
            $value === 0, $value === '0' => false,
            $value === 1, $value === '1' => true,
            default => throw $this->invalid($key, 'a boolean', $value),
        };
    }

    public function float(string $key): float
    {
        return $this->nullableFloat($key) ?? throw $this->invalid($key, 'a number');
    }

    public function nullableFloat(string $key): ?float
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_int($value) || is_float($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => throw $this->invalid($key, 'a number', $value),
        };
    }

    /**
     * Backed enum value; unknown values throw so new API states are noticed.
     *
     * @template E of \BackedEnum
     *
     * @param  class-string<E>  $enum
     * @return E
     */
    public function enum(string $key, string $enum): \BackedEnum
    {
        return $this->nullableEnum($key, $enum) ?? throw $this->invalid($key, 'a '.$enum.' value');
    }

    /**
     * @template E of \BackedEnum
     *
     * @param  class-string<E>  $enum
     * @return E|null
     */
    public function nullableEnum(string $key, string $enum): ?\BackedEnum
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $value = is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value;

        if (! is_int($value) && ! is_string($value)) {
            throw $this->invalid($key, 'a '.$enum.' value', $value);
        }

        return $enum::tryFrom($value) ?? throw $this->invalid($key, 'a '.$enum.' value', $value);
    }

    /**
     * Dates come as `Y-m-d H:i:s` or `Y-m-d` (and ISO 8601 in a few places).
     */
    public function nullableDate(string $key): ?DateTimeImmutable
    {
        $value = $this->nullableString($key);

        if ($value === null || $value === '') {
            return null;
        }

        $timezone = new DateTimeZone(date_default_timezone_get());

        foreach (['!Y-m-d H:i:s', '!Y-m-d'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $timezone);

            if ($date !== false && DateTimeImmutable::getLastErrors() === false) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (\Exception) {
            throw $this->invalid($key, 'a date', $value);
        }
    }

    public function date(string $key): DateTimeImmutable
    {
        return $this->nullableDate($key) ?? throw $this->invalid($key, 'a date');
    }

    /**
     * Nested object as a Payload.
     */
    public function object(string $key): self
    {
        return $this->nullableObject($key) ?? throw $this->invalid($key, 'an object');
    }

    public function nullableObject(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw $this->invalid($key, 'an object', $value);
        }

        return new self($value, $this->field($key));
    }

    /**
     * Raw array value (empty when missing or null).
     *
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->data[$key] ?? [];

        if (! is_array($value)) {
            throw $this->invalid($key, 'an array', $value);
        }

        return $value;
    }

    /**
     * List of nested objects mapped with a factory (empty when missing or null).
     *
     * @template T
     *
     * @param  callable(array<array-key, mixed>): T  $factory
     * @return list<T>
     */
    public function list(string $key, callable $factory): array
    {
        $items = [];

        foreach (array_values($this->array($key)) as $index => $item) {
            if (! is_array($item)) {
                throw $this->invalid($key.'.'.$index, 'an object', $item);
            }

            $items[] = $factory($item);
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    public function intList(string $key): array
    {
        $items = new self(array_values($this->array($key)), $this->field($key));

        return array_map(static fn (int $index): int => $items->int((string) $index), array_keys($items->data));
    }

    /**
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $items = new self(array_values($this->array($key)), $this->field($key));

        return array_map(static fn (int $index): string => $items->string((string) $index), array_keys($items->data));
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    private function field(string $key): string
    {
        return $this->path === '' ? $key : $this->path.'.'.$key;
    }

    private function invalid(string $key, string $expected, mixed $actual = null): InvalidPayloadException
    {
        return new InvalidPayloadException($this->field($key), $expected, $actual);
    }
}
