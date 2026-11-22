# Mopux PHP

Official PHP client for the [Mopux](https://www.mopux.com) API (`/api/v1`), with first-class Laravel integration.

- Typed, immutable data objects for every response and enums for statuses and priorities.
- Every API operation covered (69/69), checked against the OpenAPI spec in CI.
- Works with any PSR-18 HTTP client (Guzzle is used automatically when installed).
- Safe by default: retries rate-limited requests and failed reads, **never** retries a write whose outcome is unknown.
- Testing helpers: `MockClient` for plain PHP and `Mopux::fake()` for Laravel.

## Requirements

- PHP 8.2+
- A PSR-18 client and PSR-17 factories (`guzzlehttp/guzzle` covers both)
- Laravel 12 or 13 (optional). Only Laravel versions that still receive security fixes are supported; installing on older ones is blocked.

## Installation

```bash
composer require mopux/mopux-php
```

If your project has no PSR-18 client yet:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Mopux\Mopux;
use Mopux\Data\Input\TaskCreate;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;

$mopux = new Mopux('https://acme.mopux.com', $token);

$board = $mopux->projects()->board(12);

foreach ($board->taskGroups as $group) {
    echo $group->title, ': ', count($group->tasks), " open tasks\n";
}

$task = $mopux->tasks()->create(new TaskCreate(
    projectId: 12,
    groupId: 7,
    title: 'Write the release notes',
    priority: TaskPriority::High,
    hoursExpected: '2:30',
    assignees: [3, 5],
));

$mopux->tasks()->updateStatus($task->id, TaskStatus::Done);
```

## Authentication

The base URL is your Mopux installation (each installation has its own domain). The client adds `/api/v1/` itself.

**Personal access token.** Exchange credentials once and store the token (the API only shows it once):

```php
$mopux = Mopux::login('https://acme.mopux.com', $email, $password, tokenName: 'my-app');

$token = $mopux->config()->token();   // store it securely
$user = $mopux->lastLogin()?->user;
```

Later:

```php
$mopux = new Mopux('https://acme.mopux.com', $token);
$mopux->auth()->user();     // the token's user
$mopux->auth()->logout();   // revokes the token
```

The user needs the "API use" permission; otherwise the API answers `403`.

**OAuth.** When the installation enables OAuth, pass the access token the same way. Reads need the `mopux:read` scope and writes `mopux:write`. To refresh tokens automatically, use a `CallbackToken`: after a `401` the client calls `refresh` once and repeats the request.

```php
use Mopux\Http\Auth\CallbackToken;

$mopux = new Mopux('https://acme.mopux.com', new CallbackToken(
    resolve: fn (): string => $store->accessToken(),
    refresh: fn (): bool => $store->refresh(),   // true when a new token is available
));
```

Tokens created for the MCP connector are rejected by the REST API.

**Several installations.** Clients are immutable; derive one per tenant:

```php
$beta = $mopux->forTenant('https://beta.mopux.com', $betaToken);
```

## Resources

| Accessor | Operations |
| --- | --- |
| `auth()` | `login`, `logout`, `user` |
| `projects()` | `list`, `all`, `create`, `show`, `update`, `delete`, `board`, `activity`, `summary`, `members`, `addMember`, `removeMember` |
| `taskGroups()` | `create`, `reorder`, `reorderTasks`, `rename`, `archive`, `unarchive`, `move`, `copy`, `createProject`, `delete`, `focus`, `statusSummary` |
| `tasks()` | `create`, `update`, `updateBatch`, `my`, `show`, `delete`, `updateStatus`, `updatePriority`, `assign`, `move`, `updateTitle`, `updateDescription`, `updateStart`, `updateEnd`, `updateHours`, `sendTouch`, `focus`, `pinFocus`, `unpinFocus`, `moveOptions`, `attachments`, `uploadFile`, `deleteFile`, `markdownHelp` |
| `comments()` | `list`, `all`, `create`, `update`, `delete`, `reply` |
| `notifications()` | `list`, `all`, `markRead`, `markUnread`, `markAllRead`, `unreadCount`, `delete`, `deleteAll` |
| `touches()` | `list`, `reply` |
| `team()` | `list`, `all` |
| `teamInvitations()` | `list`, `create`, `resend`, `delete` |

Responses are data objects in `Mopux\Data` (`Task`, `Project`, `ProjectBoard`, `Comment`...). Each one has `toArray()` returning the API's snake_case shape.

### Updating tasks

Only the fields you pass are sent. `null` **clears** a value (description, dates, hours); leaving a field out keeps it.

```php
use Mopux\Data\Input\AssigneeMutation;
use Mopux\Data\Input\TaskUpdate;

$mopux->tasks()->update($taskId, new TaskUpdate(
    status: TaskStatus::InProgress,
    hoursInverted: 45,                     // minutes, 'H:MM' or 'HH:MM'
    dateEnd: null,                         // clears the due date
    assignees: AssigneeMutation::add([7]), // or replace([...]), remove([...]), none()
));
```

Up to four tasks can be changed atomically (all or nothing) with `updateBatch()`:

```php
use Mopux\Data\Input\TaskBatchItem;
use Mopux\Data\Input\TaskBatchUpdate;

$mopux->tasks()->updateBatch(new TaskBatchUpdate(
    new TaskBatchItem(101, new TaskUpdate(status: TaskStatus::Done)),
    new TaskBatchItem(102, taskGroupId: 8),                              // move to another group
    new TaskBatchItem(103, taskGroupId: 20, destinationProjectId: 13),   // move to another project
));
```

Inputs are validated before sending (titles, ids, dates, hours, tag limits, the read-only `Delayed` status) and throw `InvalidInputException`.

### Comments and documents

```php
use Mopux\Data\Input\CommentCreate;
use Mopux\Data\Input\Upload;

$mopux->comments()->create($taskId, new CommentCreate('Done, see @Ada', mentions: [3]));

$mopux->comments()->create($taskId, 'Report attached', [
    Upload::fromPath('/path/report.pdf'),
    Upload::fromString($csv, 'data.csv', 'text/csv'),
]);

$attachment = $mopux->tasks()->uploadFile($taskId, Upload::fromPath('/path/spec.pdf'));
```

File bodies are streamed, so large files are not loaded in memory. Download URLs of attachments expire after 15 minutes.

### Pagination

List methods return `Paginated<T>` with `items`, `currentPage`, `lastPage`, `total`, `hasMorePages()` and `nextPage()`. `all()` walks every page lazily, fetching the next one only when needed:

```php
foreach ($mopux->projects()->all() as $project) {
    // ...
}
```

Comment and notification pages come without totals from the API: `total` and `lastPage` are `null` and there are more pages while a page comes back full.

### Performance

Prefer the aggregated endpoints to fetching objects one by one: `projects()->board()` returns every group with its open tasks in one request, `projects()->summary()` the progress and workload, and `tasks()->my()` the user's tasks across projects.

## Errors

Every exception extends `Mopux\Exceptions\MopuxException`.

| Exception | When |
| --- | --- |
| `AuthenticationException` | 401: missing, invalid or revoked token |
| `AuthorizationException` | 403: no permission, missing OAuth scope, API access disabled or billing restriction |
| `NotFoundException` | 404: the resource (or the tenant) does not exist or is not visible |
| `ValidationException` | 422: invalid data; `errors()` and `firstError($field)` give the messages |
| `RateLimitException` | 429 after the retries; `retryAfter` holds the seconds to wait |
| `ClientException` | other 4xx |
| `ServerException` | 5xx on a read |
| `UncertainWriteException` | 5xx on a write: the change **may have been applied** |
| `ConnectionException` | network failure; `isWriteOutcomeUncertain()` is true for writes |
| `InvalidInputException` | rejected before sending |
| `MalformedResponseException`, `InvalidPayloadException` | unexpected response shape |

Every `ApiException` (the HTTP ones above) also gives `error()` (`data.error`) and `errorCode()`, the stable machine-readable `data.code` when the API sets one. Branch on the code, never on the message, and treat an unknown code as a generic error of its status. Helpers for the current codes:

- `isPlanRestriction()`: the contracted plan does not include the feature; `planFeature()` names it (for example `api`). Retrying will not help until the plan changes.
- `isBillingBlocked()`: the installation is blocked by billing or its public trial has ended.

```php
use Mopux\Exceptions\UncertainWriteException;
use Mopux\Exceptions\ValidationException;

try {
    $mopux->tasks()->create($input);
} catch (ValidationException $e) {
    $e->errors();          // ['title' => ['The title field is required.']]
} catch (UncertainWriteException $e) {
    // Do not create the task again blindly: check whether it exists first.
}
```

## Retries

Retries are enabled by default (2 retries, exponential backoff with jitter):

- `429` is retried for every method, honouring `Retry-After` (up to 60 seconds): the API throttles before running the request.
- Network errors and `5xx` are retried for reads only.
- Writes are **never** retried after a network error or a `5xx`; task creation is not idempotent.

```php
use Mopux\Config;
use Mopux\Http\RetryPolicy;

$mopux = new Mopux(new Config('https://acme.mopux.com', $token, retry: new RetryPolicy(maxRetries: 4)));
$mopux = new Mopux(new Config('https://acme.mopux.com', $token, retry: RetryPolicy::none()));
```

## Configuration

```php
$config = new Config(
    baseUrl: 'https://acme.mopux.com',
    token: $token,
    timeout: 30,          // seconds, applied to the default Guzzle client
    connectTimeout: 10,
    userAgent: 'my-app/1.0',
);

$mopux = new Mopux($config, httpClient: $anyPsr18Client);
```

## Laravel

The service provider and the `Mopux` facade are registered automatically. Configure the installation in `.env`:

```dotenv
MOPUX_URL=https://acme.mopux.com
MOPUX_TOKEN=12|your-token
```

Publish the configuration to change timeouts or retries:

```bash
php artisan vendor:publish --tag=mopux-config
```

Use the facade or inject the client:

```php
use Mopux\Laravel\Facades\Mopux;

$tasks = Mopux::tasks()->my();

class SyncTasks
{
    public function __construct(private \Mopux\Mopux $mopux) {}
}

$tenant = Mopux::forTenant($installation->url, $installation->token);
```

## Testing

### Laravel

`Mopux::fake()` replaces the client (facade and injected instances) with one that answers from the given responses and records requests:

```php
use Mopux\Laravel\Facades\Mopux;
use Mopux\Testing\MockResponse;
use Mopux\Testing\RecordedRequest;

Mopux::fake([
    'GET tasks/my' => MockResponse::ok([/* tasks */]),
    'PATCH tasks/*/status' => MockResponse::ok($taskPayload),
    'POST tasks' => MockResponse::validation(['title' => 'The title field is required.']),
]);

// ... run the code under test ...

Mopux::assertSent('PATCH tasks/101/status');
Mopux::assertSent(fn (RecordedRequest $request) => $request->json['status'] === 3);
Mopux::assertNotSent('DELETE tasks/*');
```

### Plain PHP

```php
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;
use Mopux\Testing\NetworkError;

$client = new MockClient([
    'GET projects' => MockResponse::ok(['data' => [], 'links' => [], 'meta' => []]),
    'POST tasks' => [MockResponse::serverError(), MockResponse::ok($taskPayload)], // a sequence
    'GET team' => new NetworkError('Connection timed out'),
]);

$mopux = new Mopux('https://acme.mopux.com', 'token', $client);
// ...
$client->assertSent('GET projects', times: 1);
```

`MockResponse` has helpers for the API envelope: `ok()`, `error()` (with an optional `data.code`), `validation()`, `unauthenticated()`, `forbidden()`, `planRestricted()`, `notFound()`, `tooManyRequests()` and `serverError()`.

## Development

```bash
composer install
composer test              # unit, feature, Laravel and contract suites
composer analyse           # PHPStan (level max)
composer lint              # Pint (check only)
composer check             # all of the above
composer test:contract     # every operation of resources/openapi.yaml must map to an SDK call
composer sync-spec         # refresh resources/openapi.yaml (or: composer sync-spec -- /path/to/openapi-v1-public.yaml)
composer test:integration  # end-to-end against a real tenant, see tests/Integration/.env.example
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Security

See [SECURITY.md](SECURITY.md) to report vulnerabilities.

## License

MIT. See [LICENSE](LICENSE).
