<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Console;

use Illuminate\Console\Command;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

class ShowMetricsCommand extends Command
{
    protected $signature = 'sms:metrics';

    protected $description = 'Show current SMS provider metrics, scores, and circuit-breaker state.';

    public function handle(): int
    {
        $rows = SmsProviderMetric::query()->orderByDesc('score')->get();

        if ($rows->isEmpty()) {
            $this->info('No metrics recorded yet.');
            return self::SUCCESS;
        }

        $this->table(
            ['Provider', 'Success', 'Failure', 'Avg latency (ms)', 'Score', 'Circuit', 'Cons. failures', 'Last success', 'Last failure'],
            $rows->map(fn (SmsProviderMetric $m) => [
                $m->provider,
                $m->success_count,
                $m->failure_count,
                $m->avg_latency_ms,
                number_format($m->score, 4),
                $m->circuit_state,
                $m->consecutive_failures,
                $m->last_success_at?->diffForHumans() ?? '-',
                $m->last_failure_at?->diffForHumans() ?? '-',
            ])->toArray(),
        );

        return self::SUCCESS;
    }
}
