<?php

declare(strict_types=1);

use Mopux\Config;
use Mopux\Data\Attachment;
use Mopux\Data\Comment;
use Mopux\Data\Input\CommentCreate;
use Mopux\Data\Input\Upload;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Http\RetryPolicy;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

it('lists comments page by page', function (): void {
    $client = new MockClient(['GET tasks/101/comments' => okFixture('comments_list')]);

    $page = mopux($client)->comments()->list(101, page: 2, perPage: 2);

    expect($client->lastRecorded()->query)->toBe(['page' => '2', 'per_page' => '2'])
        ->and($page->items[0])->toBeInstanceOf(Comment::class)
        ->and($page->total)->toBeNull()
        ->and($page->nextPage())->toBe(3);
});

it('iterates all comments until a partial page', function (): void {
    $client = new MockClient(['GET tasks/101/comments' => [okFixture('comments_list'), MockResponse::ok([apiFixture('comment')])]]);

    $comments = iterator_to_array(mopux($client)->comments()->all(101, perPage: 2), false);

    expect($comments)->toHaveCount(3);
    $client->assertSentCount(2);
});

it('accepts the Laravel pagination of API 1.2 for comments', function (): void {
    $laravel = static fn (array $items, int $page, int $lastPage): MockResponse => MockResponse::ok([
        'data' => $items,
        'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        'meta' => ['current_page' => $page, 'last_page' => $lastPage, 'per_page' => 1, 'total' => 2, 'from' => $page, 'to' => $page],
    ]);
    $client = new MockClient(['GET tasks/101/comments' => [$laravel([apiFixture('comment')], 1, 2), $laravel([apiFixture('comment_reply')], 2, 2)]]);

    $first = mopux($client)->comments()->list(101, perPage: 1);
    $all = iterator_to_array(mopux(new MockClient(['GET tasks/101/comments' => [$laravel([apiFixture('comment')], 1, 2), $laravel([apiFixture('comment_reply')], 2, 2)]]))->comments()->all(101, perPage: 1), false);

    expect($first->items[0])->toBeInstanceOf(Comment::class)
        ->and($first->total)->toBe(2)
        ->and($first->lastPage)->toBe(2)
        ->and($first->hasMorePages())->toBeTrue()
        ->and($all)->toHaveCount(2);
});

it('creates a json comment with mentions', function (): void {
    $client = new MockClient(['POST tasks/101/comments' => okFixture('comment', 'Comment created successfully.')]);

    $comment = mopux($client)->comments()->create(101, new CommentCreate('Hola @Bob', mentions: [5], mediaIds: [77]));

    expect($client->lastRecorded()->header('Content-Type'))->toBe('application/json')
        ->and($client->lastRecorded()->json)->toBe(['comment' => 'Hola @Bob', 'mentions' => [5], 'media_ids' => [77]])
        ->and($comment->mentions[0]->userId)->toBe(5);
});

it('creates a comment from a string', function (): void {
    $client = new MockClient([okFixture('comment')]);

    mopux($client)->comments()->create(101, 'Visto');

    expect($client->lastRecorded()->json)->toBe(['comment' => 'Visto']);
});

it('creates a multipart comment with files', function (): void {
    $client = new MockClient(['POST tasks/101/comments' => okFixture('comment')]);

    mopux($client)->comments()->create(101, new CommentCreate('Adjunto', mentions: [5]), [
        Upload::fromString('%PDF', 'spec.pdf', 'application/pdf'),
        Upload::fromString('a,b', 'data.csv', 'text/csv'),
    ]);

    $request = $client->lastRecorded();
    preg_match('/boundary=(\S+)/', $request->header('Content-Type'), $match);
    $boundary = $match[1] ?? '';
    $body = $request->body();

    expect($request->header('Content-Type'))->toStartWith('multipart/form-data; boundary=mopux-')
        ->and($request->json)->toBeNull()
        ->and($body)->toContain("--{$boundary}\r\nContent-Disposition: form-data; name=\"comment\"\r\n\r\nAdjunto\r\n")
        ->and($body)->toContain("name=\"mentions[]\"\r\n\r\n5\r\n")
        ->and(substr_count($body, 'name="files[]"'))->toBe(2)
        ->and($body)->toContain("filename=\"spec.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF\r\n")
        ->and($body)->toEndWith("--{$boundary}--\r\n");
});

it('requires text, mentions or files', function (): void {
    $client = new MockClient;

    expect(fn () => mopux($client)->comments()->create(101, new CommentCreate('  ')))->toThrow(InvalidInputException::class, 'a comment, files or mentions are required');
    $client->assertNothingSent();
});

it('updates, replies to and deletes comments', function (): void {
    $client = new MockClient([
        'PATCH comments/55' => okFixture('comment'),
        'POST comments/55/reply' => okFixture('comment_reply'),
        'DELETE comments/55' => MockResponse::ok([]),
    ]);
    $comments = mopux($client)->comments();

    $comments->update(55, 'Editado');
    $updateBody = $client->lastRecorded()->json;
    $reply = $comments->reply(55, 'Visto');
    $comments->delete(55);

    expect($updateBody)->toBe(['comment' => 'Editado'])
        ->and($reply->parentId)->toBe(55)
        ->and(fn () => $comments->reply(55, ''))->toThrow(InvalidInputException::class);
    $client->assertSent('DELETE comments/55', 1);
});

it('uploads and deletes task files', function (): void {
    $client = new MockClient([
        'POST tasks/101/files' => MockResponse::ok(apiFixture('task_attachments')['attachments'][0], 'File uploaded successfully.'),
        'DELETE tasks/101/files/77' => MockResponse::ok([]),
    ]);
    $tasks = mopux($client)->tasks();

    $attachment = $tasks->uploadFile(101, Upload::fromString('%PDF', 'spec.pdf', 'application/pdf'));
    $upload = $client->lastRecorded();
    $tasks->deleteFile(101, 77);

    expect($attachment)->toBeInstanceOf(Attachment::class)
        ->and($attachment->size)->toBe(20480)
        ->and($upload->header('Content-Type'))->toStartWith('multipart/form-data; boundary=')
        ->and($upload->body())->toContain("name=\"file\"; filename=\"spec.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF\r\n");
    $client->assertSent('DELETE tasks/101/files/77', 1);
});

it('resends a multipart body after a rate limit', function (): void {
    $client = new MockClient([MockResponse::tooManyRequests(0), okFixture('comment')]);
    $mopux = new Mopux\Mopux(new Config('https://acme.mopux.com', '1|t', retry: new RetryPolicy(sleeper: fn () => null)), httpClient: $client);

    $mopux->comments()->create(101, 'Con fichero', [Upload::fromString('contents', 'a.txt', 'text/plain')]);

    $client->assertSentCount(2);
    expect($client->recorded()[1]->body())->toContain("\r\n\r\ncontents\r\n")
        ->and($client->recorded()[1]->body())->toBe($client->recorded()[0]->body());
});
