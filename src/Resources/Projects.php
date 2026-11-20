<?php

declare(strict_types=1);

namespace Mopux\Resources;

use DateTimeInterface;
use Generator;
use Mopux\Data\Input\ProjectCreate;
use Mopux\Data\Input\ProjectUpdate;
use Mopux\Data\Input\Validate;
use Mopux\Data\Paginated;
use Mopux\Data\Project;
use Mopux\Data\ProjectActivity;
use Mopux\Data\ProjectBoard;
use Mopux\Data\ProjectStatistics;
use Mopux\Data\UserBrief;
use Mopux\Enums\ActivityFilter;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Support\Payload;

/**
 * Projects (`/api/v1/projects`).
 */
final class Projects extends Resource
{
    /**
     * Projects the user belongs to, ordered by title.
     *
     * @param  string|null  $search  Matches title and description.
     * @return Paginated<Project>
     */
    public function list(?string $search = null, int $page = 1, int $perPage = 20): Paginated
    {
        $response = $this->connector->get('projects', [
            'q' => $search,
            'page' => $page,
            'per_page' => self::perPage($perPage),
        ]);

        return Paginated::fromLaravel($response->array(), Project::fromArray(...));
    }

    /**
     * Every project, fetched page by page as the generator advances.
     *
     * @return Generator<int, Project>
     */
    public function all(?string $search = null, int $perPage = 100): Generator
    {
        return Paginated::iterate(fn (int $page): Paginated => $this->list($search, $page, $perPage));
    }

    public function create(ProjectCreate $project): Project
    {
        return Project::fromArray($this->connector->post('projects', $project->toArray())->array());
    }

    public function show(int $projectId): Project
    {
        return Project::fromArray($this->connector->get(self::path($projectId))->array());
    }

    public function update(int $projectId, ProjectUpdate $changes): Project
    {
        return Project::fromArray($this->connector->patch(self::path($projectId), $changes->toArray())->array());
    }

    /**
     * Deletes a project. The API requires the project slug as confirmation
     * (see Project::$slug from show()).
     */
    public function delete(int $projectId, string $confirmSlug): void
    {
        $this->connector->delete(self::path($projectId), ['confirm' => $confirmSlug]);
    }

    /**
     * The whole board in one request: groups in order with their open tasks, and members.
     *
     * @param  bool  $includeArchived  Include archived groups (the API includes them by default; the SDK does not).
     * @param  list<int>  $users  Only tasks assigned to these users.
     * @param  list<TaskStatus|int>  $statuses
     * @param  list<TaskPriority|int>  $priorities
     */
    public function board(
        int $projectId,
        ?string $search = null,
        bool $includeArchived = false,
        array $users = [],
        array $statuses = [],
        array $priorities = [],
    ): ProjectBoard {
        $params = array_filter([
            'users' => $users,
            'status' => self::values($statuses),
            'priority' => self::values($priorities),
        ]);

        $response = $this->connector->get(self::path($projectId, 'board'), [
            'archived' => $includeArchived,
            'search' => $search,
            'params' => $params === [] ? null : $params,
        ]);

        return ProjectBoard::fromArray($response->array());
    }

    /**
     * Activity log of a project, newest first.
     *
     * @param  string|null  $type  Raw activity type (e.g. `update-task-status`).
     * @return Paginated<ProjectActivity>
     */
    public function activity(
        int $projectId,
        ?ActivityFilter $filter = null,
        ?string $type = null,
        ?int $userId = null,
        DateTimeInterface|string|null $from = null,
        DateTimeInterface|string|null $to = null,
        int $page = 1,
        int $perPage = 25,
    ): Paginated {
        $from = $from === null ? null : Validate::date($from, 'from');
        $to = $to === null ? null : Validate::date($to, 'to');

        if ($from !== null && $to !== null && $to < $from) {
            throw new InvalidInputException('to', 'cannot be before from.');
        }

        $response = $this->connector->get(self::path($projectId, 'activity'), [
            'filter' => $filter,
            'type' => $type,
            'user_id' => $userId,
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'per_page' => self::perPage($perPage),
        ]);

        return Paginated::fromLaravel($response->array(), ProjectActivity::fromArray(...));
    }

    /**
     * Progress per group and workload per member.
     */
    public function summary(int $projectId): ProjectStatistics
    {
        return ProjectStatistics::fromArray($this->connector->get(self::path($projectId, 'summary'))->array());
    }

    /**
     * @return list<UserBrief>
     */
    public function members(int $projectId): array
    {
        return self::memberList($this->connector->get(self::path($projectId, 'members'))->array());
    }

    /**
     * Adds a team member to the project and returns the updated member list.
     *
     * @return list<UserBrief>
     */
    public function addMember(int $projectId, int $userId): array
    {
        $response = $this->connector->post(self::path($projectId, 'members'), ['user_id' => Validate::id($userId, 'user_id')]);

        return self::memberList($response->array());
    }

    /**
     * Removes a member from the project and returns the updated member list.
     *
     * @return list<UserBrief>
     */
    public function removeMember(int $projectId, int $userId): array
    {
        return self::memberList($this->connector->delete(self::path($projectId, 'members/'.Validate::id($userId, 'user_id')))->array());
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<UserBrief>
     */
    private static function memberList(array $data): array
    {
        return Payload::of($data)->list('members', UserBrief::fromArray(...));
    }

    /**
     * @param  list<\BackedEnum|int>  $values
     * @return list<int|string>
     */
    private static function values(array $values): array
    {
        return array_map(static fn (\BackedEnum|int $value): int|string => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }

    private static function path(int $projectId, string $suffix = ''): string
    {
        return 'projects/'.Validate::id($projectId, 'project_id').($suffix === '' ? '' : '/'.$suffix);
    }

    private static function perPage(int $perPage): int
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidInputException('per_page', 'must be between 1 and 100.');
        }

        return $perPage;
    }
}
