<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Metrics;

use Illuminate\Support\Carbon;
use Imanimen\SmsSwitch\CircuitBreaker\CircuitBreaker;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;
use Imanimen\SmsSwitch\Models\SmsLog;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

class MetricsRecorder
{
    /**
     * @param  array<string,mixed>  $scoringConfig
     */
    public function __construct(
        private readonly CircuitBreaker $breaker,
        private readonly array $scoringConfig,
        private readonly bool $loggingEnabled = true,
    ) {
    }

    public function metric(string $provider): SmsProviderMetric
    {
        return SmsProviderMetric::query()->firstOrCreate(
            ['provider' => $provider],
            ['window_start' => Carbon::now()],
        );
    }

    public function record(SmsMessage $message, SmsResult $result, int $manualWeight): void
    {
        $metric = $this->metric($result->providerName);

        $this->maybeResetWindow($metric);

        if ($result->success) {
            $metric->success_count += 1;
            $metric->last_success_at = Carbon::now();
            $this->breaker->recordSuccess($metric);
        } else {
            $metric->failure_count += 1;
            $metric->last_failure_at = Carbon::now();
            $this->breaker->recordFailure($metric);
        }

        $metric->total_latency_ms += $result->latencyMs;
        $totalAttempts = $metric->success_count + $metric->failure_count;
        $metric->avg_latency_ms = $totalAttempts > 0 ? (int) ($metric->total_latency_ms / $totalAttempts) : 0;

        $metric->score = $this->computeScore($metric, $manualWeight);
        $metric->save();

        if ($this->loggingEnabled) {
            SmsLog::query()->create([
                'provider'       => $result->providerName,
                'type'           => $message->type,
                'to'             => $message->to,
                'body'           => $message->body,
                'pattern'        => $message->pattern,
                'tokens'         => $message->tokens ?: null,
                'status'         => $result->success ? 'success' : 'failed',
                'message_id'     => $result->messageId,
                'latency_ms'     => $result->latencyMs,
                'error_message'  => $result->error,
                'correlation_id' => $message->correlationId,
            ]);
        }
    }

    public function computeScore(SmsProviderMetric $metric, int $manualWeight): float
    {
        $weights = (array) ($this->scoringConfig['weights'] ?? []);
        $wSuccess = (float) ($weights['success_rate'] ?? 0.6);
        $wLatency = (float) ($weights['latency'] ?? 0.3);
        $wManual = (float) ($weights['manual_weight'] ?? 0.1);
        $maxLatency = max(1, (int) ($this->scoringConfig['max_expected_latency_ms'] ?? 3000));

        $total = $metric->success_count + $metric->failure_count;
        $successRate = $total > 0 ? ($metric->success_count / $total) : 1.0;

        $latencyRatio = min(1.0, ($metric->avg_latency_ms > 0 ? $metric->avg_latency_ms : 0) / $maxLatency);
        $latencyComponent = 1.0 - $latencyRatio;

        $manualComponent = max(0.0, min(1.0, $manualWeight / 100));

        return round(
            ($wSuccess * $successRate) + ($wLatency * $latencyComponent) + ($wManual * $manualComponent),
            4,
        );
    }

    private function maybeResetWindow(SmsProviderMetric $metric): void
    {
        $windowMinutes = (int) ($this->scoringConfig['window_minutes'] ?? 15);
        if ($windowMinutes <= 0) {
            return;
        }

        if ($metric->window_start === null || $metric->window_start->addMinutes($windowMinutes)->isPast()) {
            $metric->success_count = 0;
            $metric->failure_count = 0;
            $metric->total_latency_ms = 0;
            $metric->avg_latency_ms = 0;
            $metric->window_start = Carbon::now();
        }
    }
}
