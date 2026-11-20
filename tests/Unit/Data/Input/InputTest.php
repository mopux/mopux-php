<?php

declare(strict_types=1);

use Mopux\Data\Input\AssigneeMutation;
use Mopux\Data\Input\CommentCreate;
use Mopux\Data\Input\Hours;
use Mopux\Data\Input\ProjectCreate;
use Mopux\Data\Input\ProjectUpdate;
use Mopux\Data\Input\TaskBatchItem;
use Mopux\Data\Input\TaskBatchUpdate;
use Mopux\Data\Input\TaskCreate;
use Mopux\Data\Input\TaskGroupCreate;
use Mopux\Data\Input\TaskGroupTask;
use Mopux\Data\Input\TaskUpdate;
use Mopux\Enums\ProjectStatus;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;

it('serializes a full task creation', function (): void {
    $input = new TaskCreate(
        projectId: 12,
        groupId: 7,
        title: 'Write docs',
        description: 'Some **markdown**',
        status: TaskStatus::InProgress,
        priority: TaskPriority::Maximum,
        dateStart: new DateTimeImmutable('2026-11-20 15:00:00'),
        dateEnd: '2026-11-30',
        hoursExpected: '2:30',
        hoursInverted: 75,
        tags: ['docs'],
        assignees: [3, 5],
    );

    expect($input->toArray())->toBe([
        'project_id' => 12,
        'group_id' => 7,
        'title' => 'Write docs',
        'description' => 'Some **markdown**',
        'status' => 1,
        'priority' => 9,
        'date_start' => '2026-11-20',
        'date_end' => '2026-11-30',
        'hours_expected' => '02:30',
        'hours_inverted' => '01:15',
        'tags' => ['docs'],
        'assignees' => [3, 5],
    ]);
});

it('only sends the given fields of a task creation', function (): void {
    expect((new TaskCreate(projectId: 1, groupId: 2, title: 'x'))->toArray())
        ->toBe(['project_id' => 1, 'group_id' => 2, 'title' => 'x']);
});

it('distinguishes missing fields from null ones on update', function (): void {
    expect((new TaskUpdate(title: 'Renamed'))->toArray())->toBe(['title' => 'Renamed'])
        ->and((new TaskUpdate(description: null, dateStart: null, dateEnd: null, hoursExpected: null, hoursInverted: null))->toArray())
        ->toBe(['description' => null, 'date_start' => null, 'date_end' => null, 'hours_expected' => null, 'hours_inverted' => null])
        ->and((new TaskUpdate(tags: []))->toArray())->toBe(['tags' => []]);
});

it('serializes assignee mutations', function (): void {
    expect((new TaskUpdate(assignees: AssigneeMutation::add([7])))->toArray())->toBe(['assignees' => ['operation' => 'add', 'user_ids' => [7]]])
        ->and(AssigneeMutation::replace([3, 5])->toArray())->toBe(['operation' => 'replace', 'user_ids' => [3, 5]])
        ->and(AssigneeMutation::remove([3])->toArray())->toBe(['operation' => 'remove', 'user_ids' => [3]])
        ->and(AssigneeMutation::none()->toArray())->toBe(['operation' => 'replace', 'user_ids' => []]);
});

it('normalizes hours', function (string|int $input, string $expected): void {
    expect(Hours::normalize($input))->toBe($expected);
})->with([
    ['2:30', '02:30'],
    ['02:30', '02:30'],
    ['04:30:00', '04:30'],
    [' 1:05 ', '01:05'],
    [0, '00:00'],
    [150, '02:30'],
    ['99:59', '99:59'],
]);

it('rejects invalid input before sending', function (Closure $build, string $message): void {
    expect($build)->toThrow(InvalidInputException::class, $message);
})->with([
    'delayed status' => [fn () => new TaskUpdate(status: TaskStatus::Delayed), 'the delayed status (-1) is read-only'],
    'empty update' => [fn () => new TaskUpdate, 'provide at least one field to update'],
    'bad hours' => [fn () => new TaskUpdate(hoursExpected: '2h'), 'Invalid "hours_expected": expected HH:MM'],
    'bad minutes' => [fn () => Hours::normalize('1:75'), 'expected at most 99:59'],
    'negative minutes' => [fn () => Hours::normalize(-1), 'minutes cannot be negative'],
    'bad date' => [fn () => new TaskUpdate(dateEnd: '30/11/2026'), 'Invalid "date_end": expected a Y-m-d date'],
    'impossible date' => [fn () => new TaskUpdate(dateEnd: '2026-02-30'), 'expected a Y-m-d date'],
    'empty title' => [fn () => new TaskCreate(projectId: 1, groupId: 1, title: '  '), 'Invalid "title": cannot be empty'],
    'long title' => [fn () => new TaskUpdate(title: str_repeat('a', 256)), 'at most 255 characters'],
    'bad project id' => [fn () => new TaskCreate(projectId: 0, groupId: 1, title: 'x'), 'Invalid "project_id"'],
    'too many tags' => [fn () => new TaskUpdate(tags: array_fill(0, 21, 'x')), 'at most 20 tags'],
    'empty tag' => [fn () => new TaskUpdate(tags: ['ok', '']), 'Invalid "tags.1"'],
    'duplicated assignees' => [fn () => new TaskCreate(projectId: 1, groupId: 1, title: 'x', assignees: [3, 3]), 'contains duplicated ids'],
    'add without ids' => [fn () => AssigneeMutation::add([]), '"add" needs at least one user id'],
    'bad assignee id' => [fn () => AssigneeMutation::replace([0]), 'Invalid "assignees.user_ids.0"'],
]);

