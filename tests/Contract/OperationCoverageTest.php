<?php

declare(strict_types=1);

use Mopux\Data\Input\CommentCreate;
use Mopux\Data\Input\ProjectCreate;
use Mopux\Data\Input\ProjectUpdate;
use Mopux\Data\Input\TaskBatchItem;
use Mopux\Data\Input\TaskBatchUpdate;
use Mopux\Data\Input\TaskCreate;
use Mopux\Data\Input\TaskUpdate;
use Mopux\Data\Input\Upload;
use Mopux\Enums\ProjectStatus;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\ConnectionException;
use Mopux\Mopux;
use Mopux\Testing\MockClient;
use Mopux\Testing\NetworkError;
use Symfony\Component\Yaml\Yaml;

/*
 * Contract between the SDK and resources/openapi.yaml (copy of
 * mopux-com/docs/api/openapi-v1-public.yaml, refreshed with `composer sync-spec`).
 *
 * Every operation of the spec maps to an SDK call. Each call is executed against a
 * MockClient that aborts the request, and the request method and path must match
 * the operation. The suite fails when the API gains an operation the SDK does not
 * cover, or when a mapped call hits the wrong endpoint.
 */

const UUID = '9b2f6a3e-1c1d-4e8f-9f0a-6f1d2c3b4a5e';

/**
 * @return array<string, Closure(Mopux): mixed>
 */
