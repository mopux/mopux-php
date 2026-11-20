<?php

declare(strict_types=1);

use Mopux\Data\Task;
use Mopux\Enums\TaskStatus;
use Mopux\Exceptions\RateLimitException;
use Mopux\Laravel\Facades\Mopux as MopuxFacade;
use Mopux\Laravel\Testing\MopuxFake;
use Mopux\Mopux;
use Mopux\Testing\MockResponse;
use Mopux\Testing\RecordedRequest;
use PHPUnit\Framework\AssertionFailedError;

final class CompleteTask
{
    public function __construct(private readonly Mopux $mopux) {}

    public function __invoke(int $taskId): Task
    {
        return $this->mopux->tasks()->updateStatus($taskId, TaskStatus::Done);
    }
}

it('fakes the facade and records requests', function (): void {
    $fake = MopuxFacade::fake(['GET tasks/my' => MockResponse::ok([apiFixture('task_summary')])]);

    $tasks = MopuxFacade::tasks()->my('api');

    expect($fake)->toBeInstanceOf(MopuxFake::class)
        ->and($tasks[0]->id)->toBe(102)
        ->and($fake->recorded()[0]->query)->toBe(['search' => 'api']);
    MopuxFacade::assertSent('GET tasks/my', 1);
    MopuxFacade::assertNotSent('POST tasks');
    MopuxFacade::assertSentCount(1);
});

it('fakes clients injected by type', function (): void {
    MopuxFacade::fake(['PATCH tasks/*/status' => okFixture('task_detail')]);

    $task = app(CompleteTask::class)(101);

    expect($task->id)->toBe(101);
    MopuxFacade::assertSent(fn (RecordedRequest $request) => $request->path === 'tasks/101/status' && $request->json === ['status' => 3]);
});

it('keeps the configured url and token in fakes', function (): void {
    $fake = MopuxFacade::fake([MockResponse::ok(['user' => ['id' => 1, 'name' => 'A', 'email' => 'a@a.test']])]);

    MopuxFacade::auth()->user();

    expect((string) $fake->client->lastRequest()->getUri())->toBe('https://acme.mopux.com/api/v1/auth/user')
        ->and($fake->client->lastRecorded()->header('Authorization'))->toBe('Bearer 1|laravel-token');
});

it('fakes tenant clients too', function (): void {
    $fake = MopuxFacade::fake([MockResponse::ok(['members' => [], 'page' => 1, 'last_page' => 1, 'total' => 0])]);

    MopuxFacade::forTenant('https://beta.mopux.com', '9|beta')->team()->list();

    expect((string) $fake->client->lastRequest()->getUri())->toStartWith('https://beta.mopux.com/api/v1/team');
});

it('fails assertions with a useful message', function (): void {
    MopuxFacade::fake();

    MopuxFacade::assertNothingSent();

    expect(fn () => MopuxFacade::assertSent('GET projects'))->toThrow(AssertionFailedError::class, 'Expected a request "GET projects" to be sent, none was');
});

it('does not retry in fakes', function (): void {
    MopuxFacade::fake([MockResponse::tooManyRequests(1), MockResponse::ok()]);

    expect(fn () => MopuxFacade::projects()->members(1))->toThrow(RateLimitException::class);
    MopuxFacade::assertSentCount(1);
});
