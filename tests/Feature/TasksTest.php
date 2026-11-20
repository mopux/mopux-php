<?php

declare(strict_types=1);

use Mopux\Data\Attachment;
use Mopux\Data\FocusList;
use Mopux\Data\Input\AssigneeMutation;
use Mopux\Data\Input\TaskBatchItem;
use Mopux\Data\Input\TaskBatchUpdate;
use Mopux\Data\Input\TaskCreate;
use Mopux\Data\Input\TaskUpdate;
use Mopux\Data\MarkdownHelp;
use Mopux\Data\MoveOptions;
use Mopux\Data\Task;
use Mopux\Data\Touch;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Exceptions\UncertainWriteException;
use Mopux\Exceptions\ValidationException;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

it('creates a task', function (): void {
    $client = new MockClient(['POST tasks' => okFixture('task_detail', 'Task created successfully.')]);

    $task = mopux($client)->tasks()->create(new TaskCreate(projectId: 12, groupId: 7, title: 'Diseñar la API', hoursExpected: '4:30', assignees: [3, 5]));

    expect($client->lastRecorded()->json)->toBe(['project_id' => 12, 'group_id' => 7, 'title' => 'Diseñar la API', 'hours_expected' => '04:30', 'assignees' => [3, 5]])
        ->and($task)->toBeInstanceOf(Task::class)
        ->and($task->id)->toBe(101);
});

it('does not retry a failed creation', function (): void {
    $client = new MockClient([MockResponse::serverError(), okFixture('task_detail')]);
    $mopux = new Mopux\Mopux('https://acme.mopux.com', '1|t', $client);

    expect(fn () => $mopux->tasks()->create(new TaskCreate(projectId: 12, groupId: 7, title: 'x')))->toThrow(UncertainWriteException::class);
    $client->assertSentCount(1);
});

it('reports validation errors of a creation', function (): void {
    $client = new MockClient([MockResponse::json(422, ['success' => false, 'message' => 'Error creating task.', 'data' => ['error' => 'User 9 is not a project member.', 'errors' => ['assignees' => ['User 9 is not a project member.']]]])]);

    try {
        mopux($client)->tasks()->create(new TaskCreate(projectId: 12, groupId: 7, title: 'x', assignees: [9]));
        $this->fail('Expected an exception.');
    } catch (ValidationException $exception) {
        expect($exception->firstError('assignees'))->toBe('User 9 is not a project member.');
    }
});

it('updates a task and a batch of tasks', function (): void {
    $client = new MockClient([
        'PATCH tasks/batch' => MockResponse::ok(['tasks' => [apiFixture('task_detail'), apiFixture('task_detail')]]),
        'PATCH tasks/101' => okFixture('task_detail'),
    ]);
    $tasks = mopux($client)->tasks();

    $tasks->update(101, new TaskUpdate(dateEnd: null, assignees: AssigneeMutation::add([7])));
    $single = $client->lastRecorded()->json;
    $updated = $tasks->updateBatch(new TaskBatchUpdate(new TaskBatchItem(101, new TaskUpdate(status: TaskStatus::Done)), new TaskBatchItem(102, taskGroupId: 8)));

    expect($single)->toBe(['date_end' => null, 'assignees' => ['operation' => 'add', 'user_ids' => [7]]])
        ->and($client->lastRecorded()->json)->toBe(['tasks' => [['id' => 101, 'changes' => ['status' => 3]], ['id' => 102, 'changes' => ['task_group_id' => 8]]]])
        ->and($updated)->toHaveCount(2);
});

it('lists my tasks', function (): void {
    $client = new MockClient(['GET tasks/my' => MockResponse::ok([apiFixture('task_summary')])]);

    $tasks = mopux($client)->tasks()->my('api', [12]);

    expect($client->lastRecorded()->query)->toBe(['search' => 'api', 'params' => ['projects' => ['12']]])
        ->and($tasks[0]->status)->toBe(TaskStatus::Delayed);
});

it('shows and deletes a task', function (): void {
    $client = new MockClient(['GET tasks/101' => okFixture('task_detail'), 'DELETE tasks/101' => MockResponse::ok([])]);
    $tasks = mopux($client)->tasks();

    expect($tasks->show(101)->tags)->toBe(['api', 'backend']);
    $tasks->delete(101);
    $client->assertSent('DELETE tasks/101', 1);
});

