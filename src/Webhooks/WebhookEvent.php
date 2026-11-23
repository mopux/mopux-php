<?php

declare(strict_types=1);

namespace Mopux\Webhooks;

use DateTimeImmutable;
use Mopux\Data\Task;
use Mopux\Exceptions\InvalidPayloadException;
use Mopux\Support\Payload;
use Psr\Http\Message\ServerRequestInterface;

final readonly class WebhookEvent
{
    /**
     * @param  array{id: int, name: string, via: string}|null  $actor
     * @param  array<array-key, mixed>  $data
     */
    private function __construct(
        public string $id,
        public string $deliveryId,
        public WebhookEventType|string $event,
        public DateTimeImmutable $occurredAt,
        public string $installation,
        public ?array $actor,
        public array $data,
    ) {}

    public static function fromRequest(ServerRequestInterface $request, string $secret): self
    {
        return self::fromPayload((string) $request->getBody(), [
            'X-Mopux-Event' => $request->getHeader('X-Mopux-Event'),
            'X-Mopux-Delivery' => $request->getHeader('X-Mopux-Delivery'),
            'X-Mopux-Signature' => $request->getHeader('X-Mopux-Signature'),
        ], $secret);
    }

    /**
     * @param  array<string, string|array<array-key, string|null>>  $headers
     */
    public static function fromPayload(string $rawBody, array $headers, string $secret): self
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $key = strtolower($name);
            $normalized[$key] = array_merge($normalized[$key] ?? [], (array) $values);
        }

        $signature = $normalized['x-mopux-signature'] ?? [];
        WebhookSignature::verify($rawBody, count($signature) === 1 ? ($signature[0] ?? '') : '', $secret);

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidPayloadException('webhook', 'a JSON object', $decoded);
        }

        $payload = Payload::of($decoded, 'webhook');
        $event = $payload->string('event');
        if ($event === '' || ($normalized['x-mopux-event'] ?? []) !== [$event]) {
            throw new InvalidPayloadException('X-Mopux-Event', 'a single header matching the event in the body', $normalized['x-mopux-event'] ?? null);
        }

        $delivery = $normalized['x-mopux-delivery'] ?? [];
        if (! $payload->has('actor')) {
            throw new InvalidPayloadException('webhook.actor', 'an object or null', null);
        }
        $actor = $payload->nullableObject('actor');
        $data = $payload->object('data')->all();
        if ($data !== [] && array_is_list($data)) {
            throw new InvalidPayloadException('webhook.data', 'a JSON object', $data);
        }
        $occurredAt = $payload->string('occurred_at');

        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]+)?Z\z/', $occurredAt) !== 1) {
            throw new InvalidPayloadException('webhook.occurred_at', 'an ISO-8601 timestamp in UTC ending in Z', $occurredAt);
        }
        $date = $payload->date('occurred_at');
        if (DateTimeImmutable::getLastErrors() !== false) {
            throw new InvalidPayloadException('webhook.occurred_at', 'a valid date and time', $occurredAt);
        }

        return new self(
            id: self::uuid($payload->string('id'), 'webhook.id'),
            deliveryId: self::uuid(implode(',', $delivery), 'X-Mopux-Delivery'),
            event: WebhookEventType::tryFrom($event) ?? $event,
            occurredAt: $date,
            installation: $payload->string('installation'),
            actor: $actor === null ? null : [
                'id' => $actor->int('id'),
                'name' => $actor->string('name'),
                'via' => $actor->string('via'),
            ],
            data: $data,
        );
    }

    public function task(): ?Task
    {
        $task = Payload::of($this->data, 'webhook.data')->nullableObject('task');

        return $task === null || ($task->all()['deleted'] ?? false) === true
            ? null
            : Task::fromArray($task->all());
    }

    public function previousStatus(): ?int
    {
        return Payload::of($this->data, 'webhook.data')->nullableInt('previous_status');
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function changes(): array
    {
        $changes = [];

        foreach (Payload::of($this->data, 'webhook.data')->array('changes') as $field => $change) {
            if (! is_string($field) || ! is_array($change)
                || ! array_key_exists('old', $change) || ! array_key_exists('new', $change)) {
                throw new InvalidPayloadException('webhook.data.changes', 'a map of field names to old and new values', $change);
            }

            $changes[$field] = ['old' => $change['old'], 'new' => $change['new']];
        }

        return $changes;
    }

    /**
     * @return list<int>
     */
    public function assignedUserIds(): array
    {
        return Payload::of($this->data, 'webhook.data')->intList('assigned_user_ids');
    }

    public function previousProjectId(): ?int
    {
        return Payload::of($this->data, 'webhook.data')->nullableInt('previous_project_id');
    }

    public function previousGroupId(): ?int
    {
        return Payload::of($this->data, 'webhook.data')->nullableInt('previous_group_id');
    }

    private static function uuid(string $value, string $field): string
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $value) !== 1) {
            throw new InvalidPayloadException($field, 'a UUID', $value);
        }

        return $value;
    }
}
