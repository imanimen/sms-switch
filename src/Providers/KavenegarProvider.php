<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Providers;

use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;

/**
 * Kavenegar REST
 *
 * - Plain: GET|POST /v1/{API-KEY}/sms/send.json?sender=&receptor=&message=
 * - Pattern (OTP lookup): GET|POST /v1/{API-KEY}/verify/lookup.json?receptor=&template=&token=&token2=...
 * - Balance: GET /v1/{API-KEY}/account/info.json
 */
class KavenegarProvider extends AbstractHttpProvider
{
    public function name(): string
    {
        return 'kavenegar';
    }

    public function send(SmsMessage $message): SmsResult
    {
        return $this->measure(function () use ($message): SmsResult {
            $apiKey = (string) ($this->config['api_key'] ?? '');
            $sender = $message->from ?? (string) ($this->config['sender'] ?? '');

            if ($message->isPattern()) {
                $query = ['receptor' => $message->to, 'template' => (string) $message->pattern];
                $i = 1;
                foreach ($message->tokens as $value) {
                    $key = $i === 1 ? 'token' : 'token' . $i;
                    $query[$key] = (string) $value;
                    $i++;
                }

                $response = $this->http()->get(
                    $this->baseUrl() . "/v1/{$apiKey}/verify/lookup.json",
                    $query,
                );
            } else {
                $response = $this->http()->asForm()->post(
                    $this->baseUrl() . "/v1/{$apiKey}/sms/send.json",
                    [
                        'sender'   => $sender,
                        'receptor' => $message->to,
                        'message'  => (string) $message->body,
                    ],
                );
            }

            $body = $this->jsonOrEmpty($response);
            $status = (int) ($body['return']['status'] ?? 0);

            if (! $response->successful() || $status !== 200) {
                return $this->fail(
                    "kavenegar error: " . ($body['return']['message'] ?? "HTTP {$response->status()}"),
                    $body,
                );
            }

            $entries = $body['entries'] ?? [];
            $id = is_array($entries) && isset($entries[0]['messageid']) ? (string) $entries[0]['messageid'] : null;

            return $this->ok($id, $body);
        });
    }

    public function getStatus(string $messageId): SmsResult
    {
        return $this->measure(function () use ($messageId): SmsResult {
            $apiKey = (string) ($this->config['api_key'] ?? '');

            $response = $this->http()->get(
                $this->baseUrl() . "/v1/{$apiKey}/sms/status.json",
                ['messageid' => $messageId],
            );

            $body = $this->jsonOrEmpty($response);

            return $response->successful()
                ? $this->ok($messageId, $body)
                : $this->fail("HTTP {$response->status()}", $body);
        });
    }

    public function getBalance(): SmsResult
    {
        return $this->measure(function (): SmsResult {
            $apiKey = (string) ($this->config['api_key'] ?? '');

            $response = $this->http()->get($this->baseUrl() . "/v1/{$apiKey}/account/info.json");
            $body = $this->jsonOrEmpty($response);

            return $response->successful()
                ? $this->ok(null, $body)
                : $this->fail("HTTP {$response->status()}", $body);
        });
    }
}
