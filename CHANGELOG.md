# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Package scaffold: Composer metadata, Pest, PHPStan (level max), Pint and CI matrix.
- `Config` and `Http\Connector`: PSR-18 transport against `/api/v1/`, envelope unwrapping and query building.
- Exception hierarchy mapped from HTTP statuses (401, 403, 404, 422, 429, 4xx, 5xx) with `UncertainWriteException` for failed writes.
- `Http\RetryPolicy` (enabled by default, 2 retries): 429 for every method honouring `Retry-After`; network errors and 5xx only for GET/HEAD/OPTIONS. Writes are never retried after an uncertain failure.
- `Testing\MockClient`, `MockResponse`, `RecordedRequest` and `NetworkError` to fake the API in tests, with `assertSent()`, `assertNotSent()`, `assertSentCount()` and `assertNothingSent()`.
- `Mopux` entry point (`new Mopux($url, $token)`, `Mopux::login()`, `forTenant()`, `withToken()`) and `auth()` resource (login, logout, user).
- Token providers: `StaticToken`, `CallbackToken` and `RefreshableTokenProvider` (one automatic refresh and resend after a 401, for OAuth).
- `Data\User` and `Data\AuthToken`, built on a typed, lenient payload reader.
- Enums: `TaskStatus` (with read-only `Delayed`), `TaskPriority`, `ProjectStatus`, `AssigneeOperation`, `ActivityFilter`, `NotificationFilter`, `TouchBox`, `TouchStatus`, `InvitationStatus`.
- Read data objects mirroring the API resources: `Task`, `TaskGroup`, `Project`, `ProjectBoard`, `ProjectStatistics` (`GroupStatistics`, `PersonWorkload`, `StatusCounts`), `ProjectActivity`, `Comment` (`CommentParent`, `Mention`), `Attachment`, `Notification`, `Touch`, `TeamMember`, `TeamInvitation`, `MoveOptions` (`MoveDestination`, `MoveWarning`), `MarkdownHelp`, `UserBrief` and `Reference`.
- Write inputs (`Data\Input`): `TaskCreate`, `TaskUpdate`, `TaskBatchUpdate`/`TaskBatchItem`, `TaskGroupCreate`/`TaskGroupTask`, `ProjectCreate`, `ProjectUpdate`, `CommentCreate` and `AssigneeMutation`, with `Missing` to tell "not sent" from `null` (clear) and client-side validation mirroring the API rules.
- `Data\Paginated<T>` for the three list shapes of the API (Laravel `data/links/meta`, page fields and plain lists) with `hasMorePages()`, `nextPage()` and lazy `Paginated::iterate()`.
- `projects()` resource: list (with lazy `all()`), create, show, update, delete (slug confirmation), board (filters, archived groups excluded by default), activity, summary and members.
- `taskGroups()` resource: create (with up to 4 tasks), reorder, reorderTasks, rename, archive, unarchive, move, copy, createProject, delete, focus and statusSummary.
