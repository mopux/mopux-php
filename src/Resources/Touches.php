<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Mopux\Data\Input\Validate;
use Mopux\Data\Touch;
use Mopux\Enums\TouchBox;
use Mopux\Enums\TouchStatus;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Support\Payload;

/**
 * Touches received or sent by the user (`/api/v1/touches`). Send one with
 * `tasks()->sendTouch()`.
 */
final class Touches extends Resource
{
    public const MAX_MESSAGE_LENGTH = 2000;

    /**
     * @return list<Touch>
     */
    public function list(TouchBox $box = TouchBox::Received, ?TouchStatus $status = null): array
    {
        $response = $this->connector->get('touches', ['box' => $box, 'status' => $status]);

        return Payload::of(['touches' => $response->array()])->list('touches', Touch::fromArray(...));
    }

    /**
     * Answers a received touch (2 to 2000 characters).
     */
    public function reply(int $touchId, string $message): Touch
    {
        $length = mb_strlen(trim($message));

        if ($length < 2 || mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidInputException('message', sprintf('must be between 2 and %d characters.', self::MAX_MESSAGE_LENGTH));
        }

        $response = $this->connector->post('touches/'.Validate::id($touchId, 'touch_id').'/reply', ['message' => $message]);

        return Touch::fromArray($response->array());
    }
}
