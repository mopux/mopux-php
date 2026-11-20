<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Generator;
use Mopux\Data\Notification;
use Mopux\Data\Paginated;
use Mopux\Enums\NotificationFilter;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Support\Payload;

/**
 * Notifications of the authenticated user (`/api/v1/notifications`). Ids are UUIDs.
 */
final class Notifications extends Resource
{
    /**
     * Notifications, newest first.
     *
     * The API returns each page without pagination metadata, so `total` and
     * `lastPage` are null and there are more pages while a page comes back full.
     *
     * @return Paginated<Notification>
     */
    public function list(?NotificationFilter $filter = null, int $page = 1, int $perPage = 25): Paginated
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidInputException('per_page', 'must be between 1 and 100.');
        }

        $response = $this->connector->get('notifications', ['filter' => $filter, 'page' => $page, 'per_page' => $perPage]);

        return Paginated::fromList($response->array(), Notification::fromArray(...), $page, $perPage);
    }

    /**
     * @return Generator<int, Notification>
     */
    public function all(?NotificationFilter $filter = null, int $perPage = 100): Generator
    {
        return Paginated::iterate(fn (int $page): Paginated => $this->list($filter, $page, $perPage));
    }

    public function markRead(string $notificationId): Notification
    {
        return Notification::fromArray($this->connector->patch(self::path($notificationId, 'read'))->array());
    }

    public function markUnread(string $notificationId): Notification
    {
        return Notification::fromArray($this->connector->patch(self::path($notificationId, 'unread'))->array());
    }

    /**
     * Marks every notification as read and returns the remaining unread count.
     */
    public function markAllRead(): int
    {
        return Payload::of($this->connector->patch('notifications/read-all')->array())->int('unread_count');
    }

    /**
     * Number of unread notifications.
     */
    public function unreadCount(): int
    {
        return Payload::of($this->connector->get('notifications/badge')->array())->int('unread_count');
    }

    /**
     * Deletes one notification and returns the remaining unread count.
     */
    public function delete(string $notificationId): int
    {
        return Payload::of($this->connector->delete(self::path($notificationId))->array())->int('unread_count');
    }

    /**
     * Deletes every notification of the user and returns how many were deleted.
     */
    public function deleteAll(): int
    {
        return Payload::of($this->connector->delete('notifications')->array())->int('deleted');
    }

    private static function path(string $notificationId, string $suffix = ''): string
    {
        $notificationId = trim($notificationId);

        if (preg_match('/^[0-9a-fA-F-]{1,64}$/', $notificationId) !== 1) {
            throw new InvalidInputException('notification_id', 'expected a UUID.');
        }

        return 'notifications/'.$notificationId.($suffix === '' ? '' : '/'.$suffix);
    }
}
