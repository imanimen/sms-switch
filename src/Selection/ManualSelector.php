<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Selection;

use Imanimen\SmsSwitch\Contracts\ProviderSelector;
use Imanimen\SmsSwitch\Exceptions\ProviderNotConfiguredException;

class ManualSelector implements ProviderSelector
{
    /**
     * @param  array<string,array<string,mixed>>  $providers
     */
    public function __construct(
        private readonly string $default,
        private readonly array $providers,
    ) {
    }

    public function candidates(): array
    {
        if ($this->default === '' || ! isset($this->providers[$this->default])) {
            throw new ProviderNotConfiguredException(
                "Default provider [{$this->default}] is not defined in sms-switch.providers.",
            );
        }

        if (! (bool) ($this->providers[$this->default]['enabled'] ?? true)) {
            throw new ProviderNotConfiguredException(
                "Default provider [{$this->default}] is disabled.",
            );
        }

        return [$this->default];
    }
}
