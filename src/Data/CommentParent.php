<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * The comment a reply answers, as embedded in the reply.
 */
final class CommentParent implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $comment,
        public readonly ?UserBrief $user,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'parent');

        return new self(
            id: $payload->int('id'),
            comment: $payload->nullableString('comment'),
            user: UserBrief::fromNullable($payload->nullableObject('user')?->all()),
        );
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'comment' => $this->comment, 'user' => $this->user?->toArray()];
    }
}
