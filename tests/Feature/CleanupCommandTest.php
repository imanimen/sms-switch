<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Imanimen\SmsSwitch\Models\SmsLog;
use Ramsey\Uuid\Uuid;

it('deletes only logs older than the retention window', function (): void {
    SmsLog::query()->create([
        'provider'       => 'mobinsms', 'type' => 'text', 'to' => '9891',
        'status'         => 'success', 'latency_ms' => 100,
        'correlation_id' => Uuid::uuid4()->toString(),
        'created_at'     => Carbon::now()->subDays(60),
        'updated_at'     => Carbon::now()->subDays(60),
    ]);
    SmsLog::query()->create([
        'provider'       => 'mobinsms', 'type' => 'text', 'to' => '9891',
        'status'         => 'success', 'latency_ms' => 100,
        'correlation_id' => Uuid::uuid4()->toString(),
        'created_at'     => Carbon::now()->subDays(1),
        'updated_at'     => Carbon::now()->subDays(1),
    ]);

    $this->artisan('sms:cleanup', ['--days' => 30])->assertSuccessful();

    expect(SmsLog::query()->count())->toBe(1);
});
