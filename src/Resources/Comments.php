<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Generator;
use Mopux\Data\Comment;
use Mopux\Data\Input\CommentCreate;
use Mopux\Data\Input\Upload;
use Mopux\Data\Input\Validate;
use Mopux\Data\Paginated;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Http\Multipart;

/**
 * Task comments (`/api/v1/tasks/{id}/comments` and `/api/v1/comments/{id}`).
 */
final class Comments extends Resource
{
    /**
     * Comments of a task, newest first.
     *
     * The API returns each page without pagination metadata, so `total` and
     * `lastPage` are null and there are more pages while a page comes back full.
     *
     * @return Paginated<Comment>
     */
    public function list(int $taskId, int $page = 1, int $perPage = 20): Paginated
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidInputException('per_page', 'must be between 1 and 100.');
        }

        $response = $this->connector->get(self::taskPath($taskId), ['page' => $page, 'per_page' => $perPage]);

        return Paginated::fromList($response->array(), Comment::fromArray(...), $page, $perPage);
    }

    /**
     * Every comment of a task, fetched page by page as the generator advances.
     *
     * @return Generator<int, Comment>
     */
    public function all(int $taskId, int $perPage = 100): Generator
    {
        return Paginated::iterate(fn (int $page): Paginated => $this->list($taskId, $page, $perPage));
    }

    /**
     * Publishes a comment (Markdown). With files, the request is sent as multipart
     * and the files are uploaded to the task and attached to the comment.
     *
     * @param  list<Upload>  $files
     */
    public function create(int $taskId, CommentCreate|string $comment, array $files = []): Comment
    {
        $comment = is_string($comment) ? new CommentCreate($comment) : $comment;

        if (trim((string) $comment->comment) === '' && $comment->mentions === [] && $comment->mediaIds === [] && $files === []) {
            throw new InvalidInputException('comment', 'a comment, files or mentions are required.');
        }

        if ($files === []) {
            return Comment::fromArray($this->connector->post(self::taskPath($taskId), $comment->toArray())->array());
        }

        $multipart = new Multipart;

        foreach ($comment->toArray() as $name => $value) {
            $multipart->field($name, $value);
        }

        foreach ($files as $file) {
            $multipart->file('files[]', $file);
        }

        $response = $this->connector->request(
            'POST',
            self::taskPath($taskId),
            body: $multipart->build($this->connector->streamFactory()),
            headers: ['Content-Type' => $multipart->contentType()],
        );

        return Comment::fromArray($response->array());
    }

    /**
     * Edits the text of a comment of the authenticated user.
     */
    public function update(int $commentId, string $comment): Comment
    {
        return Comment::fromArray($this->connector->patch(self::path($commentId), ['comment' => self::text($comment)])->array());
    }

    /**
     * Deletes a comment of the authenticated user.
     */
    public function delete(int $commentId): void
    {
        $this->connector->delete(self::path($commentId));
    }

    public function reply(int $commentId, string $comment): Comment
    {
        return Comment::fromArray($this->connector->post(self::path($commentId, 'reply'), ['comment' => self::text($comment)])->array());
    }

    private static function text(string $comment): string
    {
        if (trim($comment) === '') {
            throw new InvalidInputException('comment', 'cannot be empty.');
        }

        if (mb_strlen($comment) > CommentCreate::MAX_LENGTH) {
            throw new InvalidInputException('comment', sprintf('must be at most %d characters.', CommentCreate::MAX_LENGTH));
        }

        return $comment;
    }

    private static function taskPath(int $taskId): string
    {
        return 'tasks/'.Validate::id($taskId, 'task_id').'/comments';
    }

    private static function path(int $commentId, string $suffix = ''): string
    {
        return 'comments/'.Validate::id($commentId, 'comment_id').($suffix === '' ? '' : '/'.$suffix);
    }
}