function sdkOperations(): array
{
    return [
        'POST /api/v1/auth/login' => fn (Mopux $m) => $m->auth()->login('ada@acme.test', 'secret'),
        'POST /api/v1/auth/logout' => fn (Mopux $m) => $m->auth()->logout(),
        'GET /api/v1/auth/user' => fn (Mopux $m) => $m->auth()->user(),

        'GET /api/v1/projects' => fn (Mopux $m) => $m->projects()->list(),
        'POST /api/v1/projects' => fn (Mopux $m) => $m->projects()->create(new ProjectCreate('Web')),
        'GET /api/v1/projects/{projectId}' => fn (Mopux $m) => $m->projects()->show(12),
        'PATCH /api/v1/projects/{projectId}' => fn (Mopux $m) => $m->projects()->update(12, new ProjectUpdate(status: ProjectStatus::Done)),
        'DELETE /api/v1/projects/{projectId}' => fn (Mopux $m) => $m->projects()->delete(12, 'web'),
        'GET /api/v1/projects/{projectId}/activity' => fn (Mopux $m) => $m->projects()->activity(12),
        'GET /api/v1/projects/{projectId}/members' => fn (Mopux $m) => $m->projects()->members(12),
        'POST /api/v1/projects/{projectId}/members' => fn (Mopux $m) => $m->projects()->addMember(12, 5),
        'DELETE /api/v1/projects/{projectId}/members/{userId}' => fn (Mopux $m) => $m->projects()->removeMember(12, 5),
        'GET /api/v1/projects/{projectId}/summary' => fn (Mopux $m) => $m->projects()->summary(12),
        'GET /api/v1/projects/{projectId}/board' => fn (Mopux $m) => $m->projects()->board(12),

        'POST /api/v1/projects/{projectId}/task-groups' => fn (Mopux $m) => $m->taskGroups()->create(12, 'Backlog'),
        'PATCH /api/v1/projects/{projectId}/task-groups/reorder' => fn (Mopux $m) => $m->taskGroups()->reorder(12, [8, 7]),
        'PATCH /api/v1/projects/{projectId}/task-groups/{taskGroupId}/tasks/reorder' => fn (Mopux $m) => $m->taskGroups()->reorderTasks(12, 7, [101]),
        'PATCH /api/v1/projects/{projectId}/task-groups/{taskGroupId}/title' => fn (Mopux $m) => $m->taskGroups()->rename(12, 7, 'Renamed'),
        'PATCH /api/v1/projects/{projectId}/task-groups/{taskGroupId}/archive' => fn (Mopux $m) => $m->taskGroups()->archive(12, 7),
        'PATCH /api/v1/projects/{projectId}/task-groups/{taskGroupId}/unarchive' => fn (Mopux $m) => $m->taskGroups()->unarchive(12, 7),
        'PATCH /api/v1/projects/{projectId}/task-groups/{taskGroupId}/move' => fn (Mopux $m) => $m->taskGroups()->move(12, 7, 13),
        'PATCH /api/v1/projects/{projectId}/task-groups/{taskGroupId}/copy' => fn (Mopux $m) => $m->taskGroups()->copy(12, 7, 13, 'Copy'),
        'POST /api/v1/projects/{projectId}/task-groups/{taskGroupId}/create-project' => fn (Mopux $m) => $m->taskGroups()->createProject(12, 7, 'Spin-off'),
        'DELETE /api/v1/projects/{projectId}/task-groups/{taskGroupId}' => fn (Mopux $m) => $m->taskGroups()->delete(12, 7),
        'GET /api/v1/projects/{projectId}/task-groups/{taskGroupId}/focus' => fn (Mopux $m) => $m->taskGroups()->focus(12, 7),
        'GET /api/v1/projects/{projectId}/task-groups/{taskGroupId}/status-summary' => fn (Mopux $m) => $m->taskGroups()->statusSummary(12, 7),

        'POST /api/v1/tasks' => fn (Mopux $m) => $m->tasks()->create(new TaskCreate(projectId: 12, groupId: 7, title: 'x')),
        'PATCH /api/v1/tasks/batch' => fn (Mopux $m) => $m->tasks()->updateBatch(new TaskBatchUpdate(new TaskBatchItem(101, taskGroupId: 8))),
        'GET /api/v1/tasks/my' => fn (Mopux $m) => $m->tasks()->my(),
        'GET /api/v1/tasks/markdown-help' => fn (Mopux $m) => $m->tasks()->markdownHelp(),
        'GET /api/v1/tasks/focus' => fn (Mopux $m) => $m->tasks()->focus(),
        'POST /api/v1/tasks/{taskId}/focus' => fn (Mopux $m) => $m->tasks()->pinFocus(101),
        'DELETE /api/v1/tasks/{taskId}/focus' => fn (Mopux $m) => $m->tasks()->unpinFocus(101),
        'GET /api/v1/tasks/{taskId}/move-options' => fn (Mopux $m) => $m->tasks()->moveOptions(101),
        'GET /api/v1/tasks/{taskId}/attachments' => fn (Mopux $m) => $m->tasks()->attachments(101),
        'POST /api/v1/tasks/{taskId}/files' => fn (Mopux $m) => $m->tasks()->uploadFile(101, Upload::fromString('x', 'a.txt')),
        'DELETE /api/v1/tasks/{taskId}/files/{mediaId}' => fn (Mopux $m) => $m->tasks()->deleteFile(101, 77),
        'GET /api/v1/tasks/{taskId}' => fn (Mopux $m) => $m->tasks()->show(101),
        'PATCH /api/v1/tasks/{taskId}' => fn (Mopux $m) => $m->tasks()->update(101, new TaskUpdate(title: 'x')),
        'DELETE /api/v1/tasks/{taskId}' => fn (Mopux $m) => $m->tasks()->delete(101),
        'PATCH /api/v1/tasks/{taskId}/status' => fn (Mopux $m) => $m->tasks()->updateStatus(101, TaskStatus::Done),
        'PATCH /api/v1/tasks/{taskId}/priority' => fn (Mopux $m) => $m->tasks()->updatePriority(101, TaskPriority::High),
        'PATCH /api/v1/tasks/{taskId}/assign' => fn (Mopux $m) => $m->tasks()->assign(101, 5),
        'PATCH /api/v1/tasks/{taskId}/move' => fn (Mopux $m) => $m->tasks()->move(101, 13, 20),
        'PATCH /api/v1/tasks/{taskId}/title' => fn (Mopux $m) => $m->tasks()->updateTitle(101, 'x'),
        'PATCH /api/v1/tasks/{taskId}/description' => fn (Mopux $m) => $m->tasks()->updateDescription(101, 'x'),
        'PATCH /api/v1/tasks/{taskId}/start' => fn (Mopux $m) => $m->tasks()->updateStart(101, '2026-11-20'),
        'PATCH /api/v1/tasks/{taskId}/end' => fn (Mopux $m) => $m->tasks()->updateEnd(101, '2026-11-30'),
        'PATCH /api/v1/tasks/{taskId}/hours' => fn (Mopux $m) => $m->tasks()->updateHours(101, '1:00'),
        'POST /api/v1/tasks/{taskId}/touches' => fn (Mopux $m) => $m->tasks()->sendTouch(101, 5),

        'GET /api/v1/tasks/{taskId}/comments' => fn (Mopux $m) => $m->comments()->list(101),
        'POST /api/v1/tasks/{taskId}/comments' => fn (Mopux $m) => $m->comments()->create(101, new CommentCreate('Hola')),
        'PATCH /api/v1/comments/{commentId}' => fn (Mopux $m) => $m->comments()->update(55, 'x'),
        'DELETE /api/v1/comments/{commentId}' => fn (Mopux $m) => $m->comments()->delete(55),
        'POST /api/v1/comments/{commentId}/reply' => fn (Mopux $m) => $m->comments()->reply(55, 'x'),

        'GET /api/v1/notifications' => fn (Mopux $m) => $m->notifications()->list(),
        'PATCH /api/v1/notifications/{notificationId}/read' => fn (Mopux $m) => $m->notifications()->markRead(UUID),
        'PATCH /api/v1/notifications/{notificationId}/unread' => fn (Mopux $m) => $m->notifications()->markUnread(UUID),
        'PATCH /api/v1/notifications/read-all' => fn (Mopux $m) => $m->notifications()->markAllRead(),
        'GET /api/v1/notifications/badge' => fn (Mopux $m) => $m->notifications()->unreadCount(),
        'DELETE /api/v1/notifications' => fn (Mopux $m) => $m->notifications()->deleteAll(),
        'DELETE /api/v1/notifications/{notificationId}' => fn (Mopux $m) => $m->notifications()->delete(UUID),

        'GET /api/v1/touches' => fn (Mopux $m) => $m->touches()->list(),
        'POST /api/v1/touches/{touchId}/reply' => fn (Mopux $m) => $m->touches()->reply(4, 'Hecho'),

        'GET /api/v1/team' => fn (Mopux $m) => $m->team()->list(),
        'GET /api/v1/team/invitations' => fn (Mopux $m) => $m->teamInvitations()->list(),
        'POST /api/v1/team/invitations' => fn (Mopux $m) => $m->teamInvitations()->create('new@acme.test', 2),
        'POST /api/v1/team/invitations/{invitationId}/resend' => fn (Mopux $m) => $m->teamInvitations()->resend(2),
        'DELETE /api/v1/team/invitations/{invitationId}' => fn (Mopux $m) => $m->teamInvitations()->delete(2),
    ];
}

