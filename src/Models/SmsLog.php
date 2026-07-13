<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $type
 * @property string $to
 * @property string|null $body
 * @property string|null $pattern
 * @property array|null $tokens
 * @property string $status
 * @property string|null $message_id
 * @property int $latency_ms
 * @property string|null $error_message
 * @property string $correlation_id
 */
class SmsLog extends Model
{
    protected $table = 'sms_logs';

    protected $guarded = [];

    protected $casts = [
        'tokens'     => 'array',
        'latency_ms' => 'integer',
    ];

    public function getConnectionName(): ?string
    {
        return config('sms-switch.logging.connection') ?: parent::getConnectionName();
    }
}
