<?php

namespace App\Relay\Delivery;

use Carbon\CarbonImmutable;

class RelayRetryPolicy
{
    public function maxAttempts(): int
    {
        return max(1, (int) config('relay.delivery.max_attempts', 5));
    }

    public function shouldRetry(int $attemptCount): bool
    {
        return $attemptCount < $this->maxAttempts();
    }

    public function nextRetryAt(int $attemptCount): CarbonImmutable
    {
        $schedule = config('relay.delivery.backoff_minutes', [1, 5, 15, 60, 360]);
        $minutes = $schedule[max(0, min($attemptCount - 1, count($schedule) - 1))] ?? 1;

        return CarbonImmutable::now()->addMinutes((int) $minutes);
    }
}
