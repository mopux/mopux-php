<?php

declare(strict_types=1);

use Mopux\Exceptions\InvalidWebhookSignatureException;
use Mopux\Webhooks\WebhookSignature;

it('matches each upstream signature vector and rejects altered bytes or secrets', function (array $vector) {
    ['body' => $body, 'secret' => $secret, 'timestamp' => $timestamp, 'signature' => $signature] = $vector;

    expect(WebhookSignature::sign($body, $secret, $timestamp))->toBe($signature);
    WebhookSignature::verify($body, $signature, $secret, now: $timestamp);
    WebhookSignature::verify($body, $signature, $secret, now: $timestamp + 300);
    WebhookSignature::verify($body, $signature, $secret, now: $timestamp - 300);

    expect(fn () => WebhookSignature::verify($body."\n", $signature, $secret, now: $timestamp))
        ->toThrow(InvalidWebhookSignatureException::class, 'does not match')
        ->and(fn () => WebhookSignature::verify($body, $signature, $secret.'x', now: $timestamp))
        ->toThrow(InvalidWebhookSignatureException::class, 'does not match')
        ->and(fn () => WebhookSignature::verify($body, $signature, $secret, now: $timestamp + 301))
        ->toThrow(InvalidWebhookSignatureException::class, 'outside')
        ->and(fn () => WebhookSignature::verify($body, $signature, $secret, now: $timestamp - 301))
        ->toThrow(InvalidWebhookSignatureException::class, 'outside');
})->with(fn () => array_map(static fn (array $vector): array => [$vector], apiFixture('webhooks/signature-vectors')['vectors']));

it('rejects malformed signature headers', function (string $header) {
    expect(fn () => WebhookSignature::verify('{}', $header, 'test'))
        ->toThrow(InvalidWebhookSignatureException::class, 'Malformed');
})->with([
    '', 'v1=abc', 't=123,v1=abc', 't=1,t=2,v1='.str_repeat('0', 64),
    't=999999999999999999999999,v1='.str_repeat('0', 64),
]);
