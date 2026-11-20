<?php

declare(strict_types=1);

use Mopux\Data\Input\ProjectCreate;
use Mopux\Data\Input\ProjectUpdate;
use Mopux\Data\Project;
use Mopux\Data\ProjectBoard;
use Mopux\Data\ProjectStatistics;
use Mopux\Enums\ActivityFilter;
use Mopux\Enums\ProjectStatus;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

it('lists projects', function (): void {
    $client = new MockClient(['GET projects' => okFixture('projects_page')]);

    $page = mopux($client)->projects()->list('web', page: 2, perPage: 10);

    expect($client->lastRecorded()->query)->toBe(['q' => 'web', 'page' => '2', 'per_page' => '10'])
        ->and($page->items[0])->toBeInstanceOf(Project::class)
        ->and($page->lastPage)->toBe(3);
});

it('iterates every project lazily', function (): void {
    $page = fn (int $n, int $last) => MockResponse::ok(['data' => [['id' => $n, 'title' => "P{$n}"]], 'links' => [], 'meta' => ['current_page' => $n, 'last_page' => $last]]);
    $client = new MockClient(['GET projects' => [$page(1, 2), $page(2, 2)]]);

    $titles = array_map(fn (Project $project) => $project->title, iterator_to_array(mopux($client)->projects()->all(perPage: 1), false));

    expect($titles)->toBe(['P1', 'P2']);
    $client->assertSentCount(2);
    expect($client->recorded()[1]->query)->toBe(['page' => '2', 'per_page' => '1']);
});

it('validates per page', function (): void {
    expect(fn () => mopux(new MockClient)->projects()->list(perPage: 101))->toThrow(InvalidInputException::class, 'per_page');
});

it('creates, shows, updates and deletes projects', function (): void {
    $client = new MockClient([
        'POST projects' => okFixture('project_created', 'Project created successfully.'),
        'GET projects/12' => okFixture('project_detail'),
        'PATCH projects/12' => okFixture('project_detail'),
        'DELETE projects/12' => MockResponse::ok([]),
    ]);
    $projects = mopux($client)->projects();

    $created = $projects->create(new ProjectCreate('Nuevo', dateStarts: '2026-11-20'));
    $shown = $projects->show(12);
    $updated = $projects->update(12, new ProjectUpdate(status: ProjectStatus::Done));
    $projects->delete(12, 'web-corporativa');

    $recorded = $client->recorded();
    expect($created->id)->toBe(14)
        ->and($recorded[0]->json)->toBe(['title' => 'Nuevo', 'date_starts' => '2026-11-20'])
        ->and($shown->slug)->toBe('web-corporativa')
        ->and($recorded[2]->json)->toBe(['status' => 1])
        ->and($updated)->toBeInstanceOf(Project::class)
        ->and($recorded[3]->method)->toBe('DELETE')
        ->and($recorded[3]->query)->toBe(['confirm' => 'web-corporativa']);
});

it('gets the board with filters', function (): void {
    $client = new MockClient(['GET projects/12/board' => okFixture('project_board')]);

    $board = mopux($client)->projects()->board(12, 'api', includeArchived: true, users: [3], statuses: [TaskStatus::Pending, 1], priorities: [TaskPriority::Maximum]);

    expect($board)->toBeInstanceOf(ProjectBoard::class)
        ->and($client->lastRecorded()->query)->toBe([
            'archived' => '1',
            'search' => 'api',
            'params' => ['users' => ['3'], 'status' => ['0', '1'], 'priority' => ['9']],
        ]);
});

it('excludes archived groups from the board by default', function (): void {
    $client = new MockClient([okFixture('project_board')]);

    mopux($client)->projects()->board(12);

    expect($client->lastRecorded()->query)->toBe(['archived' => '0']);
});

it('lists the activity with filters', function (): void {
    $client = new MockClient(['GET projects/12/activity' => okFixture('activity_page')]);

    $page = mopux($client)->projects()->activity(12, ActivityFilter::Comments, userId: 3, from: '2026-11-01', to: new DateTimeImmutable('2026-11-20'), perPage: 50);

    expect($client->lastRecorded()->query)->toBe(['filter' => 'comments', 'user_id' => '3', 'from' => '2026-11-01', 'to' => '2026-11-20', 'page' => '1', 'per_page' => '50'])
        ->and($page->total)->toBe(8)
        ->and(fn () => mopux($client)->projects()->activity(12, from: '2026-11-20', to: '2026-11-01'))->toThrow(InvalidInputException::class, 'cannot be before from');
});

it('gets the summary', function (): void {
    $client = new MockClient(['GET projects/12/summary' => okFixture('project_statistics')]);

    expect(mopux($client)->projects()->summary(12))->toBeInstanceOf(ProjectStatistics::class);
});

it('manages members', function (): void {
    $members = MockResponse::ok(['members' => [['id' => 3, 'name' => 'Ada', 'avatar' => null], ['id' => 5, 'name' => 'Bob', 'avatar' => null]]]);
    $client = new MockClient(['GET projects/12/members' => $members, 'POST projects/12/members' => $members, 'DELETE projects/12/members/5' => MockResponse::ok(['members' => [['id' => 3, 'name' => 'Ada', 'avatar' => null]]])]);
    $projects = mopux($client)->projects();

    expect($projects->members(12))->toHaveCount(2)
        ->and($projects->addMember(12, 5)[1]->name)->toBe('Bob')
        ->and($client->lastRecorded()->json)->toBe(['user_id' => 5])
        ->and($projects->removeMember(12, 5))->toHaveCount(1);
});

it('rejects invalid ids before sending', function (): void {
    $client = new MockClient;

    expect(fn () => mopux($client)->projects()->show(0))->toThrow(InvalidInputException::class, 'project_id');
    $client->assertNothingSent();
});
