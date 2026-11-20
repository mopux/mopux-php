<?php

declare(strict_types=1);

use Mopux\Data\Input\AssigneeMutation;
use Mopux\Data\Input\CommentCreate;
use Mopux\Data\Input\ProjectCreate;
use Mopux\Data\Input\ProjectUpdate;
use Mopux\Data\Input\TaskBatchItem;
use Mopux\Data\Input\TaskBatchUpdate;
use Mopux\Data\Input\TaskCreate;
use Mopux\Data\Input\TaskGroupCreate;
use Mopux\Data\Input\TaskGroupTask;
use Mopux\Data\Input\TaskUpdate;
use Mopux\Data\Input\Upload;
use Mopux\Data\Project;
use Mopux\Enums\ActivityFilter;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\NotFoundException;
use Mopux\Exceptions\ValidationException;
use Mopux\Mopux;
use Mopux\Tests\Integration\IntegrationEnv;

/*
 * End-to-end flow against a real tenant. Skipped unless configured
 * (see tests/Integration/.env.example). Run with `composer test:integration`.
 *
 * Everything is created inside a throwaway project that is deleted at the end,
 * even when an assertion fails.
 */

beforeEach(function (): void {
    if (! IntegrationEnv::configured()) {
        $this->markTestSkipped('Set MOPUX_INTEGRATION_URL and a token or credentials (tests/Integration/.env).');
    }
});

