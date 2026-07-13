<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Contracts;

interface ProviderSelector
{
    /**
     * Return an ordered list of provider names to try. First = primary.
     * Subsequent entries are fallbacks (used only when caller opts into fallback).
     *
     * @return list<string>
     */
    public function candidates(): array;
}
