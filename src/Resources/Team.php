<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Generator;
use Mopux\Data\Paginated;
use Mopux\Data\TeamMember;
use Mopux\Exceptions\InvalidInputException;

/**
 * Members of the installation's team (`/api/v1/team`).
 */
final class Team extends Resource
{
    /**
     * @param  string|null  $search  Matches name and email.
     * @param  string|null  $role  Role name (e.g. `admin`, `external`).
     * @return Paginated<TeamMember>
     */
    public function list(?string $search = null, ?string $role = null, int $page = 1, int $perPage = 25): Paginated
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidInputException('per_page', 'must be between 1 and 100.');
        }

        $response = $this->connector->get('team', ['search' => $search, 'role' => $role, 'page' => $page, 'per_page' => $perPage]);

        return Paginated::fromPageFields($response->array(), 'members', TeamMember::fromArray(...), $perPage);
    }

    /**
     * @return Generator<int, TeamMember>
     */
    public function all(?string $search = null, ?string $role = null, int $perPage = 100): Generator
    {
        return Paginated::iterate(fn (int $page): Paginated => $this->list($search, $role, $page, $perPage));
    }
}
