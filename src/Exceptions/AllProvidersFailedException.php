<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Exceptions;

use Imanimen\SmsSwitch\DTO\SmsResult;

class AllProvidersFailedException extends SmsSendException
{
    /**
     * @param  list<SmsResult>  $attempts
     */
    public function __construct(public readonly array $attempts, string $message = '')
    {
        $message = $message ?: 'All configured SMS providers failed.';
        parent::__construct($message);
    }
}
