<?php

declare(strict_types=1);

namespace Mopux\Http;

use Closure;
use InvalidArgumentException;
use Mopux\Exceptions\RateLimitException;

/**
 * Decides whether a failed request is retried and how long to wait.
 *
 * - 429 is retried for every method, honouring `Retry-After`: the API throttles
 *   before running the request, so nothing was written.
 * - Network errors and 5xx are retried only for safe methods (GET, HEAD, OPTIONS).
 * - Writes (POST, PATCH, PUT, DELETE) are never retried after a network error or
 *   a 5xx: the outcome is uncertain and task creation is not idempotent.
 */
final class RetryPolicy
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** @var Closure(int): void */
    private readonly Closure $sleeper;

    /**
     * @param  int  $maxRetries  Retries after the first attempt (0 disables retries).
     * @param  int  $baseDelayMs  First backoff delay; doubles on every retry, with jitter.
     * @param  int  $maxDelayMs  Upper bound of the backoff delay.
     * @param  int  $maxRetryAfterSeconds  A longer `Retry-After` is not waited for: the exception is thrown.
     * @param  (Closure(int): void)|null  $sleeper  Receives milliseconds; defaults to usleep().
     */
    public function __construct(
        public readonly int $maxRetries = 2,
        public readonly int $baseDelayMs = 250,
        public readonly int $maxDelayMs = 5_000,
        public readonly int $maxRetryAfterSeconds = 60,
        ?Closure $sleeper = null,
    ) {
        if ($maxRetries < 0 || $baseDelayMs < 0 || $maxDelayMs < 0 || $maxRetryAfterSeconds < 0) {
            throw new InvalidArgumentException('Mopux retry settings cannot be negative.');
        }

        $this->sleeper = $sleeper ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
    }

    public static function none(): self
    {
        return new self(maxRetries: 0);
    }

    /**
     * @param  Closure(int): void  $sleeper
     */
    public function withSleeper(Closure $sleeper): self
    {
        return new self($this->maxRetries, $this->baseDelayMs, $this->maxDelayMs, $this->maxRetryAfterSeconds, $sleeper);
    }

    /**
     * Delay in milliseconds before retrying after a network error, or null to give up.
     */
    public function delayAfterConnectionError(string $method, int $attempt): ?int
    {
        if ($attempt >= $this->maxRetries || ! self::isSafe($method)) {
            return null;
        }

        return $this->backoff($attempt);
    }

    /**
     * Delay in milliseconds before retrying after an HTTP response, or null to give up.
     *
     * @param  int  $attempt  Retries already performed (0 after the first attempt).
     */
    public function delayAfterResponse(string $method, int $status, string $retryAfter, int $attempt): ?int
    {
        if ($attempt >= $this->maxRetries) {
            return null;
        }

        if ($status === 429) {
            $seconds = RateLimitException::parseRetryAfter($retryAfter);

            if ($seconds === null) {
                return $this->backoff($attempt);
            }

            return $seconds <= $this->maxRetryAfterSeconds ? $seconds * 1000 : null;
        }

        if ($status >= 500 && self::isSafe($method)) {
            return $this->backoff($attempt);
        }

        return null;
    }

    public function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            ($this->sleeper)($milliseconds);
        }
    }

    private function backoff(int $attempt): int
    {
        $delay = min($this->maxDelayMs, $this->baseDelayMs * (2 ** $attempt));

        // Up to 25% jitter so concurrent clients do not retry in lockstep.
        return (int) min($this->maxDelayMs, $delay + random_int(0, intdiv($delay, 4)));
    }

    private static function isSafe(string $method): bool
    {
        return in_array(strtoupper($method), self::SAFE_METHODS, true);
    }
}
