<?php

declare(strict_types=1);

use Mopux\Data\ApiToken;
use Mopux\Data\Attachment;
use Mopux\Data\AuthToken;
use Mopux\Data\Comment;
use Mopux\Data\DataObject;
use Mopux\Data\MarkdownHelp;
use Mopux\Data\MoveOptions;
use Mopux\Data\Notification;
use Mopux\Data\Project;
use Mopux\Data\ProjectActivity;
use Mopux\Data\ProjectBoard;
use Mopux\Data\ProjectStatistics;
use Mopux\Data\StatusCounts;
use Mopux\Data\Task;
use Mopux\Data\TaskGroup;
use Mopux\Data\TeamInvitation;
use Mopux\Data\TeamMember;
use Mopux\Data\Touch;
use Mopux\Data\User;
use Mopux\Data\UserBrief;
use Mopux\Enums\ProjectStatus;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Enums\TouchStatus;
use Mopux\Exceptions\InvalidPayloadException;

dataset('round trips', [
    'task detail' => [Task::class, 'task_detail'],
    'task summary' => [Task::class, 'task_summary'],
    'task board' => [Task::class, 'task_board'],
    'project board' => [ProjectBoard::class, 'project_board'],
    'project detail' => [Project::class, 'project_detail'],
    'project summary' => [Project::class, 'project_summary'],
    'project created' => [Project::class, 'project_created'],
    'project statistics' => [ProjectStatistics::class, 'project_statistics'],
    'activity' => [ProjectActivity::class, 'activity'],
    'activity without changes' => [ProjectActivity::class, 'activity_empty_changes'],
    'comment' => [Comment::class, 'comment'],
    'reply' => [Comment::class, 'comment_reply'],
    'notification' => [Notification::class, 'notification'],
    'touch' => [Touch::class, 'touch'],
    'team invitation' => [TeamInvitation::class, 'team_invitation'],
    'move options' => [MoveOptions::class, 'move_options'],
    'markdown help' => [MarkdownHelp::class, 'markdown_help'],
    'user' => [User::class, 'user'],
    'auth token' => [AuthToken::class, 'auth_token'],
    'attachment' => [Attachment::class, 'attachment'],
    'team member' => [TeamMember::class, 'team_member'],
    'user brief' => [UserBrief::class, 'user_brief'],
    'api token' => [ApiToken::class, 'api_token'],
]);

it('serializes back to an equivalent object', function (string $class, string $fixture): void {
    /** @var class-string<DataObject> $class */
    $object = $class::fromArray(apiFixture($fixture));
    $again = $class::fromArray($object->toArray());

    expect($again)->toEqual($object)
        ->and($again->toArray())->toBe($object->toArray());
})->with('round trips');

it('ignores unknown fields', function (string $class, string $fixture): void {
    /** @var class-string<DataObject> $class */
    expect($class::fromArray(apiFixture($fixture) + ['field_added_later' => ['x' => 1]]))->toEqual($class::fromArray(apiFixture($fixture)));
})->with('round trips');

it('hydrates a task detail', function (): void {
    $task = Task::fromArray(apiFixture('task_detail'));

    expect($task->id)->toBe(101)
        ->and($task->title)->toBe('Diseñar la API')
        ->and($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->dateStart?->format('Y-m-d'))->toBe('2026-11-01')
        ->and($task->hoursExpected)->toBe('04:30:00')
        ->and($task->project?->title)->toBe('Web corporativa')
        ->and($task->group?->id)->toBe(7)
        ->and($task->assigneeIds())->toBe([3, 5])
        ->and($task->assignees[0]->avatar)->toBe('https://acme.mopux.com/media/ada.png')
        ->and($task->tags)->toBe(['api', 'backend'])
        ->and($task->createdAt?->format('Y-m-d H:i:s'))->toBe('2026-10-30 09:00:00')
        ->and($task->isDone())->toBeFalse();
});

it('distinguishes the task shapes', function (): void {
    $summary = Task::fromArray(apiFixture('task_summary'));
    $board = Task::fromArray(apiFixture('task_board'));

    expect($summary->tags)->toBeNull()
        ->and($summary->status)->toBe(TaskStatus::Delayed)
        ->and($summary->dateStart)->toBeNull()
        ->and($board->project)->toBeNull()
        ->and($board->groupPosition)->toBe(1)
        ->and($board->priority)->toBe(TaskPriority::Maximum)
        ->and($board->assignees)->toBe([]);
});

it('hydrates a project board', function (): void {
    $board = ProjectBoard::fromArray(apiFixture('project_board'));

    expect($board->status)->toBe(ProjectStatus::InProgress)
        ->and($board->taskGroups)->toHaveCount(2)
        ->and($board->taskGroups[0])->toBeInstanceOf(TaskGroup::class)
        ->and($board->taskGroups[0]->archived)->toBeFalse()
        ->and($board->taskGroups[1]->description)->toBe('Cerradas')
        ->and($board->tasks())->toHaveCount(1)
        ->and($board->tasks()[0]->id)->toBe(103)
        ->and($board->group(8)?->title)->toBe('Hecho')
        ->and($board->group(99))->toBeNull()
        ->and($board->users)->toHaveCount(2);
});

