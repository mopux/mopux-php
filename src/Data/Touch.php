<?php

declare(strict_types=1);

namespace Mopux\Data;

use DateTimeImmutable;
use Mopux\Enums\TouchStatus;
use Mopux\Support\Format;
use Mopux\Support\Payload;

/**
 * A touch: a short nudge about a task sent to a project member, who can answer it.
 */
final class Touch implements DataObject
{
    public function __construct(
        public readonly int $id,
        public readonly int $senderId,
        public readonly int $recipientId,
        public readonly ?int $taskId,
        public readonly ?string $senderMessage,
        public readonly ?string $recipientMessage,
        public readonly TouchStatus $status,
        public readonly ?UserBrief $sender = null,
        public readonly ?UserBrief $recipient = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'touch');

        return new self(
            id: $payload->int('id'),
            senderId: $payload->int('sender_id'),
            recipientId: $payload->int('recipient_id'),
            taskId: $payload->nullableInt('task_id'),
            senderMessage: $payload->nullableString('sender_message'),
            recipientMessage: $payload->nullableString('recipient_message'),
            status: $payload->enum('status', TouchStatus::class),
            sender: UserBrief::fromNullable($payload->nullableObject('sender')?->all()),
            recipient: UserBrief::fromNullable($payload->nullableObject('recipient')?->all()),
            createdAt: $payload->nullableDate('created_at'),
            updatedAt: $payload->nullableDate('updated_at'),
        );
    }

    public function isAnswered(): bool
    {
        return $this->status === TouchStatus::Answered;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->senderId,
            'recipient_id' => $this->recipientId,
            'task_id' => $this->taskId,
            'sender_message' => $this->senderMessage,
            'recipient_message' => $this->recipientMessage,
            'status' => $this->status->value,
            'sender' => $this->sender?->toArray(),
            'recipient' => $this->recipient?->toArray(),
            'created_at' => Format::dateTime($this->createdAt),
            'updated_at' => Format::dateTime($this->updatedAt),
        ];
    }
}
