<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Contracts;

use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;

interface SmsProvider
{
    public function name(): string;

    public function send(SmsMessage $message): SmsResult;

    public function getStatus(string $messageId): SmsResult;

    public function getBalance(): SmsResult;
}
