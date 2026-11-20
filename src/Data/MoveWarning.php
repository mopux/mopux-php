<?php

declare(strict_types=1);

namespace Mopux\Data;

use Mopux\Support\Payload;

/**
 * Warning about moving a task to another project, e.g. `task_user_not_in_project`
 * when an assignee is not a member of the destination project.
 */
final class MoveWarning implements DataObject
{
    public function __construct(
        public readonly string $code,
        public readonly ?int $userId,
        public readonly string $message,
    ) {}

    public static function fromArray(array $data): static
    {
        $payload = Payload::of($data, 'warning');

        return new self(
            code: $payload->string('code'),
            userId: $payload->nullableInt('user_id'),
            message: $payload->nullableString('message') ?? '',
        );
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'user_id' => $this->userId, 'message' => $this->message];
    }
}
