<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Console;

use Illuminate\Console\Command;
use Imanimen\SmsSwitch\SmsManager;

class TestProviderCommand extends Command
{
    protected $signature = 'sms:test
        {provider : Provider name (e.g. smsir, kavenegar, melipayamak, mobinsms)}
        {to : Destination phone number}
        {--text=Test message from Imanimen/laravel-sms-switch}
        {--pattern= : Pattern/template code (skips --text if provided)}
        {--token=* : key=value pairs passed as pattern tokens}';

    protected $description = 'Send a canary SMS through a specific provider and print the result.';

    public function handle(SmsManager $manager): int
    {
        $provider = (string) $this->argument('provider');
        $to = (string) $this->argument('to');
        $pattern = $this->option('pattern');
        $tokens = $this->parseTokens();

        $manager = $manager->via($provider);

        $result = $pattern
            ? $manager->sendPattern($to, (string) $pattern, $tokens)
            : $manager->send($to, (string) $this->option('text'));

        $this->line('Provider   : ' . $result->providerName);
        $this->line('Success    : ' . ($result->success ? 'yes' : 'no'));
        $this->line('Message ID : ' . ($result->messageId ?? '-'));
        $this->line('Latency ms : ' . $result->latencyMs);
        $this->line('Error      : ' . ($result->error ?? '-'));
        $this->line('Raw        : ' . json_encode($result->raw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $result->success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<string,string>
     */
    private function parseTokens(): array
    {
        $tokens = [];
        foreach ((array) $this->option('token') as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $pair, 2);
            $tokens[trim($k)] = trim($v);
        }
        return $tokens;
    }
}