it('updates single task fields', function (Closure $call, string $route, array $body): void {
    $client = new MockClient([$route => okFixture('task_detail')]);

    $result = $call(mopux($client)->tasks());

    expect($result)->toBeInstanceOf(Task::class)
        ->and($client->lastRecorded()->json)->toBe($body);
    $client->assertSent($route, 1);
})->with([
    'status' => [fn ($tasks) => $tasks->updateStatus(101, TaskStatus::Review), 'PATCH tasks/101/status', ['status' => 2]],
    'priority' => [fn ($tasks) => $tasks->updatePriority(101, TaskPriority::Maximum), 'PATCH tasks/101/priority', ['priority' => 9]],
    'assign' => [fn ($tasks) => $tasks->assign(101, 5), 'PATCH tasks/101/assign', ['assignee_id' => 5]],
    'unassign' => [fn ($tasks) => $tasks->assign(101, null), 'PATCH tasks/101/assign', ['assignee_id' => null]],
    'title' => [fn ($tasks) => $tasks->updateTitle(101, 'New'), 'PATCH tasks/101/title', ['title' => 'New']],
    'description' => [fn ($tasks) => $tasks->updateDescription(101, null), 'PATCH tasks/101/description', ['description' => null]],
    'start' => [fn ($tasks) => $tasks->updateStart(101, new DateTimeImmutable('2026-11-21')), 'PATCH tasks/101/start', ['date_start' => '2026-11-21']],
    'end' => [fn ($tasks) => $tasks->updateEnd(101, null), 'PATCH tasks/101/end', ['date_end' => null]],
    'hours' => [fn ($tasks) => $tasks->updateHours(101, inverted: 90), 'PATCH tasks/101/hours', ['hours_inverted' => '01:30']],
    'clear hours' => [fn ($tasks) => $tasks->updateHours(101, expected: null, inverted: '2:00'), 'PATCH tasks/101/hours', ['hours_expected' => null, 'hours_inverted' => '02:00']],
]);

it('validates single field updates before sending', function (): void {
    $client = new MockClient;
    $tasks = mopux($client)->tasks();

    expect(fn () => $tasks->updateStatus(101, TaskStatus::Delayed))->toThrow(InvalidInputException::class, 'read-only')
        ->and(fn () => $tasks->updateHours(101))->toThrow(InvalidInputException::class, 'provide expected or inverted hours')
        ->and(fn () => $tasks->updateTitle(101, ''))->toThrow(InvalidInputException::class)
        ->and(fn () => $tasks->attachments(101, perPage: 0))->toThrow(InvalidInputException::class, 'per_page');
    $client->assertNothingSent();
});

it('moves a task and returns the warnings', function (): void {
    $client = new MockClient(['PATCH tasks/101/move' => MockResponse::ok(apiFixture('task_detail') + ['warnings' => apiFixture('move_options')['projects'][0]['warnings']])]);

    $moved = mopux($client)->tasks()->move(101, 13, 20);

    expect($client->lastRecorded()->json)->toBe(['project_id' => 13, 'group_id' => 20])
        ->and($moved->task->id)->toBe(101)
        ->and($moved->warnings[0]->userId)->toBe(5);
});

it('sends a touch', function (): void {
    $client = new MockClient(['POST tasks/101/touches' => okFixture('touch')]);

    $touch = mopux($client)->tasks()->sendTouch(101, 5, '¿Cómo va?');

    expect($touch)->toBeInstanceOf(Touch::class)
        ->and($client->lastRecorded()->json)->toBe(['recipient_id' => 5, 'message' => '¿Cómo va?']);
});

it('manages the focus', function (): void {
    $client = new MockClient([
        'GET tasks/focus' => okFixture('focus'),
        'POST tasks/102/focus' => okFixture('focus'),
        'DELETE tasks/102/focus' => MockResponse::ok(['tasks' => [], 'limit' => 5]),
    ]);
    $tasks = mopux($client)->tasks();

    $focus = $tasks->focus();
    $pinned = $tasks->pinFocus(102);
    $unpinned = $tasks->unpinFocus(102);

    expect($focus)->toBeInstanceOf(FocusList::class)
        ->and($focus->limit)->toBe(5)
        ->and($pinned->contains(102))->toBeTrue()
        ->and($pinned->isFull())->toBeFalse()
        ->and($unpinned->tasks)->toBe([]);
});

it('gets move options, attachments and markdown help', function (): void {
    $client = new MockClient([
        'GET tasks/101/move-options' => okFixture('move_options'),
        'GET tasks/101/attachments' => okFixture('task_attachments'),
        'GET tasks/markdown-help' => okFixture('markdown_help'),
    ]);
    $tasks = mopux($client)->tasks();

    expect($tasks->moveOptions(101))->toBeInstanceOf(MoveOptions::class);

    $attachments = $tasks->attachments(101, page: 1, perPage: 20);
    expect($client->lastRecorded()->query)->toBe(['page' => '1', 'per_page' => '20'])
        ->and($attachments->items[0])->toBeInstanceOf(Attachment::class)
        ->and($attachments->nextPage())->toBe(2)
        ->and($tasks->markdownHelp())->toBeInstanceOf(MarkdownHelp::class);
});
