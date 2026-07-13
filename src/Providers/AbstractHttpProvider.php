<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\Contracts\SmsProvider;
use Imanimen\SmsSwitch\DTO\SmsResult;
use Throwable;

abstract class AbstractHttpProvider implements SmsProvider
{
    /**
     * @param  array<string,mixed>  $config
     * @param  array<string,mixed>  $httpDefaults
     */
    public function __construct(
        protected readonly array $config,
        protected readonly array $httpDefaults = [],
    ) {
    }

    abstract public function name(): string;

    protected function http(): PendingRequest
    {
        return Http::timeout((int) ($this->httpDefaults['timeout'] ?? 5))
            ->connectTimeout((int) ($this->httpDefaults['connect_timeout'] ?? 2))
            ->acceptJson();
    }

    /**
     * Run a closure, measuring latency and normalizing errors into an SmsResult.
     *
     * @param  callable(): SmsResult  $fn
     */
    protected function measure(callable $fn): SmsResult
    {
        $start = (int) (microtime(true) * 1000);

        try {
            $result = $fn();
            $latency = ((int) (microtime(true) * 1000)) - $start;

            return new SmsResult(
                success: $result->success,
                providerName: $this->name(),
                messageId: $result->messageId,
                latencyMs: $latency,
                error: $result->error,
                raw: $result->raw,
            );
        } catch (Throwable $e) {
            $latency = ((int) (microtime(true) * 1000)) - $start;

            return SmsResult::fail($this->name(), $e->getMessage(), $latency);
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    /**
     * @param  array<string,mixed>  $raw
     */
    protected function ok(?string $messageId, array $raw = []): SmsResult
    {
        return SmsResult::ok($this->name(), $messageId, 0, $raw);
    }

    /**
     * @param  array<string,mixed>  $raw
     */
    protected function fail(string $error, array $raw = []): SmsResult
    {
        return SmsResult::fail($this->name(), $error, 0, $raw);
    }

    /**
     * @return array<string,mixed>
     */
    protected function jsonOrEmpty(Response $response): array
    {
        try {
            $data = $response->json();
        } catch (Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
