<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Imanimen\SmsSwitch\Metrics\MetricsRecorder;
use Imanimen\SmsSwitch\Models\SmsLog;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

class RecomputeMetricsCommand extends Command
{
    protected $signature = 'sms:metrics:recompute {--minutes= : Rolling window override in minutes}';

    protected $description = 'Rebuild sms_provider_metrics counters from sms_logs (does NOT reset circuit-breaker state).';

    public function handle(MetricsRecorder $recorder): int
    {
        $minutes = (int) ($this->option('minutes') ?? config('sms-switch.scoring.window_minutes', 15));
        $since = Carbon::now()->subMinutes(max(1, $minutes));

        $providers = SmsLog::query()->where('created_at', '>=', $since)
            ->select('provider')->distinct()->pluck('provider');

        if ($providers->isEmpty()) {
            $this->info('No logs in window; nothing to recompute.');
            return self::SUCCESS;
        }

        foreach ($providers as $provider) {
            $rows = SmsLog::query()
                ->where('provider', $provider)
                ->where('created_at', '>=', $since)
                ->get(['status', 'latency_ms']);

            $success = $rows->where('status', 'success')->count();
            $failure = $rows->where('status', 'failed')->count();
            $totalLatency = (int) $rows->sum('latency_ms');
            $totalAttempts = $success + $failure;

            $metric = SmsProviderMetric::query()->firstOrNew(['provider' => $provider]);
            $metric->success_count = $success;
            $metric->failure_count = $failure;
            $metric->total_latency_ms = $totalLatency;
            $metric->avg_latency_ms = $totalAttempts > 0 ? (int) ($totalLatency / $totalAttempts) : 0;
            $metric->window_start = $since;

            $weight = (int) config("sms-switch.providers.{$provider}.weight", 0);
            $metric->score = $recorder->computeScore($metric, $weight);
            $metric->save();

            $this->line("  {$provider}: success={$success} failure={$failure} score=" . number_format($metric->score, 4));
        }

        $this->info('Recomputed metrics for ' . $providers->count() . ' provider(s).');
        return self::SUCCESS;
    }
}
