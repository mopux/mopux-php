<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\ServerRequest;
use Mopux\Exceptions\InvalidPayloadException;
use Mopux\Exceptions\InvalidWebhookSignatureException;
use Mopux\Webhooks\WebhookEvent;
use Mopux\Webhooks\WebhookEventType;
use Mopux\Webhooks\WebhookSignature;

it('parses the signed event fixture', function (string $name) {
    $fixtures = apiFixture('webhooks/events');
    $data = $fixtures['events'][$name];
    $type = WebhookEventType::tryFrom($name);
    $isTask = str_starts_with($name, 'task.');
    $deleted = $name === 'task.deleted';

    if ($isTask && ! $deleted) {
        $data['task'] = apiFixture('task_detail');
    }

    $envelope = $fixtures['envelope'];
    if ($type === WebhookEventType::Ping) {
        $envelope['actor'] = null;
    }
    $body = json_encode($envelope + ['event' => $name, 'data' => $data], JSON_THROW_ON_ERROR);
    $delivery = '00000000-0000-4000-8000-000000000002';
    $request = new ServerRequest('POST', '/webhooks/mopux', [
        'X-mOPUX-eVENT' => $name,
        'x-mopux-delivery' => $delivery,
        'X-MOPUX-SIGNATURE' => WebhookSignature::sign($body, 'test'),
    ], $body);
    $request->getBody()->read(10);

    $event = WebhookEvent::fromRequest($request, 'test');

    expect($event->event)->toBe($type ?? $name)
        ->and($event->id)->toBe($envelope['id'])
        ->and($event->deliveryId)->toBe($delivery)
        ->and($event->occurredAt->format('Y-m-d\TH:i:s.u\Z'))->toBe($envelope['occurred_at'])
        ->and($event->occurredAt->getOffset())->toBe(0)
        ->and($event->installation)->toBe($envelope['installation'])
        ->and($event->actor)->toBe($envelope['actor'])
        ->and($event->data)->toBe($data)
        ->and($event->task()?->id)->toBe($isTask && ! $deleted ? $data['task']['id'] : null)
        ->and($event->previousStatus())->toBe($data['previous_status'] ?? null)
        ->and($event->changes())->toBe($data['changes'] ?? [])
        ->and($event->assignedUserIds())->toBe($data['assigned_user_ids'] ?? [])
        ->and($event->previousProjectId())->toBe($data['previous_project_id'] ?? null)
        ->and($event->previousGroupId())->toBe($data['previous_group_id'] ?? null);
})->with(fn () => array_keys(apiFixture('webhooks/events')['events']));

it('keeps a reduced task available in another task event', function () {
    $fixture = apiFixture('webhooks/events');
    $data = $fixture['events']['task.deleted'];
    $body = json_encode($fixture['envelope'] + ['event' => 'task.updated', 'data' => $data], JSON_THROW_ON_ERROR);

    $event = WebhookEvent::fromPayload($body, [
        'X-Mopux-Event' => 'task.updated',
        'X-Mopux-Delivery' => '00000000-0000-4000-8000-000000000002',
        'X-Mopux-Signature' => WebhookSignature::sign($body, 'test'),
    ], 'test');

    expect($event->task())->toBeNull()->and($event->data)->toBe($data);
});

it('verifies the body before parsing it', function () {
    $signature = WebhookSignature::sign('{', 'test');

    expect(fn () => WebhookEvent::fromPayload('{', ['X-Mopux-Signature' => $signature], 'wrong'))
        ->toThrow(InvalidWebhookSignatureException::class)
        ->and(fn () => WebhookEvent::fromPayload('{', ['X-Mopux-Signature' => $signature], 'test'))
        ->toThrow(InvalidPayloadException::class);
});
