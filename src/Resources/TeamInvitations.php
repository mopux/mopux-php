<?php

declare(strict_types=1);

namespace Mopux\Resources;

use Mopux\Data\Input\Validate;
use Mopux\Data\Paginated;
use Mopux\Data\TeamInvitation;
use Mopux\Enums\InvitationStatus;
use Mopux\Exceptions\InvalidInputException;

/**
 * Team invitations (`/api/v1/team/invitations`). Requires permission to manage the team.
 */
final class TeamInvitations extends Resource
{
    /**
     * @param  string|null  $search  Matches the invited email.
     * @return Paginated<TeamInvitation>
     */
    public function list(?string $search = null, InvitationStatus $status = InvitationStatus::All, int $page = 1, int $perPage = 20): Paginated
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidInputException('per_page', 'must be between 1 and 100.');
        }

        $response = $this->connector->get('team/invitations', ['q' => $search, 'status' => $status, 'page' => $page, 'per_page' => $perPage]);

        return Paginated::fromLaravel($response->array(), TeamInvitation::fromArray(...));
    }

    /**
     * Invites someone to the team with a role id. Administrators cannot be invited.
     *
     * @param  'internal'|'external'|null  $userType
     */
    public function create(string $email, int $roleId, ?string $userType = null): TeamInvitation
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInputException('email', sprintf('"%s" is not a valid email address.', $email));
        }

        $payload = ['email' => $email, 'rol' => Validate::id($roleId, 'rol')];

        if ($userType !== null) {
            $payload['user_type'] = $userType;
        }

        return TeamInvitation::fromArray($this->connector->post('team/invitations', $payload)->array());
    }

    /**
     * Sends a pending invitation again. Accepted invitations answer 404.
     */
    public function resend(int $invitationId): TeamInvitation
    {
        return TeamInvitation::fromArray($this->connector->post(self::path($invitationId, 'resend'))->array());
    }

    /**
     * Cancels a pending invitation. Accepted invitations answer 404.
     */
    public function delete(int $invitationId): void
    {
        $this->connector->delete(self::path($invitationId));
    }

    private static function path(int $invitationId, string $suffix = ''): string
    {
        return 'team/invitations/'.Validate::id($invitationId, 'invitation_id').($suffix === '' ? '' : '/'.$suffix);
    }
}
