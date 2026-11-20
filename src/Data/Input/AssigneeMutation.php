<?php

declare(strict_types=1);

namespace Mopux\Data\Input;

use Mopux\Enums\AssigneeOperation;
use Mopux\Exceptions\InvalidInputException;

/**
 * Change of the assignees of an existing task: `{operation, user_ids}`.
 *
 *     AssigneeMutation::replace([3, 5]);   // exact set
 *     AssigneeMutation::replace([]);       // unassign everybody
 *     AssigneeMutation::add([7]);
 *     AssigneeMutation::remove([3]);
 */
final class AssigneeMutation
{
    /** @var list<int> */
    public readonly array $userIds;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public readonly AssigneeOperation $operation, array $userIds)
    {
        $this->userIds = Validate::ids($userIds, 'assignees.user_ids');

        if ($operation !== AssigneeOperation::Replace && $this->userIds === []) {
            throw new InvalidInputException('assignees.user_ids', sprintf('"%s" needs at least one user id.', $operation->value));
        }
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function replace(array $userIds): self
    {
        return new self(AssigneeOperation::Replace, $userIds);
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function add(array $userIds): self
    {
        return new self(AssigneeOperation::Add, $userIds);
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function remove(array $userIds): self
    {
        return new self(AssigneeOperation::Remove, $userIds);
    }

    public static function none(): self
    {
        return new self(AssigneeOperation::Replace, []);
    }

    /**
     * @return array{operation: string, user_ids: list<int>}
     */
    public function toArray(): array
    {
        return ['operation' => $this->operation->value, 'user_ids' => $this->userIds];
    }
}
