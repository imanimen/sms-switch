<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\DTO;

use Ramsey\Uuid\Uuid;

final class SmsMessage
{
    public const TYPE_TEXT = 'text';
    public const TYPE_PATTERN = 'pattern';

    /**
     * @param  array<string,string|int>  $tokens
     */
    public function __construct(
        public readonly string $to,
        public readonly string $type,
        public readonly ?string $body = null,
        public readonly ?string $pattern = null,
        public readonly array $tokens = [],
        public readonly ?string $from = null,
        public readonly string $correlationId = '',
    ) {
    }

    public static function text(string $to, string $body, ?string $from = null): self
    {
        return new self(
            to: $to,
            type: self::TYPE_TEXT,
            body: $body,
            from: $from,
            correlationId: Uuid::uuid4()->toString(),
        );
    }

    /**
     * @param  array<string,string|int>  $tokens
     */
    public static function pattern(string $to, string $pattern, array $tokens = [], ?string $from = null): self
    {
        return new self(
            to: $to,
            type: self::TYPE_PATTERN,
            pattern: $pattern,
            tokens: $tokens,
            from: $from,
            correlationId: Uuid::uuid4()->toString(),
        );
    }

    public function isPattern(): bool
    {
        return $this->type === self::TYPE_PATTERN;
    }
}
