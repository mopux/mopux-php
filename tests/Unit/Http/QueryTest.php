<?php

declare(strict_types=1);

use Mopux\Http\Query;

enum QueryTestStatus: int
{
    case Done = 1;
}

it('builds laravel style query strings', function (): void {
    $query = Query::build([
        'search' => 'web app',
        'page' => 2,
        'archived' => false,
        'include' => true,
        'skip' => null,
        'status' => QueryTestStatus::Done,
        'from' => new DateTimeImmutable('2026-11-20 10:00:00'),
        'users' => [3, 5],
    ]);

    expect($query)->toBe('search=web%20app&page=2&archived=0&include=1&status=1&from=2026-11-20&users%5B%5D=3&users%5B%5D=5');
});

it('returns an empty string when every value is null', function (): void {
    expect(Query::build(['a' => null]))->toBe('');
});
