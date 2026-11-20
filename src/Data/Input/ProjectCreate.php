<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use DateTimeInterface;
use Mopux\Exceptions\InvalidInputException;

/**
 * Payload of `POST projects`.
 */
final class ProjectCreate
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly DateTimeInterface|string|null $dateStarts = null,
        public readonly DateTimeInterface|string|null $dateEnds = null,
    ) {
        $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['title' => Validate::title($this->title)];

        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        if ($this->dateStarts !== null) {
            $payload['date_starts'] = Validate::date($this->dateStarts, 'date_starts');
        }

        if ($this->dateEnds !== null) {
            $payload['date_ends'] = Validate::date($this->dateEnds, 'date_ends');
        }

        if (isset($payload['date_starts'], $payload['date_ends']) && $payload['date_ends'] < $payload['date_starts']) {
            throw new InvalidInputException('date_ends', 'cannot be before date_starts.');
        }

        return $payload;
    }
}
