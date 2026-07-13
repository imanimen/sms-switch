<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Imanimen\SmsSwitch\Models\SmsLog;

class CleanupLogsCommand extends Command
{
    protected $signature = 'sms:cleanup {--days= : Retention window in days (defaults to config)}';

    protected $description = 'Delete SMS log rows older than the configured retention window.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('sms-switch.logging.retention_days', 30));
        if ($days <= 0) {
            $this->error('Retention days must be a positive integer.');
            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $total = 0;

        do {
            $deleted = SmsLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("Deleted {$total} sms_logs rows older than {$days} days (< {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
