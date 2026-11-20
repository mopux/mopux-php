<?php

declare(strict_types=1);

use Mopux\Enums\ActivityFilter;
use Mopux\Enums\AssigneeOperation;
use Mopux\Enums\InvitationStatus;
use Mopux\Enums\NotificationFilter;
use Mopux\Enums\ProjectStatus;
use Mopux\Enums\TaskPriority;
use Mopux\Enums\TaskStatus;
use Mopux\Enums\TouchBox;
use Mopux\Enums\TouchStatus;
use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function openapi(): array
{
    static $spec = null;

    return $spec ??= Yaml::parseFile(__DIR__.'/../../../resources/openapi.yaml');
}

/**
 * Enum values of a query parameter, looked up by path, method and name.
 *
 * @return list<int|string>
 */
function queryEnum(string $path, string $method, string $name): array
{
    foreach (openapi()['paths'][$path][$method]['parameters'] ?? [] as $parameter) {
        if (($parameter['name'] ?? null) === $name) {
            return $parameter['schema']['enum'];
        }
    }

    throw new LogicException("Parameter {$name} not found in {$method} {$path}.");
}

/**
 * @param  class-string<BackedEnum>  $enum
 * @return list<int|string>
 */
function enumValues(string $enum): array
{
    return array_map(fn (BackedEnum $case) => $case->value, $enum::cases());
}

it('matches the writable task statuses of the spec and keeps delayed read-only', function (): void {
    $writable = array_values(array_map(
        fn (TaskStatus $status) => $status->value,
        array_filter(TaskStatus::cases(), fn (TaskStatus $status) => $status->isWritable()),
    ));

    expect($writable)->toEqualCanonicalizing(openapi()['components']['schemas']['TaskStatus']['enum'])
        ->and(TaskStatus::Delayed->value)->toBe(-1)
        ->and(TaskStatus::Delayed->isWritable())->toBeFalse()
        ->and(TaskStatus::Done->isOpen())->toBeFalse()
        ->and(TaskStatus::Review->isOpen())->toBeTrue();
});

it('matches the spec', function (string $enum, array $expected): void {
    expect(enumValues($enum))->toEqualCanonicalizing($expected);
})->with([
    'task priority' => fn () => [TaskPriority::class, openapi()['components']['schemas']['TaskPriority']['enum']],
    'assignee operation' => fn () => [AssigneeOperation::class, openapi()['components']['schemas']['AssigneeMutation']['properties']['operation']['enum']],
    'project status' => fn () => [ProjectStatus::class, openapi()['paths']['/api/v1/projects/{projectId}']['patch']['requestBody']['content']['application/json']['schema']['properties']['status']['enum']],
    'activity filter' => fn () => [ActivityFilter::class, queryEnum('/api/v1/projects/{projectId}/activity', 'get', 'filter')],
    'notification filter' => fn () => [NotificationFilter::class, queryEnum('/api/v1/notifications', 'get', 'filter')],
    'touch box' => fn () => [TouchBox::class, queryEnum('/api/v1/touches', 'get', 'box')],
    'touch status' => fn () => [TouchStatus::class, queryEnum('/api/v1/touches', 'get', 'status')],
    'invitation status' => fn () => [InvitationStatus::class, queryEnum('/api/v1/team/invitations', 'get', 'status')],
]);

it('uses the backend values of TaskStatusEnum and TaskPriorityEnum', function (): void {
    // Mirrors mopux-com app/Enums: Pending 0, Doing 1, Revision 2, Done 3, Delayed -1;
    // Lowest 0, Medium 1, High 2, Highest 9.
    expect(enumValues(TaskStatus::class))->toBe([0, 1, 2, 3, -1])
        ->and(enumValues(TaskPriority::class))->toBe([0, 1, 2, 9])
        ->and(enumValues(ProjectStatus::class))->toBe([0, 1, 2, -1]);
});
