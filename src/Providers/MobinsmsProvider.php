<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Providers;

use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;

class MobinsmsProvider extends AbstractHttpProvider
{
    public function name(): string
    {
        return 'mobinsms';
    }

    public function send(SmsMessage $message): SmsResult
    {
        return $this->measure(function () use ($message): SmsResult {
            $from = $message->from ?? (string) ($this->config['from'] ?? '');

            if ($message->isPattern()) {
                $response = $this->http()
                    ->withHeaders(['X-API-Key' => (string) ($this->config['api_key'] ?? '')])
                    ->post($this->baseUrl() . '/v1/send/pattern', [
                        'from'    => $from,
                        'to'      => [$message->to],
                        'pattern' => (string) $message->pattern,
                        'data'    => $message->tokens,
                    ]);
            } else {
                $response = $this->http()
                    ->withHeaders(['X-API-Key' => (string) ($this->config['api_key'] ?? '')])
                    ->post($this->baseUrl() . '/v1/send/quick', [
                        'from' => $from,
                        'to'   => [$message->to],
                        'body' => (string) $message->body,
                    ]);
            }

            $body = $this->jsonOrEmpty($response);

            if (! $response->successful()) {
                return $this->fail("HTTP {$response->status()}: " . ($body['message'] ?? 'unknown error'), $body);
            }

            $id = isset($body['id']) ? (string) $body['id']
                : (isset($body['message_id']) ? (string) $body['message_id'] : null);

            return $this->ok($id, $body);
        });
    }

    public function getStatus(string $messageId): SmsResult
    {
        return $this->measure(function () use ($messageId): SmsResult {
            $response = $this->http()
                ->withHeaders(['X-API-Key' => (string) ($this->config['api_key'] ?? '')])
                ->get($this->baseUrl() . '/v1/message/status', ['message_id' => $messageId]);

            $body = $this->jsonOrEmpty($response);

            return $response->successful()
                ? $this->ok($messageId, $body)
                : $this->fail("HTTP {$response->status()}", $body);
        });
    }

    public function getBalance(): SmsResult
    {
        return $this->measure(function (): SmsResult {
            $response = $this->http()
                ->withHeaders(['X-API-Key' => (string) ($this->config['api_key'] ?? '')])
                ->get($this->baseUrl() . '/v1/account/balance');

            $body = $this->jsonOrEmpty($response);

            return $response->successful()
                ? $this->ok(null, $body)
                : $this->fail("HTTP {$response->status()}", $body);
        });
    }
}
