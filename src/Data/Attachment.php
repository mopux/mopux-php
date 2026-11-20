<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * A document attached to a task, directly or through comments.
 *
 * `url` is a signed download link valid for a short time (15 minutes for task
 * attachments); request the list again for a fresh one. `size` (bytes),
 * `commentIds` and `direct` are only present in task attachment responses.
 */
final class Attachment implements DataObject
{
    /**
     * @param  list<int>  $commentIds  Comments that include the document.
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $title,
        public readonly ?string $type,
        public readonly ?string $mimetype,
        public readonly ?string $url,
        public readonly ?int $size = null,
        public readonly array $commentIds = [],
        public readonly ?bool $direct = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'attachment');

        return new self(
            id: $payload->int('id'),
            title: $payload->nullableString('title'),
            type: $payload->nullableString('type'),
            mimetype: $payload->nullableString('mimetype'),
            url: $payload->nullableString('url'),
            size: $payload->nullableInt('size'),
            commentIds: $payload->intList('comment_ids'),
            direct: $payload->nullableBool('direct'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'mimetype' => $this->mimetype,
            'size' => $this->size,
            'url' => $this->url,
            'comment_ids' => $this->commentIds,
            'direct' => $this->direct,
        ], static fn (mixed $value, string $key): bool => $value !== null || in_array($key, ['title', 'type', 'mimetype', 'url'], true), ARRAY_FILTER_USE_BOTH);
    }
}
