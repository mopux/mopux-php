<?php

declare(strict_types=1);

namespace Mopux\Webhooks;

use Mopux\Exceptions\InvalidWebhookSignatureException;

final class WebhookSignature
{
    public static function verify(
        string $rawBody,
        string $signatureHeader,
        string $secret,
        int $tolerance = 300,
        ?int $now = null,
    ): void {
        if (preg_match('/\At=(0|[1-9][0-9]*),v1=[0-9a-f]{64}\z/', $signatureHeader, $parts) !== 1
            || (string) (int) $parts[1] !== $parts[1]) {
            throw new InvalidWebhookSignatureException('Malformed Mopux webhook signature header.');
        }

        $timestamp = (int) $parts[1];

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new InvalidWebhookSignatureException('Mopux webhook timestamp is outside the allowed tolerance.');
        }

        if ($secret === '' || ! hash_equals(self::sign($rawBody, $secret, $timestamp), $signatureHeader)) {
            throw new InvalidWebhookSignatureException('Mopux webhook signature does not match.');
        }
    }

    public static function sign(string $rawBody, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }
}
