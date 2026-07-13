<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property int $success_count
 * @property int $failure_count
 * @property int $total_latency_ms
 * @property int $avg_latency_ms
 * @property float $score
 * @property string $circuit_state
 * @property \Illuminate\Support\Carbon|null $circuit_opened_at
 * @property int $consecutive_failures
 * @property int $half_open_probes
 * @property \Illuminate\Support\Carbon|null $last_success_at
 * @property \Illuminate\Support\Carbon|null $last_failure_at
 * @property \Illuminate\Support\Carbon|null $window_start
 */
class SmsProviderMetric extends Model
{
    protected $table = 'sms_provider_metrics';

    protected $guarded = [];

    protected $casts = [
        'success_count'        => 'integer',
        'failure_count'        => 'integer',
        'total_latency_ms'     => 'integer',
        'avg_latency_ms'       => 'integer',
        'score'                => 'float',
        'consecutive_failures' => 'integer',
        'half_open_probes'     => 'integer',
        'circuit_opened_at'    => 'datetime',
        'last_success_at'      => 'datetime',
        'last_failure_at'      => 'datetime',
        'window_start'         => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('sms-switch.logging.connection') ?: parent::getConnectionName();
    }
}
