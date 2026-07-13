<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\CircuitBreaker;

use Illuminate\Support\Carbon;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

class CircuitBreaker
{
    public const CLOSED = 'closed';
    public const OPEN = 'open';
    public const HALF_OPEN = 'half_open';

    /**
     * @param  array<string,int>  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Return whether traffic is allowed through this provider right now.
     * Also transitions `open` → `half_open` when the cooldown has elapsed.
     */
    public function allows(SmsProviderMetric $metric): bool
    {
        if ($metric->circuit_state === self::CLOSED) {
            return true;
        }

        if ($metric->circuit_state === self::HALF_OPEN) {
            return true;
        }

        if ($metric->circuit_state === self::OPEN) {
            $openedAt = $metric->circuit_opened_at;
            $cooldown = (int) ($this->config['open_duration_seconds'] ?? 60);

            if ($openedAt !== null && $openedAt->addSeconds($cooldown)->isPast()) {
                $metric->circuit_state = self::HALF_OPEN;
                $metric->half_open_probes = 0;
                $metric->save();

                return true;
            }
        }

        return false;
    }

    public function recordSuccess(SmsProviderMetric $metric): void
    {
        $metric->consecutive_failures = 0;

        if ($metric->circuit_state === self::HALF_OPEN) {
            $metric->half_open_probes += 1;
            $threshold = (int) ($this->config['half_open_success_threshold'] ?? 1);

            if ($metric->half_open_probes >= $threshold) {
                $metric->circuit_state = self::CLOSED;
                $metric->circuit_opened_at = null;
                $metric->half_open_probes = 0;
            }
        } elseif ($metric->circuit_state === self::OPEN) {
            $metric->circuit_state = self::CLOSED;
            $metric->circuit_opened_at = null;
        }
    }

    public function recordFailure(SmsProviderMetric $metric): void
    {
        $metric->consecutive_failures += 1;
        $threshold = (int) ($this->config['failure_threshold'] ?? 5);

        if ($metric->circuit_state === self::HALF_OPEN) {
            $metric->circuit_state = self::OPEN;
            $metric->circuit_opened_at = Carbon::now();
            $metric->half_open_probes = 0;

            return;
        }

        if ($metric->circuit_state === self::CLOSED && $metric->consecutive_failures >= $threshold) {
            $metric->circuit_state = self::OPEN;
            $metric->circuit_opened_at = Carbon::now();
        }
    }
}
