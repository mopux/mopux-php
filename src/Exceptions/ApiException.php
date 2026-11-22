<?php

declare(strict_types=1);

namespace Mopux\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * The API answered with an HTTP error status. Subclasses map specific statuses.
 */
class ApiException extends MopuxException
{
    /** The contracted plan does not include the requested feature (`data.feature` names it). */
    public const CODE_PLAN_FEATURE_UNAVAILABLE = 'plan_feature_unavailable';

    /** The installation is blocked by billing or its public trial has ended. */
    public const CODE_BILLING_ACCESS_BLOCKED = 'billing_access_blocked';

    /**
     * @param  array<array-key, mixed>|null  $body  Decoded JSON body, when it was valid JSON.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $method,
        public readonly string $path,
        public readonly ?array $body,
        public readonly ResponseInterface $response,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Value of `data.error` in the error envelope, when present.
     */
    public function error(): ?string
    {
        return $this->dataString('error');
    }

    /**
     * Stable machine-readable code from `data.code`, when the API sets one.
     * Treat unknown codes as a generic error of the HTTP status.
     */
    public function errorCode(): ?string
    {
        return $this->dataString('code');
    }

    /**
     * Whether the request failed because the contracted plan does not include a feature.
     */
    public function isPlanRestriction(): bool
    {
        return $this->errorCode() === self::CODE_PLAN_FEATURE_UNAVAILABLE;
    }

    /**
     * The feature missing from the plan (`data.feature`, e.g. `api`), on plan restrictions.
     */
    public function planFeature(): ?string
    {
        return $this->isPlanRestriction() ? $this->dataString('feature') : null;
    }

    /**
     * Whether the installation is blocked by billing or its public trial has ended.
     */
    public function isBillingBlocked(): bool
    {
        return $this->errorCode() === self::CODE_BILLING_ACCESS_BLOCKED;
    }

    private function dataString(string $key): ?string
    {
        $data = $this->body['data'] ?? null;
        $value = is_array($data) ? ($data[$key] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