it('runs the whole api flow against a real tenant', function (): void {
    $mopux = IntegrationEnv::client();
    $project = null;

    try {
        // Auth
        $me = $mopux->auth()->user();
        expect($me->id)->toBeGreaterThan(0);

        // Read-only endpoints
        $mopux->projects()->list(perPage: 5);
        $mopux->tasks()->my();
        $mopux->tasks()->focus();
        $mopux->tasks()->markdownHelp();
        $mopux->notifications()->list(perPage: 5);
        $mopux->notifications()->unreadCount();
        $mopux->touches()->list();
        $mopux->team()->list(perPage: 5);

        // Project
        $title = 'mopux-php integration '.date('Y-m-d H:i:s');
        $project = $mopux->projects()->create(new ProjectCreate($title, 'Created by the mopux-php integration tests', date('Y-m-d')));
        expect($project->title)->toBe($title);

        $project = $mopux->projects()->update($project->id, new ProjectUpdate(description: 'Updated by mopux-php'));
        expect($project->description)->toBe('Updated by mopux-php');
        expect($mopux->projects()->show($project->id)->slug)->not->toBeNull();

        $members = $mopux->projects()->members($project->id);
        expect(array_map(fn ($user) => $user->id, $members))->toContain($me->id);

        // Group with tasks (atomic)
        $group = $mopux->taskGroups()->create($project->id, new TaskGroupCreate(
            'Backlog',
            new TaskGroupTask('First task', priority: TaskPriority::High, hoursExpected: '1:30', tags: ['sdk']),
            new TaskGroupTask('Second task', assignees: [$me->id]),
        ));
        expect($group->tasks)->toHaveCount(2)
            ->and($group->tasks[0]->hoursExpected)->toBe('01:30:00')
            ->and($group->tasks[0]->tags)->toBe(['sdk'])
            ->and($group->tasks[1]->assigneeIds())->toBe([$me->id]);

        // Single task create and updates
        $task = $mopux->tasks()->create(new TaskCreate(
            projectId: $project->id,
            groupId: $group->id,
            title: 'Third task',
            description: 'With **markdown**',
            dateStart: date('Y-m-d'),
            dateEnd: date('Y-m-d', strtotime('+7 days')),
        ));
        expect($task->group?->id)->toBe($group->id);

        $task = $mopux->tasks()->update($task->id, new TaskUpdate(
            status: TaskStatus::InProgress,
            hoursInverted: 45,
            dateEnd: null,
            assignees: AssigneeMutation::replace([$me->id]),
        ));
        expect($task->status)->toBe(TaskStatus::InProgress)
            ->and($task->hoursInverted)->toBe('00:45:00')
            ->and($task->dateEnd)->toBeNull()
            ->and($task->assigneeIds())->toBe([$me->id]);

        $task = $mopux->tasks()->updateStatus($task->id, TaskStatus::Review);
        $task = $mopux->tasks()->updatePriority($task->id, TaskPriority::Maximum);
        $task = $mopux->tasks()->updateTitle($task->id, 'Third task (renamed)');
        $task = $mopux->tasks()->updateDescription($task->id, null);
        $task = $mopux->tasks()->updateHours($task->id, expected: '2:00');
        expect($task->status)->toBe(TaskStatus::Review)
            ->and($task->priority)->toBe(TaskPriority::Maximum)
            ->and($task->title)->toBe('Third task (renamed)')
            ->and($task->description)->toBeNull()
            ->and($task->hoursExpected)->toBe('02:00:00');

        // Batch
        $updated = $mopux->tasks()->updateBatch(new TaskBatchUpdate(
            new TaskBatchItem($group->tasks[0]->id, new TaskUpdate(status: TaskStatus::Done)),
            new TaskBatchItem($group->tasks[1]->id, new TaskUpdate(priority: TaskPriority::Low)),
        ));
        expect($updated[0]->status)->toBe(TaskStatus::Done)
            ->and($updated[1]->priority)->toBe(TaskPriority::Low);

        // Validation errors come back as exceptions and change nothing
        expect(fn () => $mopux->tasks()->create(new TaskCreate(projectId: $project->id, groupId: $group->id, title: 'Bad', assignees: [999_999_999])))
            ->toThrow(ValidationException::class);

        // Board, focus, summary, activity
        $board = $mopux->projects()->board($project->id);
        expect($board->group($group->id))->not->toBeNull();
        $focus = $mopux->taskGroups()->focus($project->id, $group->id);
        expect(count($focus->tasks))->toBeGreaterThanOrEqual(3);
        $mopux->taskGroups()->statusSummary($project->id, $group->id);
        $summary = $mopux->projects()->summary($project->id);
        expect($summary->total)->toBe(3);
        $mopux->projects()->activity($project->id, ActivityFilter::All, perPage: 10);

        // Reorder and rename
        $mopux->taskGroups()->reorderTasks($project->id, $group->id, [$task->id, $group->tasks[1]->id, $group->tasks[0]->id]);
        expect($mopux->taskGroups()->rename($project->id, $group->id, 'Backlog (renamed)')->title)->toBe('Backlog (renamed)');

        // Comments, mentions, reply and document
        $comment = $mopux->comments()->create($task->id, new CommentCreate('Hello from mopux-php', mentions: [$me->id]));
        $reply = $mopux->comments()->reply($comment->id, 'A reply');
        expect($reply->parentId)->toBe($comment->id);
        $edited = $mopux->comments()->update($comment->id, 'Hello from mopux-php (edited)');
        expect($edited->comment)->toBe('Hello from mopux-php (edited)');

        if (IntegrationEnv::get('MOPUX_INTEGRATION_UPLOADS') !== '0') {
            $withFile = $mopux->comments()->create($task->id, 'With a file', [Upload::fromString("a,b\n1,2\n", 'sdk-test.csv', 'text/csv')]);
            expect($withFile->medias)->toHaveCount(1);

            $attachment = $mopux->tasks()->uploadFile($task->id, Upload::fromString('plain text', 'sdk-direct.txt', 'text/plain'));
            expect($attachment->direct)->toBeTrue();
            $attachments = $mopux->tasks()->attachments($task->id);
            expect($attachments->total)->toBeGreaterThanOrEqual(2);
            $mopux->tasks()->deleteFile($task->id, $attachment->id);
        }

        $comments = $mopux->comments()->list($task->id);
        expect($comments->count())->toBeGreaterThanOrEqual(2);
        $mopux->tasks()->attachments($task->id);
        $mopux->comments()->delete($reply->id);

        // Focus pin/unpin (only when the user's focus has room: never touch their pinned tasks)
        if (! $mopux->tasks()->focus()->isFull()) {
            $pinned = $mopux->tasks()->pinFocus($task->id);
            expect($pinned->contains($task->id))->toBeTrue();
            expect($mopux->tasks()->unpinFocus($task->id)->contains($task->id))->toBeFalse();
        }

        // Move options, archive, delete
        $mopux->tasks()->moveOptions($task->id);
        expect($mopux->taskGroups()->archive($project->id, $group->id)->archived)->toBeTrue();
        expect($mopux->taskGroups()->unarchive($project->id, $group->id)->archived)->toBeFalse();

        $mopux->tasks()->delete($task->id);
        expect(fn () => $mopux->tasks()->show($task->id))->toThrow(NotFoundException::class);
    } finally {
        if ($project instanceof Project) {
            cleanupProject($mopux, $project->id);
        }

        if (IntegrationEnv::get('MOPUX_INTEGRATION_TOKEN') === null) {
            $mopux->auth()->logout();
        }
    }
})->group('integration');

function cleanupProject(Mopux $mopux, int $projectId): void
{
    $slug = $mopux->projects()->show($projectId)->slug;

    if ($slug !== null) {
        $mopux->projects()->delete($projectId, $slug);
    }
}