it('hydrates the project shapes', function (): void {
    $detail = Project::fromArray(apiFixture('project_detail'));
    $summary = Project::fromArray(apiFixture('project_summary'));
    $created = Project::fromArray(apiFixture('project_created'));

    expect($detail->owner?->name)->toBe('Ada Lovelace')
        ->and($detail->ownerId)->toBe(3)
        ->and($detail->users)->toHaveCount(2)
        ->and($detail->configuration)->toBe(['color' => '#ff0000'])
        ->and($detail->progress())->toBe(25)
        ->and($detail->dateEnds?->format('Y-m-d'))->toBe('2026-12-31')
        ->and($summary->owner)->toBeNull()
        ->and($summary->users)->toBeNull()
        ->and($summary->status)->toBe(ProjectStatus::Delayed)
        ->and($summary->progress())->toBe(0)
        ->and($created->ownerId)->toBe(3)
        ->and($created->dateStarts?->format('Y-m-d'))->toBe('2026-11-20')
        ->and($created->progress())->toBeNull();
});

it('hydrates project statistics', function (): void {
    $statistics = ProjectStatistics::fromArray(apiFixture('project_statistics'));

    expect($statistics->progress)->toBe(25)
        ->and($statistics->groups[0]->counts->inProgress)->toBe(5)
        ->and($statistics->groups[0]->counts->review)->toBe(3)
        ->and($statistics->groups[0]->counts->delayed)->toBe(2)
        ->and($statistics->people[0]->user->id)->toBe(3)
        ->and($statistics->people[0]->pending)->toBe(9)
        ->and($statistics->unassigned->total)->toBe(4)
        ->and($statistics->unassigned->pending)->toBe(3)
        ->and($statistics->withoutTasks)->toBe(1)
        ->and($statistics->multipleAssignees)->toBeTrue();
});

it('hydrates activity entries', function (): void {
    $entry = ProjectActivity::fromArray(apiFixture('activity'));
    $empty = ProjectActivity::fromArray(apiFixture('activity_empty_changes'));

    expect($entry->changes)->toBe(['old_status' => 0, 'status' => 1])
        ->and($entry->change('status'))->toBe(1)
        ->and($entry->actor?->name)->toBe('Ada Lovelace')
        ->and($entry->group)->toBeNull()
        ->and($empty->changes)->toBe([])
        ->and($empty->actor)->toBeNull()
        ->and($empty->task?->id)->toBeNull()
        ->and($empty->task?->title)->toBe('Tarea borrada');
});

it('hydrates comments and replies', function (): void {
    $comment = Comment::fromArray(apiFixture('comment'));
    $reply = Comment::fromArray(apiFixture('comment_reply'));

    expect($comment->isReply())->toBeFalse()
        ->and($comment->mentions[0]->userId)->toBe(5)
        ->and($comment->mentions[0]->user?->name)->toBe('Bob')
        ->and($comment->medias[0])->toBeInstanceOf(Attachment::class)
        ->and($comment->medias[0]->size)->toBeNull()
        ->and($comment->repliesCount)->toBe(1)
        ->and($reply->isReply())->toBeTrue()
        ->and($reply->parent?->comment)->toBe('Hola @Bob')
        ->and($reply->repliesCount)->toBeNull();
});

it('hydrates notifications, touches, team members, invitations and attachments', function (): void {
    $notification = Notification::fromArray(apiFixture('notification'));
    $touch = Touch::fromArray(apiFixture('touch'));
    $member = TeamMember::fromArray(apiFixture('team')['members'][0]);
    $invitation = TeamInvitation::fromArray(apiFixture('team_invitation'));
    $attachment = Attachment::fromArray(apiFixture('task_attachments')['attachments'][0]);

    expect($notification->id)->toBe('9b2f6a3e-1c1d-4e8f-9f0a-6f1d2c3b4a5e')
        ->and($notification->isRead)->toBeFalse()
        ->and($notification->data['task_id'])->toBe(101)
        ->and($touch->status)->toBe(TouchStatus::Pending)
        ->and($touch->isAnswered())->toBeFalse()
        ->and($touch->recipient?->name)->toBe('Bob')
        ->and($member->roles)->toBe(['admin'])
        ->and($member->isActive)->toBeTrue()
        ->and($invitation->accepted)->toBeFalse()
        ->and($invitation->role)->toBe(2)
        ->and($invitation->invitedByUser?->email)->toBe('ada@acme.test')
        ->and($attachment->size)->toBe(20480)
        ->and($attachment->commentIds)->toBe([55])
        ->and($attachment->direct)->toBeFalse();
});

it('hydrates move options and helpers', function (): void {
    $options = MoveOptions::fromArray(apiFixture('move_options'));
    $help = MarkdownHelp::fromArray(apiFixture('markdown_help'));
    $counts = StatusCounts::fromArray(apiFixture('group_status_summary')['summary']);

    expect($options->currentGroupId)->toBe(7)
        ->and($options->projects[0]->groups[0]->position)->toBe(1)
        ->and($options->projects[0]->warnings[0]->code)->toBe('task_user_not_in_project')
        ->and($help->examples[0]['output'])->toBe('esto es <b>negrita</b>')
        ->and($counts->total)->toBe(28)
        ->and($counts->delayed)->toBeNull();
});

it('maps embedded empty users to null', function (): void {
    expect(UserBrief::fromNullable(['id' => null, 'name' => null]))->toBeNull()
        ->and(UserBrief::fromNullable(null))->toBeNull()
        ->and(UserBrief::fromNullable(['id' => 1])?->id)->toBe(1);
});

it('rejects unknown enum values and missing required fields', function (): void {
    expect(fn () => Task::fromArray(['status' => 7] + apiFixture('task_detail')))
        ->toThrow(InvalidPayloadException::class, '"task.status" should be a Mopux\Enums\TaskStatus value, int given')
        ->and(fn () => Task::fromArray(array_diff_key(apiFixture('task_detail'), ['title' => true])))
        ->toThrow(InvalidPayloadException::class, '"task.title" should be a string');
});
