<?php

declare(strict_types=1);

use Mopux\Data\TeamInvitation;
use Mopux\Data\TeamMember;
use Mopux\Data\Touch;
use Mopux\Enums\InvitationStatus;
use Mopux\Enums\TouchBox;
use Mopux\Enums\TouchStatus;
use Mopux\Exceptions\InvalidInputException;
use Mopux\Testing\MockClient;
use Mopux\Testing\MockResponse;

it('lists touches', function (): void {
    $client = new MockClient(['GET touches' => MockResponse::ok([apiFixture('touch')])]);
    $touches = mopux($client)->touches();

    $received = $touches->list();
    $defaultQuery = $client->lastRecorded()->query;
    $touches->list(TouchBox::Sent, TouchStatus::Answered);

    expect($received[0])->toBeInstanceOf(Touch::class)
        ->and($defaultQuery)->toBe(['box' => 'received'])
        ->and($client->lastRecorded()->query)->toBe(['box' => 'sent', 'status' => 'answered']);
});

it('replies to a touch', function (): void {
    $client = new MockClient(['POST touches/4/reply' => MockResponse::ok(['recipient_message' => 'Bien', 'status' => 'answered'] + apiFixture('touch'))]);

    $touch = mopux($client)->touches()->reply(4, 'Bien');

    expect($touch->isAnswered())->toBeTrue()
        ->and($client->lastRecorded()->json)->toBe(['message' => 'Bien'])
        ->and(fn () => mopux($client)->touches()->reply(4, 'x'))->toThrow(InvalidInputException::class, 'between 2 and 2000')
        ->and(fn () => mopux($client)->touches()->reply(4, str_repeat('a', 2001)))->toThrow(InvalidInputException::class);
});

it('lists the team', function (): void {
    $client = new MockClient(['GET team' => okFixture('team')]);

    $page = mopux($client)->team()->list('ada', 'admin', perPage: 10);

    expect($client->lastRecorded()->query)->toBe(['search' => 'ada', 'role' => 'admin', 'page' => '1', 'per_page' => '10'])
        ->and($page->items[0])->toBeInstanceOf(TeamMember::class)
        ->and($page->total)->toBe(1)
        ->and(iterator_to_array(mopux(new MockClient([okFixture('team')]))->team()->all(), false))->toHaveCount(1);
});

it('lists, creates, resends and deletes invitations', function (): void {
    $client = new MockClient([
        'GET team/invitations' => okFixture('team_invitations_page'),
        'POST team/invitations/2/resend' => okFixture('team_invitation'),
        'POST team/invitations' => okFixture('team_invitation'),
        'DELETE team/invitations/2' => MockResponse::ok([]),
    ]);
    $invitations = mopux($client)->teamInvitations();

    $page = $invitations->list('acme', InvitationStatus::Pending);
    $listQuery = $client->lastRecorded()->query;
    $created = $invitations->create('new@acme.test', 2, 'external');
    $createBody = $client->lastRecorded()->json;
    $invitations->resend(2);
    $invitations->delete(2);

    expect($listQuery)->toBe(['q' => 'acme', 'status' => 'pending', 'page' => '1', 'per_page' => '20'])
        ->and($page->items[0])->toBeInstanceOf(TeamInvitation::class)
        ->and($createBody)->toBe(['email' => 'new@acme.test', 'rol' => 2, 'user_type' => 'external'])
        ->and($created->email)->toBe('new@acme.test')
        ->and(fn () => $invitations->create('not-an-email', 2))->toThrow(InvalidInputException::class, 'not a valid email');
    $client->assertSent('POST team/invitations/2/resend', 1);
    $client->assertSent('DELETE team/invitations/2', 1);
});
