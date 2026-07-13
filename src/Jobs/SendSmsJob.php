<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\SmsManager;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly SmsMessage $message,
        public readonly ?string $forcedProvider = null,
    ) {
    }

    public function handle(SmsManager $manager): void
    {
        $target = $this->forcedProvider !== null ? $manager->via($this->forcedProvider) : $manager;
        $target->dispatch($this->message);
    }
}
