<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * A user mentioned in a comment.
 */
final class Mention implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?UserBrief $user,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'mention');

        return new self(
            id: $payload->int('id'),
            userId: $payload->int('user_id'),
            user: UserBrief::fromNullable($payload->nullableObject('user')?->all()),
        );
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'user_id' => $this->userId, 'user' => $this->user?->toArray()];
    }
}
