<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\DTO;

final class SmsResult
{
    /**
     * @param  array<string,mixed>  $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $providerName,
        public readonly ?string $messageId = null,
        public readonly int $latencyMs = 0,
        public readonly ?string $error = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param  array<string,mixed>  $raw
     */
    public static function ok(string $provider, ?string $messageId, int $latencyMs, array $raw = []): self
    {
        return new self(true, $provider, $messageId, $latencyMs, null, $raw);
    }

    /**
     * @param  array<string,mixed>  $raw
     */
    public static function fail(string $provider, string $error, int $latencyMs = 0, array $raw = []): self
    {
        return new self(false, $provider, null, $latencyMs, $error, $raw);
    }
}