it('serializes batch updates with moves', function (): void {
    $batch = new TaskBatchUpdate(
        new TaskBatchItem(1, new TaskUpdate(status: TaskStatus::Done)),
        new TaskBatchItem(2, taskGroupId: 9),
        new TaskBatchItem(3, new TaskUpdate(dateEnd: null), taskGroupId: 20, destinationProjectId: 13),
    );

    expect($batch->toArray())->toBe(['tasks' => [
        ['id' => 1, 'changes' => ['status' => 3]],
        ['id' => 2, 'changes' => ['task_group_id' => 9]],
        ['id' => 3, 'changes' => ['date_end' => null, 'destination_project_id' => 13, 'task_group_id' => 20]],
    ]]);
});

it('validates batch limits and items', function (Closure $build, string $message): void {
    expect($build)->toThrow(InvalidInputException::class, $message);
})->with([
    'empty' => [fn () => new TaskBatchUpdate, 'between 1 and 4 tasks are required, 0 given'],
    'too many' => [fn () => new TaskBatchUpdate(...array_map(fn (int $id) => new TaskBatchItem($id, taskGroupId: 1), range(1, 5))), '5 given'],
    'duplicated' => [fn () => new TaskBatchUpdate(new TaskBatchItem(1, taskGroupId: 1), new TaskBatchItem(1, taskGroupId: 2)), 'each task can only appear once'],
    'no changes' => [fn () => new TaskBatchItem(1), 'provide at least one field to update'],
    'project without group' => [fn () => new TaskBatchItem(1, destinationProjectId: 13), 'is required when moving to another project'],
    'invalid changes fail when built' => [fn () => new TaskBatchItem(2, new TaskUpdate(status: TaskStatus::Delayed)), 'Invalid "status"'],
]);

it('serializes a task group with tasks', function (): void {
    $group = new TaskGroupCreate(
        'Sprint 1',
        new TaskGroupTask('First', priority: TaskPriority::High, assignees: [3]),
        new TaskGroupTask('Second', hoursExpected: '1:00', tags: ['a']),
    );

    expect($group->toArray())->toBe([
        'title' => 'Sprint 1',
        'tasks' => [
            ['title' => 'First', 'priority' => 2, 'assignees' => [3]],
            ['title' => 'Second', 'hours_expected' => '01:00', 'tags' => ['a']],
        ],
    ])->and((new TaskGroupCreate('Empty'))->toArray())->toBe(['title' => 'Empty'])
        ->and(fn () => new TaskGroupCreate('Too many', ...array_map(fn (int $i) => new TaskGroupTask("T{$i}"), range(1, 5))))
        ->toThrow(InvalidInputException::class, 'at most 4 tasks');
});

it('serializes projects', function (): void {
    expect((new ProjectCreate('Web', 'New site', '2026-11-01', new DateTimeImmutable('2026-12-31')))->toArray())
        ->toBe(['title' => 'Web', 'description' => 'New site', 'date_starts' => '2026-11-01', 'date_ends' => '2026-12-31'])
        ->and((new ProjectCreate('Web'))->toArray())->toBe(['title' => 'Web'])
        ->and(fn () => new ProjectCreate('Web', dateStarts: '2026-12-01', dateEnds: '2026-11-01'))->toThrow(InvalidInputException::class, 'cannot be before date_starts')
        ->and((new ProjectUpdate(status: ProjectStatus::Delayed, dateEnds: null, notifyTasks: false))->toArray())
        ->toBe(['date_ends' => null, 'status' => -1, 'notify_tasks' => false])
        ->and(fn () => new ProjectUpdate)->toThrow(InvalidInputException::class, 'provide at least one field');
});

it('serializes comments', function (): void {
    expect((new CommentCreate('Hola @Bob', mentions: [5], mediaIds: [77]))->toArray())
        ->toBe(['comment' => 'Hola @Bob', 'mentions' => [5], 'media_ids' => [77]])
        ->and((new CommentCreate(null, mediaIds: [77]))->toArray())->toBe(['comment' => null, 'media_ids' => [77]])
        ->and(fn () => new CommentCreate(str_repeat('a', 65536)))->toThrow(InvalidInputException::class, 'at most 65535');
});
