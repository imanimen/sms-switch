<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Facades;

use Illuminate\Support\Facades\Facade;
use Imanimen\SmsSwitch\DTO\SmsResult;
use Imanimen\SmsSwitch\SmsManager;

/**
 * @method static SmsManager via(string $provider)
 * @method static SmsResult send(string $to, string $text, ?string $from = null)
 * @method static SmsResult sendPattern(string $to, string $pattern, array $tokens = [], ?string $from = null)
 * @method static void queue(string $to, string $text, ?string $from = null)
 * @method static void queuePattern(string $to, string $pattern, array $tokens = [], ?string $from = null)
 * @method static SmsResult status(string $messageId, string $provider)
 * @method static SmsResult balance(string $provider)
 *
 * @see \Imanimen\SmsSwitch\SmsManager
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
