<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A task comment or reply.
 */
final class Comment implements DataObject
{
    /**
     * @param  list<Mention>  $mentions
     * @param  list<Attachment>  $medias
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $parentId,
        public readonly int $taskId,
        public readonly ?int $projectId,
        public readonly string $comment,
        public readonly ?UserBrief $user,
        public readonly ?CommentParent $parent = null,
        public readonly array $mentions = [],
        public readonly array $medias = [],
        public readonly ?int $repliesCount = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'comment');
        $parent = $payload->nullableObject('parent');

        return new self(
            id: $payload->int('id'),
            parentId: $payload->nullableInt('parent_id'),
            taskId: $payload->int('task_id'),
            projectId: $payload->nullableInt('project_id'),
            comment: $payload->nullableString('comment') ?? '',
            user: UserBrief::fromNullable($payload->nullableObject('user')?->all()),
            parent: $parent === null ? null : CommentParent::fromArray($parent->all()),
            mentions: $payload->list('mentions', Mention::fromArray(...)),
            medias: $payload->list('medias', Attachment::fromArray(...)),
            repliesCount: $payload->nullableInt('replies_count'),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
            'comment' => $this->comment,
            'user' => $this->user?->toArray(),
            'parent' => $this->parent?->toArray(),
            'mentions' => Format::list($this->mentions),
            'medias' => Format::list($this->medias),
            'replies_count' => $this->repliesCount,
            'created_at' => Format::dateTime($this->createdAt),
            'updated_at' => Format::dateTime($this->updatedAt),
        ];
    }
}
