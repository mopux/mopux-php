<?php

declare(strict_types=1);

namespace Mopux\Resources;

use DateTimeInterface;
use Mopux\Data\Attachment;
use Mopux\Data\FocusList;
use Mopux\Data\Input\Hours;
use Mopux\Data\Input\Missing;
use Mopux\Data\Input\TaskBatchUpdate;
use Mopux\Data\Input\TaskCreate;
use Mopux\Data\Input\TaskUpdate;
use Mopux\Data\Input\Upload;
use Mopux\Data\Input\Validate;
use Mopux\Data\MarkdownHelp;
use Mopux\Data\MovedTask;
use Mopux\Data\MoveOptions;
use Mopux\Data\Paginated;
use Mopux\Data\Task;
use Mopux\Data\Touch;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Exceptions\UncertainWriteException;
use Mopux\Exceptions\ValidationException;
use Mopux\Http\Multipart;
use Mopux\Support\Payload;

/**
 * Tasks (`/api/v1/tasks`).
 *
 * Writes are never retried automatically after a 5xx or a network error: they
 * throw UncertainWriteException / ConnectionException, and the caller should
 * refresh the state before trying again (task creation is not idempotent).
 */
final class Tasks extends Resource
{
    /**
     * Creates a task with all its fields in one atomic operation.
     *
     * @throws ValidationException Invalid fields; nothing was created.
     * @throws UncertainWriteException 5xx: the task may or may not exist.
     */
    public function create(TaskCreate $task): Task
    {
        return Task::fromArray($this->connector->post('tasks', $task->toArray())->array());
    }

    /**
     * Updates several fields at once, atomically. Null clears description, dates and hours.
     */
    public function update(int $taskId, TaskUpdate $changes): Task
    {
        return Task::fromArray($this->connector->patch(self::path($taskId), $changes->toArray())->array());
    }

    /**
     * Updates 1 to 4 tasks atomically (all or nothing). Returns the tasks in request order.
     *
     * @return list<Task>
     */
    public function updateBatch(TaskBatchUpdate $batch): array
    {
        return Payload::of($this->connector->patch('tasks/batch', $batch->toArray())->array())->list('tasks', Task::fromArray(...));
    }

    /**
     * Open tasks assigned to the user (plus recently completed ones), across projects.
     * The API returns them all at once, without pagination.
     *
     * @param  list<int>  $projectIds  Restrict to these projects.
     * @return list<Task>
     */
    public function my(?string $search = null, array $projectIds = []): array
    {
        $response = $this->connector->get('tasks/my', [
            'search' => $search,
            'params' => $projectIds === [] ? null : ['projects' => Validate::ids($projectIds, 'projects')],
        ]);

        return Payload::of(['tasks' => $response->array()])->list('tasks', Task::fromArray(...));
    }

    public function show(int $taskId): Task
    {
        return Task::fromArray($this->connector->get(self::path($taskId))->array());
    }

    public function delete(int $taskId): void
    {
        $this->connector->delete(self::path($taskId));
    }

    public function updateStatus(int $taskId, TaskStatus $status): Task
    {
        return $this->patchTask($taskId, 'status', ['status' => Validate::writableStatus($status)]);
    }

    public function updatePriority(int $taskId, TaskPriority $priority): Task
    {
        return $this->patchTask($taskId, 'priority', ['priority' => $priority->value]);
    }

    /**
     * Legacy single assignment: sets the primary assignee (null unassigns everybody).
     * Prefer update() with an AssigneeMutation to manage several assignees.
     */
    public function assign(int $taskId, ?int $userId): Task
    {
        return $this->patchTask($taskId, 'assign', ['assignee_id' => $userId === null ? null : Validate::id($userId, 'assignee_id')]);
    }

    /**
     * Moves the task to a group of the same or another project.
     */
    public function move(int $taskId, int $projectId, int $groupId): MovedTask
    {
        $response = $this->connector->patch(self::path($taskId, 'move'), [
            'project_id' => Validate::id($projectId, 'project_id'),
            'group_id' => Validate::id($groupId, 'group_id'),
        ]);

        return MovedTask::fromArray($response->array());
    }

    public function updateTitle(int $taskId, string $title): Task
    {
        return $this->patchTask($taskId, 'title', ['title' => Validate::title($title)]);
    }

    public function updateDescription(int $taskId, ?string $description): Task
    {
        return $this->patchTask($taskId, 'description', ['description' => $description]);
    }

