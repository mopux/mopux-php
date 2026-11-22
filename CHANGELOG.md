# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `ApiException::errorCode()` reads the stable `data.code` of API 1.2.0, with `isPlanRestriction()`, `planFeature()` and `isBillingBlocked()` for the current codes (`plan_feature_unavailable`, `billing_access_blocked`).
- `MockResponse::error()` accepts a code and extra `data` members; new `MockResponse::planRestricted()`.

### Changed
- `MockResponse::tooManyRequests()` and `serverError()` return the error envelope of API 1.2.0.
- `ApiException::error()` returns `null` for an empty `data.error`.
- Bundled spec updated to API 1.2.0 (in development): every error carries `data.error`, the 429 is wrapped and `ApiError` documents `data.code`.

## [0.1.1] - 2026-11-21

### Changed
- Exception messages include the details the API returns besides `message`: `data.error` when it adds the reason, and the 422 field errors not already in the message.

## [0.1.0] - 2026-11-21

### Added
- Package scaffold: Composer metadata, Pest, PHPStan (level max), Pint and CI matrix.
- Composer conflict with `http-interop/http-factory-guzzle` < 1.0, whose factories predate PSR-17.
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
- `tasks()` resource: create, update, updateBatch, my, show, delete, updateStatus, updatePriority, assign, move (with warnings), updateTitle, updateDescription, updateStart, updateEnd, updateHours, sendTouch, focus/pinFocus/unpinFocus, moveOptions, attachments and markdownHelp.
- `comments()` resource: list (with lazy `all()`), create (JSON, or multipart when files are attached), update, delete and reply.
- `tasks()->uploadFile()` and `deleteFile()`; `Data\Input\Upload` (from path, string or stream) and a streaming multipart builder.
- `notifications()` (list, all, markRead, markUnread, markAllRead, unreadCount, delete, deleteAll), `touches()` (list, reply), `team()` (list, all) and `teamInvitations()` (list, create, resend, delete) resources.
- Contract test (`composer test:contract`): every operation of `resources/openapi.yaml` (69) maps to an SDK call that hits the right method and path; `composer sync-spec` refreshes the spec.
- Laravel integration (Laravel 12 and 13; older versions are blocked through `conflict`): auto-discovered `MopuxServiceProvider`, publishable `config/mopux.php` (`MOPUX_URL`, `MOPUX_TOKEN`, timeouts, retries), `Mopux` facade and `Mopux::fake()` with request assertions (also fakes clients injected by type and tenant clients).
- Integration suite (`composer test:integration`) running the whole API flow against a real tenant inside a throwaway project.

### Documentation
- README covering installation, authentication (tokens, OAuth refresh, multi-tenant), resources, inputs, uploads, pagination, errors, retries, Laravel and testing; SECURITY.md.

[Unreleased]: https://github.com/mopux/mopux-php/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/mopux/mopux-php/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/mopux/mopux-php/releases/tag/v0.1.0
