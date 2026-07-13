<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Providers;

use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;

/**
 * sms.ir REST v1
 *
 * - Plain text: POST /v1/send/bulk    { lineNumber, messageText, mobiles: [...] }
 * - Pattern/OTP: POST /v1/send/verify { mobile, templateId, parameters: [{name,value}] }
 * - Balance: GET /v1/credit
 *
 * Auth: X-API-KEY header.
 */
class SmsIrProvider extends AbstractHttpProvider
{
    public function name(): string
    {
        return 'smsir';
    }

    public function send(SmsMessage $message): SmsResult
    {
        return $this->measure(function () use ($message): SmsResult {
            $headers = ['X-API-KEY' => (string) ($this->config['api_key'] ?? '')];

            if ($message->isPattern()) {
                $parameters = [];
                foreach ($message->tokens as $name => $value) {
                    $parameters[] = ['name' => (string) $name, 'value' => (string) $value];
                }

                $response = $this->http()->withHeaders($headers)->post(
                    $this->baseUrl() . '/v1/send/verify',
                    [
                        'mobile'     => $message->to,
                        'templateId' => (int) $message->pattern,
                        'parameters' => $parameters,
                    ],
                );
            } else {
                $response = $this->http()->withHeaders($headers)->post(
                    $this->baseUrl() . '/v1/send/bulk',
                    [
                        'lineNumber'  => $message->from ?? ($this->config['line_number'] ?? null),
                        'messageText' => (string) $message->body,
                        'mobiles'     => [$message->to],
                    ],
                );
            }

            $body = $this->jsonOrEmpty($response);

            if (! $response->successful() || (int) ($body['status'] ?? 0) !== 1) {
                return $this->fail(
                    "sms.ir error: " . ($body['message'] ?? "HTTP {$response->status()}"),
                    $body,
                );
            }

            $data = $body['data'] ?? [];
            $id = null;
            if (is_array($data)) {
                $id = (string) ($data['messageId'] ?? $data['messageIds'][0] ?? '') ?: null;
            }

            return $this->ok($id, $body);
        });
    }

    public function getStatus(string $messageId): SmsResult
    {
        return $this->measure(function () use ($messageId): SmsResult {
            $response = $this->http()
                ->withHeaders(['X-API-KEY' => (string) ($this->config['api_key'] ?? '')])
                ->get($this->baseUrl() . '/v1/send/' . urlencode($messageId));

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
                ->withHeaders(['X-API-KEY' => (string) ($this->config['api_key'] ?? '')])
                ->get($this->baseUrl() . '/v1/credit');

            $body = $this->jsonOrEmpty($response);

            return $response->successful()
                ? $this->ok(null, $body)
                : $this->fail("HTTP {$response->status()}", $body);
        });
    }
}
