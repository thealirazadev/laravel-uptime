<?php

namespace App\Support;

/**
 * Immutable result of a single HTTP check. Produced by RunHttpCheck and consumed
 * by Monitor::applyCheckResult, so the state machine never touches the HTTP layer.
 */
final class CheckOutcome
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?int $httpStatus = null,
        public readonly ?int $responseTimeMs = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(int $httpStatus, int $responseTimeMs): self
    {
        return new self(true, $httpStatus, $responseTimeMs);
    }

    /** A failed check. The reason is truncated to fit the 255-char column. */
    public static function failure(string $error, ?int $httpStatus = null, ?int $responseTimeMs = null): self
    {
        return new self(false, $httpStatus, $responseTimeMs, mb_substr($error, 0, 255));
    }
}
