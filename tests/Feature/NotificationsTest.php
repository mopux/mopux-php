<?php

declare(strict_types=1);

use Mopux\Data\Notification;
use Mopux\Enums\NotificationFilter;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

const NOTIFICATION_ID = '9b2f6a3e-1c1d-4e8f-9f0a-6f1d2c3b4a5e';

it('lists notifications', function (): void {
    $client = new MockClient(['GET notifications' => okFixture('notifications_list')]);

    $page = mopux($client)->notifications()->list(NotificationFilter::Unread, perPage: 2);

    expect($client->lastRecorded()->query)->toBe(['filter' => 'unread', 'page' => '1', 'per_page' => '2'])
        ->and($page->items[0])->toBeInstanceOf(Notification::class)
        ->and($page->items[1]->isRead)->toBeTrue()
        ->and($page->hasMorePages())->toBeTrue();
});

it('accepts the Laravel pagination of API 1.2 for notifications', function (): void {
    $items = apiFixture('notifications_list');
    $client = new MockClient(['GET notifications' => MockResponse::ok([
        'data' => $items,
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => count($items), 'from' => 1, 'to' => count($items)],
    ])]);

    $page = mopux($client)->notifications()->list();

    expect($page->items)->toHaveCount(count($items))
        ->and($page->total)->toBe(count($items))
        ->and($page->hasMorePages())->toBeFalse();
});

it('iterates all notifications', function (): void {
    $client = new MockClient(['GET notifications' => [okFixture('notifications_list'), MockResponse::ok([])]]);

    expect(iterator_to_array(mopux($client)->notifications()->all(perPage: 2), false))->toHaveCount(2);
    $client->assertSentCount(2);
});

it('marks notifications', function (): void {
    $client = new MockClient([
        'PATCH notifications/'.NOTIFICATION_ID.'/read' => MockResponse::ok(['is_read' => true, 'read_at' => '2026-11-20 11:00:00'] + apiFixture('notification')),
        'PATCH notifications/'.NOTIFICATION_ID.'/unread' => okFixture('notification'),
        'PATCH notifications/read-all' => MockResponse::ok(['unread_count' => 0]),
    ]);
    $notifications = mopux($client)->notifications();

    expect($notifications->markRead(NOTIFICATION_ID)->isRead)->toBeTrue()
        ->and($notifications->markUnread(NOTIFICATION_ID)->isRead)->toBeFalse()
        ->and($notifications->markAllRead())->toBe(0);
});

it('reads the unread count and deletes notifications', function (): void {
    $client = new MockClient([
        'GET notifications/badge' => MockResponse::ok(['unread_count' => 4]),
        'DELETE notifications/'.NOTIFICATION_ID => MockResponse::ok(['unread_count' => 3]),
        'DELETE notifications' => MockResponse::ok(['deleted' => 12, 'unread_count' => 0]),
    ]);
    $notifications = mopux($client)->notifications();

    expect($notifications->unreadCount())->toBe(4)
        ->and($notifications->delete(NOTIFICATION_ID))->toBe(3)
        ->and($notifications->deleteAll())->toBe(12);
});

it('rejects ids that are not uuids', function (): void {
    $client = new MockClient;

    expect(fn () => mopux($client)->notifications()->markRead('../tasks/1'))->toThrow(InvalidInputException::class, 'expected a UUID');
    $client->assertNothingSent();
});
