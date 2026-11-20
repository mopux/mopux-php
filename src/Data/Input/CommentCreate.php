<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Exceptions\InvalidInputException;

/**
 * Payload of `POST tasks/{id}/comments` (Markdown text, mentions and already
 * uploaded documents). Files to upload with the comment are passed to the
 * resource method, which then sends the request as multipart.
 */
final class CommentCreate
{
    public const MAX_LENGTH = 65535;

    /** @var list<int> */
    public readonly array $mentions;

    /** @var list<int> */
    public readonly array $mediaIds;

    /**
     * @param  list<int>  $mentions  User ids to mention.
     * @param  list<int>  $mediaIds  Documents already uploaded to the task to attach.
     */
    public function __construct(
        public readonly ?string $comment,
        array $mentions = [],
        array $mediaIds = [],
    ) {
        $this->mentions = Validate::ids($mentions, 'mentions');
        $this->mediaIds = Validate::ids($mediaIds, 'media_ids');

        if ($comment !== null && mb_strlen($comment) > self::MAX_LENGTH) {
            throw new InvalidInputException('comment', sprintf('must be at most %d characters.', self::MAX_LENGTH));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = ['comment' => $this->comment];

        if ($this->mentions !== []) {
            $payload['mentions'] = $this->mentions;
        }

        if ($this->mediaIds !== []) {
            $payload['media_ids'] = $this->mediaIds;
        }

        return $payload;
    }
}
