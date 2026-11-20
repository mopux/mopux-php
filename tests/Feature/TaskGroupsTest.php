<?php

declare(strict_types=1);

use Mopux\Data\GroupStatusSummary;
use Mopux\Data\Input\TaskGroupCreate;
use Mopux\Data\Input\TaskGroupTask;
use Mopux\Data\Project;
use Mopux\Data\TaskGroup;
use Mopux\Enums\TaskPriority;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

function groupPayload(array $overrides = []): array
{
    return $overrides + ['id' => 7, 'project_id' => 12, 'title' => 'Backlog', 'status' => 0, 'position' => 1];
}

it('creates an empty group from a title', function (): void {
    $client = new MockClient(['POST projects/12/task-groups' => MockResponse::ok(groupPayload())]);

    $group = mopux($client)->taskGroups()->create(12, 'Backlog');

    expect($client->lastRecorded()->json)->toBe(['title' => 'Backlog'])
        ->and($group)->toBeInstanceOf(TaskGroup::class)
        ->and($group->tasks)->toBe([]);
});

it('creates a group with tasks', function (): void {
    $client = new MockClient([MockResponse::ok(['group' => groupPayload(), 'tasks' => [apiFixture('task_detail')]])]);

    $group = mopux($client)->taskGroups()->create(12, new TaskGroupCreate('Backlog', new TaskGroupTask('Diseñar la API', priority: TaskPriority::High, assignees: [3])));

    expect($client->lastRecorded()->json)->toBe(['title' => 'Backlog', 'tasks' => [['title' => 'Diseñar la API', 'priority' => 2, 'assignees' => [3]]]])
        ->and($group->id)->toBe(7)
        ->and($group->tasks[0]->tags)->toBe(['api', 'backend']);
});

it('reorders groups and tasks', function (): void {
    $client = new MockClient([
        'PATCH projects/12/task-groups/reorder' => MockResponse::ok(['task_groups' => [['id' => 8, 'title' => 'B', 'position' => 1], ['id' => 7, 'title' => 'A', 'position' => 2]]]),
        'PATCH projects/12/task-groups/7/tasks/reorder' => MockResponse::ok(['tasks' => [['id' => 102, 'title' => 'X', 'group_position' => 1]]]),
    ]);
    $groups = mopux($client)->taskGroups();

    $positions = $groups->reorder(12, [8, 7]);
    $request = $client->lastRecorded()->json;
    $taskPositions = $groups->reorderTasks(12, 7, [102]);

    expect($request)->toBe(['groups' => [['value' => 8, 'order' => 0], ['value' => 7, 'order' => 1]]])
        ->and($positions[1]->position)->toBe(2)
        ->and($client->lastRecorded()->json)->toBe(['tasks' => [['value' => 102, 'order' => 0]]])
        ->and($taskPositions[0]->position)->toBe(1)
        ->and(fn () => $groups->reorder(12, []))->toThrow(InvalidInputException::class, 'provide at least one id');
});

it('renames, archives, unarchives, moves and copies groups', function (): void {
    $client = new MockClient([
        'PATCH projects/12/task-groups/7/title' => MockResponse::ok(groupPayload(['title' => 'Renamed'])),
        'PATCH projects/12/task-groups/7/archive' => MockResponse::ok(groupPayload(['status' => 1])),
        'PATCH projects/12/task-groups/7/unarchive' => MockResponse::ok(groupPayload()),
        'PATCH projects/12/task-groups/7/move' => MockResponse::ok(groupPayload(['project_id' => 13])),
        'PATCH projects/12/task-groups/7/copy' => MockResponse::ok(groupPayload(['id' => 30, 'title' => 'Copy'])),
    ]);
    $groups = mopux($client)->taskGroups();

    expect($groups->rename(12, 7, 'Renamed')->title)->toBe('Renamed')
        ->and($client->lastRecorded()->json)->toBe(['title' => 'Renamed'])
        ->and($groups->archive(12, 7)->archived)->toBeTrue()
        ->and($client->lastRecorded()->body())->toBe('')
        ->and($groups->unarchive(12, 7)->archived)->toBeFalse()
        ->and($groups->move(12, 7, 13)->projectId)->toBe(13)
        ->and($client->lastRecorded()->json)->toBe(['project_id' => 13])
        ->and($groups->copy(12, 7, 12, 'Copy')->id)->toBe(30)
        ->and($client->lastRecorded()->json)->toBe(['project_id' => 12, 'title' => 'Copy']);
});

it('creates a project from a group', function (): void {
    $client = new MockClient(['POST projects/12/task-groups/7/create-project' => MockResponse::ok(['id' => 40, 'title' => 'Spin-off', 'description' => null, 'copied_from_group_id' => 7])]);

    $project = mopux($client)->taskGroups()->createProject(12, 7, 'Spin-off');

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->copiedFromGroupId)->toBe(7)
        ->and($client->lastRecorded()->json)->toBe(['title' => 'Spin-off']);
});

it('deletes a group', function (): void {
    $client = new MockClient(['DELETE projects/12/task-groups/7' => MockResponse::ok([])]);

    mopux($client)->taskGroups()->delete(12, 7);

    $client->assertSent('DELETE projects/12/task-groups/7', 1);
});

it('gets the group focus and status summary', function (): void {
    $client = new MockClient([
        'GET projects/12/task-groups/7/focus' => MockResponse::ok(['group' => groupPayload(), 'tasks' => [apiFixture('task_board')]]),
        'GET projects/12/task-groups/7/status-summary' => okFixture('group_status_summary'),
    ]);
    $groups = mopux($client)->taskGroups();

    $focus = $groups->focus(12, 7, 'tests');
    $query = $client->lastRecorded()->query;
    $summary = $groups->statusSummary(12, 7);

    expect($focus->tasks[0]->id)->toBe(103)
        ->and($query)->toBe(['search' => 'tests'])
        ->and($summary)->toBeInstanceOf(GroupStatusSummary::class)
        ->and($summary->counts->total)->toBe(28)
        ->and($summary->group->title)->toBe('Backlog');
});
