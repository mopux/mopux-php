<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Mopux\Data\GroupStatusSummary;
use Mopux\Data\Input\TaskGroupCreate;
use Mopux\Data\Input\Validate;
use Mopux\Data\Position;
use Mopux\Data\Project;
use Mopux\Data\TaskGroup;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Support\Payload;

/**
 * Task groups (columns) of a project (`/api/v1/projects/{id}/task-groups`).
 */
final class TaskGroups extends Resource
{
    /**
     * Creates a group, optionally with up to 4 tasks in the same atomic operation.
     * The returned group includes the created tasks.
     */
    public function create(int $projectId, TaskGroupCreate|string $group): TaskGroup
    {
        $group = is_string($group) ? new TaskGroupCreate($group) : $group;
        $data = $this->connector->post(self::path($projectId), $group->toArray())->array();

        if (isset($data['group']) && is_array($data['group'])) {
            $data = $data['group'] + ['tasks' => $data['tasks'] ?? []];
        }

        return TaskGroup::fromArray($data);
    }

    /**
     * Sets the order of the project's groups (first id goes first).
     *
     * @param  list<int>  $groupIds
     * @return list<Position>
     */
    public function reorder(int $projectId, array $groupIds): array
    {
        $response = $this->connector->patch(self::path($projectId, 'reorder'), ['groups' => self::order($groupIds, 'groups')]);

        return Payload::of($response->array())->list('task_groups', Position::fromArray(...));
    }

    /**
     * Sets the manual order of the tasks of a group (first id goes first).
     *
     * @param  list<int>  $taskIds
     * @return list<Position>
     */
    public function reorderTasks(int $projectId, int $groupId, array $taskIds): array
    {
        $response = $this->connector->patch(self::path($projectId, self::id($groupId).'/tasks/reorder'), ['tasks' => self::order($taskIds, 'tasks')]);

        return Payload::of($response->array())->list('tasks', Position::fromArray(...));
    }

    public function rename(int $projectId, int $groupId, string $title): TaskGroup
    {
        $response = $this->connector->patch(self::path($projectId, self::id($groupId).'/title'), ['title' => Validate::title($title)]);

        return TaskGroup::fromArray($response->array());
    }

    public function archive(int $projectId, int $groupId): TaskGroup
    {
        return TaskGroup::fromArray($this->connector->patch(self::path($projectId, self::id($groupId).'/archive'))->array());
    }

    public function unarchive(int $projectId, int $groupId): TaskGroup
    {
        return TaskGroup::fromArray($this->connector->patch(self::path($projectId, self::id($groupId).'/unarchive'))->array());
    }

    /**
     * Moves the group, with its tasks, to another project.
     */
    public function move(int $projectId, int $groupId, int $toProjectId): TaskGroup
    {
        $response = $this->connector->patch(self::path($projectId, self::id($groupId).'/move'), ['project_id' => Validate::id($toProjectId, 'project_id')]);

        return TaskGroup::fromArray($response->array());
    }

    /**
     * Copies the group, with its tasks, into a project (the same or another) under a new title.
     */
    public function copy(int $projectId, int $groupId, int $toProjectId, string $title): TaskGroup
    {
        $response = $this->connector->patch(self::path($projectId, self::id($groupId).'/copy'), [
            'project_id' => Validate::id($toProjectId, 'project_id'),
            'title' => Validate::title($title),
        ]);

        return TaskGroup::fromArray($response->array());
    }

    /**
     * Creates a new project from a copy of the group's tasks.
     */
    public function createProject(int $projectId, int $groupId, string $title, ?string $description = null): Project
    {
        $payload = ['title' => Validate::title($title)];

        if ($description !== null) {
            $payload['description'] = $description;
        }

        return Project::fromArray($this->connector->post(self::path($projectId, self::id($groupId).'/create-project'), $payload)->array());
    }

    public function delete(int $projectId, int $groupId): void
    {
        $this->connector->delete(self::path($projectId, (string) self::id($groupId)));
    }

    /**
     * The group with its tasks (all statuses), optionally filtered by title.
     */
    public function focus(int $projectId, int $groupId, ?string $search = null): TaskGroup
    {
        $data = $this->connector->get(self::path($projectId, self::id($groupId).'/focus'), ['search' => $search])->array();
        $payload = Payload::of($data);

        return TaskGroup::fromArray($payload->object('group')->all() + ['tasks' => $payload->array('tasks')]);
    }

    public function statusSummary(int $projectId, int $groupId): GroupStatusSummary
    {
        return GroupStatusSummary::fromArray($this->connector->get(self::path($projectId, self::id($groupId).'/status-summary'))->array());
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{value: int, order: int}>
     */
    private static function order(array $ids, string $field): array
    {
        $ids = Validate::ids($ids, $field);

        if ($ids === []) {
            throw new InvalidInputException($field, 'provide at least one id.');
        }

        return array_map(static fn (int $id, int $order): array => ['value' => $id, 'order' => $order], $ids, array_keys($ids));
    }

    private static function id(int $groupId): int
    {
        return Validate::id($groupId, 'task_group_id');
    }

    private static function path(int $projectId, string $suffix = ''): string
    {
        return 'projects/'.Validate::id($projectId, 'project_id').'/task-groups'.($suffix === '' ? '' : '/'.$suffix);
    }
}
