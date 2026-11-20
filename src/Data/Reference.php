<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * A lightweight `{id, title}` reference to a project, group, task or document.
 * Either member may be null (e.g. a deleted task in the activity log keeps its title).
 */
final class Reference implements DataObject
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $title,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data);

        return new self($payload->nullableInt('id'), $payload->nullableString('title'));
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public static function fromNullable(?array $data): ?self
    {
        if ($data === null || (($data['id'] ?? null) === null && ($data['title'] ?? null) === null)) {
            return null;
        }

        return self::fromArray($data);
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title];
    }
}
