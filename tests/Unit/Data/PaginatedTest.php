<?php

declare(strict_types=1);

use Mopux\Data\Attachment;
use Mopux\Data\Comment;
use Mopux\Data\Notification;
use Mopux\Data\Paginated;
use Mopux\Data\Project;
use Mopux\Data\ProjectActivity;
use Mopux\Data\TeamInvitation;
use Mopux\Data\TeamMember;

it('hydrates laravel paginated lists', function (string $fixture, string $class, int $count, int $lastPage, int $total): void {
    $page = Paginated::fromLaravel(apiFixture($fixture), $class::fromArray(...));

    expect($page->items)->toHaveCount($count)
        ->and($page->items[0])->toBeInstanceOf($class)
        ->and($page->currentPage)->toBe(1)
        ->and($page->lastPage)->toBe($lastPage)
        ->and($page->total)->toBe($total);
})->with([
    'projects' => ['projects_page', Project::class, 1, 3, 3],
    'activity' => ['activity_page', ProjectActivity::class, 2, 4, 8],
    'invitations' => ['team_invitations_page', TeamInvitation::class, 1, 1, 1],
]);

it('hydrates plain lists without metadata', function (string $fixture, string $class): void {
    $page = Paginated::fromList(apiFixture($fixture), $class::fromArray(...), page: 2, perPage: 2);

    expect($page->items)->toHaveCount(2)
        ->and($page->items[1])->toBeInstanceOf($class)
        ->and($page->currentPage)->toBe(2)
        ->and($page->lastPage)->toBeNull()
        ->and($page->total)->toBeNull()
        ->and($page->hasMorePages())->toBeTrue()
        ->and($page->nextPage())->toBe(3);
})->with([
    'comments' => ['comments_list', Comment::class],
    'notifications' => ['notifications_list', Notification::class],
]);

it('hydrates page field lists', function (): void {
    $team = Paginated::fromPageFields(apiFixture('team'), 'members', TeamMember::fromArray(...));
    $attachments = Paginated::fromPageFields(apiFixture('task_attachments'), 'attachments', Attachment::fromArray(...), perPage: 20);

    expect($team->items[0]->name)->toBe('Ada Lovelace')
        ->and($team->hasMorePages())->toBeFalse()
        ->and($attachments->items[0])->toBeInstanceOf(Attachment::class)
        ->and($attachments->lastPage)->toBe(2)
        ->and($attachments->total)->toBe(21)
        ->and($attachments->nextPage())->toBe(2);
});

it('knows when there are more pages', function (Paginated $page, bool $more): void {
    expect($page->hasMorePages())->toBe($more);
})->with([
    'before the last page' => [new Paginated([1], currentPage: 1, lastPage: 2), true],
    'on the last page' => [new Paginated([1], currentPage: 2, lastPage: 2), false],
    'full plain page' => [new Paginated([1, 2], perPage: 2), true],
    'partial plain page' => [new Paginated([1], perPage: 2), false],
    'unknown page size' => [new Paginated([1, 2]), false],
    'empty page' => [new Paginated([], currentPage: 1, lastPage: 5), false],
]);

it('iterates every page lazily', function (): void {
    $requested = [];
    $pages = [
        1 => new Paginated(['a', 'b'], currentPage: 1, lastPage: 3),
        2 => new Paginated(['c', 'd'], currentPage: 2, lastPage: 3),
        3 => new Paginated(['e'], currentPage: 3, lastPage: 3),
    ];

    $generator = Paginated::iterate(function (int $page) use (&$requested, $pages): Paginated {
        $requested[] = $page;

        return $pages[$page];
    });

    expect($generator->current())->toBe('a')
        ->and($requested)->toBe([1]);

    expect(iterator_to_array($generator, false))->toBe(['a', 'b', 'c', 'd', 'e'])
        ->and($requested)->toBe([1, 2, 3]);
});

it('stops plain list iteration on a partial or empty page', function (): void {
    $requested = [];
    $items = Paginated::iterate(function (int $page) use (&$requested): Paginated {
        $requested[] = $page;

        return new Paginated($page < 3 ? [$page * 10, $page * 10 + 1] : [], currentPage: $page, perPage: 2);
    });

    expect(iterator_to_array($items, false))->toBe([10, 11, 20, 21])
        ->and($requested)->toBe([1, 2, 3]);
});

it('stops when the api repeats the same page', function (): void {
    $calls = 0;
    $items = Paginated::iterate(function (int $page) use (&$calls): Paginated {
        $calls++;

        return new Paginated(['x'], currentPage: 1, lastPage: 3);
    }, firstPage: 2);

    expect(iterator_to_array($items, false))->toBe(['x'])
        ->and($calls)->toBe(1);
});

it('maps and counts items', function (): void {
    $page = new Paginated([1, 2, 3]);

    expect($page->map(fn (int $n) => $n * 2))->toBe([2, 4, 6])
        ->and($page->count())->toBe(3)
        ->and($page->isEmpty())->toBeFalse()
        ->and((new Paginated([]))->isEmpty())->toBeTrue();
});