/**
 * @return list<string> `METHOD /path` of every operation in the bundled spec.
 */
function specOperations(): array
{
    $spec = Yaml::parseFile(__DIR__.'/../../resources/openapi.yaml');
    $operations = [];

    foreach ($spec['paths'] as $path => $methods) {
        foreach (array_keys($methods) as $method) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $operations[] = strtoupper($method).' '.$path;
            }
        }
    }

    sort($operations);

    return $operations;
}

it('covers every operation of the spec', function (): void {
    $spec = specOperations();
    $sdk = array_keys(sdkOperations());
    sort($sdk);

    expect(array_values(array_diff($spec, $sdk)))->toBe([], 'Operations in the spec without an SDK method')
        ->and(array_values(array_diff($sdk, $spec)))->toBe([], 'SDK operations that are not in the spec')
        ->and(count($spec))->toBe(69);
});

it('sends each operation to its endpoint', function (string $operation): void {
    [$method, $template] = explode(' ', $operation, 2);
    $client = new MockClient([new NetworkError('aborted by the contract test')]);

    try {
        sdkOperations()[$operation](mopux($client));
        $this->fail("{$operation} did not send any request.");
    } catch (ConnectionException) {
        // Expected: the request was captured and aborted.
    }

    $request = $client->lastRecorded();
    $pattern = '#^'.preg_replace('/\\\{[A-Za-z]+\\\}/', '[^/]+', preg_quote($template, '#')).'$#';

    expect($request->method)->toBe($method)
        ->and($request->request->getUri()->getPath())->toMatch($pattern);
    $client->assertSentCount(1);
})->with(fn () => array_keys(sdkOperations()));