    public function updateStart(int $taskId, DateTimeInterface|string|null $date): Task
    {
        return $this->patchTask($taskId, 'start', ['date_start' => $date === null ? null : Validate::date($date, 'date_start')]);
    }

    public function updateEnd(int $taskId, DateTimeInterface|string|null $date): Task
    {
        return $this->patchTask($taskId, 'end', ['date_end' => $date === null ? null : Validate::date($date, 'date_end')]);
    }

    /**
     * Updates expected and/or worked hours (`HH:MM`, `H:MM` or minutes). Omitted
     * values are kept; null clears them.
     */
    public function updateHours(
        int $taskId,
        string|int|null|Missing $expected = Missing::Value,
        string|int|null|Missing $inverted = Missing::Value,
    ): Task {
        $payload = [];

        foreach (['hours_expected' => $expected, 'hours_inverted' => $inverted] as $key => $hours) {
            if (! $hours instanceof Missing) {
                $payload[$key] = $hours === null ? null : Hours::normalize($hours, $key);
            }
        }

        if ($payload === []) {
            throw new InvalidInputException('hours', 'provide expected or inverted hours.');
        }

        return $this->patchTask($taskId, 'hours', $payload);
    }

    /**
     * Sends a touch (a nudge about this task) to a project member.
     */
    public function sendTouch(int $taskId, int $recipientId, ?string $message = null): Touch
    {
        $response = $this->connector->post(self::path($taskId, 'touches'), [
            'recipient_id' => Validate::id($recipientId, 'recipient_id'),
            'message' => $message,
        ]);

        return Touch::fromArray($response->array());
    }

    /**
     * The user's personal focus (pinned tasks).
     */
    public function focus(): FocusList
    {
        return FocusList::fromArray($this->connector->get('tasks/focus')->array());
    }

    /**
     * Pins a task to the focus; returns the updated focus. Fails (422) when it is full.
     */
    public function pinFocus(int $taskId): FocusList
    {
        return FocusList::fromArray($this->connector->post(self::path($taskId, 'focus'))->array());
    }

    public function unpinFocus(int $taskId): FocusList
    {
        return FocusList::fromArray($this->connector->delete(self::path($taskId, 'focus'))->array());
    }

    /**
     * Projects and groups the task can be moved to, with warnings.
     */
    public function moveOptions(int $taskId): MoveOptions
    {
        return MoveOptions::fromArray($this->connector->get(self::path($taskId, 'move-options'))->array());
    }

    /**
     * Documents of the task (direct and from comments). Download URLs expire after 15 minutes.
     *
     * @return Paginated<Attachment>
     */
    public function attachments(int $taskId, int $page = 1, int $perPage = 20): Paginated
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidInputException('per_page', 'must be between 1 and 100.');
        }

        $response = $this->connector->get(self::path($taskId, 'attachments'), ['page' => $page, 'per_page' => $perPage]);

        return Paginated::fromPageFields($response->array(), 'attachments', Attachment::fromArray(...), $perPage);
    }

    /**
     * Uploads a document to the task (multipart). The download URL in the result
     * expires after 15 minutes.
     */
    public function uploadFile(int $taskId, Upload $file): Attachment
    {
        $multipart = (new Multipart)->file('file', $file);

        $response = $this->connector->request(
            'POST',
            self::path($taskId, 'files'),
            body: $multipart->build($this->connector->streamFactory()),
            headers: ['Content-Type' => $multipart->contentType()],
        );

        return Attachment::fromArray($response->array());
    }

    /**
     * Deletes a document the authenticated user uploaded to the task.
     */
    public function deleteFile(int $taskId, int $mediaId): void
    {
        $this->connector->delete(self::path($taskId, 'files/'.Validate::id($mediaId, 'media_id')));
    }

    /**
     * Markdown supported in descriptions and comments.
     */
    public function markdownHelp(): MarkdownHelp
    {
        return MarkdownHelp::fromArray($this->connector->get('tasks/markdown-help')->array());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patchTask(int $taskId, string $action, array $payload): Task
    {
        return Task::fromArray($this->connector->patch(self::path($taskId, $action), $payload)->array());
    }

    private static function path(int $taskId, string $suffix = ''): string
    {
        return 'tasks/'.Validate::id($taskId, 'task_id').($suffix === '' ? '' : '/'.$suffix);
    }
}
