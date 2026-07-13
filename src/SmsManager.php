<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch;

use Illuminate\Container\Container;
use Imanimen\SmsSwitch\Contracts\ProviderSelector;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;
use Imanimen\SmsSwitch\Exceptions\AllProvidersFailedException;
use Imanimen\SmsSwitch\Exceptions\ProviderNotConfiguredException;
use Imanimen\SmsSwitch\Jobs\SendSmsJob;
use Imanimen\SmsSwitch\Metrics\MetricsRecorder;

class SmsManager
{
    public const MODE_MANUAL = 'manual';
    public const MODE_AUTO = 'auto';

    private ?string $forcedProvider = null;

    /**
     * @param  array<string,mixed>  $queueConfig
     */
    public function __construct(
        private readonly ProviderFactory $factory,
        private readonly ProviderSelector $selector,
        private readonly MetricsRecorder $recorder,
        private readonly string $mode = self::MODE_MANUAL,
        private readonly array $queueConfig = [],
    ) {
    }

    /**
     * Force a specific provider for the next send() / sendPattern() call.
     * Bypasses selector and fallback. Chainable.
     */
    public function via(string $provider): self
    {
        $clone = clone $this;
        $clone->forcedProvider = $provider;

        return $clone;
    }

    public function send(string $to, string $text, ?string $from = null): SmsResult
    {
        return $this->dispatch(SmsMessage::text($to, $text, $from));
    }

    /**
     * @param  array<string,string|int>  $tokens
     */
    public function sendPattern(string $to, string $pattern, array $tokens = [], ?string $from = null): SmsResult
    {
        return $this->dispatch(SmsMessage::pattern($to, $pattern, $tokens, $from));
    }

    public function queue(string $to, string $text, ?string $from = null): void
    {
        $this->dispatchJob(SmsMessage::text($to, $text, $from));
    }

    /**
     * @param  array<string,string|int>  $tokens
     */
    public function queuePattern(string $to, string $pattern, array $tokens = [], ?string $from = null): void
    {
        $this->dispatchJob(SmsMessage::pattern($to, $pattern, $tokens, $from));
    }

    public function status(string $messageId, string $provider): SmsResult
    {
        return $this->factory->make($provider)->getStatus($messageId);
    }

    public function balance(string $provider): SmsResult
    {
        return $this->factory->make($provider)->getBalance();
    }

    public function factory(): ProviderFactory
    {
        return $this->factory;
    }

    public function dispatch(SmsMessage $message): SmsResult
    {
        $candidates = $this->candidates();

        $attempts = [];
        foreach ($candidates as $providerName) {
            $provider = $this->factory->make($providerName);
            $result = $provider->send($message);

            $weight = (int) (config("sms-switch.providers.{$providerName}.weight") ?? 0);
            $this->recorder->record($message, $result, $weight);

            $attempts[] = $result;

            if ($result->success) {
                return $result;
            }

            // manual mode + forced via() short-circuit: do not fall through.
            if ($this->forcedProvider !== null || $this->mode !== self::MODE_AUTO) {
                return $result;
            }
        }

        throw new AllProvidersFailedException($attempts);
    }

    /**
     * @return list<string>
     */
    private function candidates(): array
    {
        if ($this->forcedProvider !== null) {
            if (! $this->factory->isEnabled($this->forcedProvider)) {
                throw new ProviderNotConfiguredException(
                    "Provider [{$this->forcedProvider}] is not enabled or not configured.",
                );
            }

            return [$this->forcedProvider];
        }

        return $this->selector->candidates();
    }

    private function dispatchJob(SmsMessage $message): void
    {
        $job = new SendSmsJob($message, $this->forcedProvider);

        $connection = $this->queueConfig['connection'] ?? null;
        $queue = $this->queueConfig['queue'] ?? null;

        if ($connection !== null) {
            $job->onConnection((string) $connection);
        }

        if ($queue !== null) {
            $job->onQueue((string) $queue);
        }

        Container::getInstance()->make(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job);
    }
}
