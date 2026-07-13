<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Providers;

use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\DTO\SmsResult;

/**
 * Melipayamak REST (v2)
 *
 * Base URL: https://console.melipayamak.com
 *
 * - Plain: POST /api/send/simple/{token}  {from, to, text}
 * - Pattern (shared OTP): POST /api/send/shared-otp/{token}  {to, args}
 * - Balance: GET /api/receive/credit/{token}
 *
 * The token is derived by melipayamak as md5(username:password) but is provided
 * to consumers directly. If `api_token` is set we use it, otherwise username+password
 * combined into base auth.
 */
class MelipayamakProvider extends AbstractHttpProvider
{
    public function name(): string
    {
        return 'melipayamak';
    }

    public function send(SmsMessage $message): SmsResult
    {
        return $this->measure(function () use ($message): SmsResult {
            $token = $this->authToken();
            $from = $message->from ?? (string) ($this->config['from'] ?? '');

            if ($message->isPattern()) {
                $args = array_values(array_map(fn ($v) => (string) $v, $message->tokens));

                $response = $this->http()->post(
                    $this->baseUrl() . "/api/send/shared-otp/{$token}",
                    ['to' => $message->to, 'args' => $args, 'bodyId' => $message->pattern],
                );
            } else {
                $response = $this->http()->post(
                    $this->baseUrl() . "/api/send/simple/{$token}",
                    ['from' => $from, 'to' => $message->to, 'text' => (string) $message->body],
                );
            }

            $body = $this->jsonOrEmpty($response);

            if (! $response->successful()) {
                return $this->fail("HTTP {$response->status()}: " . ($body['status'] ?? ''), $body);
            }

            $status = (string) ($body['status'] ?? '');
            $recId = $body['recId'] ?? null;

            if ($recId === null && $status !== '' && ! in_array(strtolower($status), ['ok', 'success', 'ارسال موفق'], true)) {
                return $this->fail("melipayamak error: {$status}", $body);
            }

            return $this->ok($recId !== null ? (string) $recId : null, $body);
        });
    }

    public function getStatus(string $messageId): SmsResult
    {
        return $this->measure(function () use ($messageId): SmsResult {
            $token = $this->authToken();

            $response = $this->http()->post(
                $this->baseUrl() . "/api/receive/status/{$token}",
                ['recId' => $messageId],
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
            $token = $this->authToken();

            $response = $this->http()->get($this->baseUrl() . "/api/receive/credit/{$token}");
            $body = $this->jsonOrEmpty($response);

            return $response->successful()
                ? $this->ok(null, $body)
                : $this->fail("HTTP {$response->status()}", $body);
        });
    }

    private function authToken(): string
    {
        $token = (string) ($this->config['api_token'] ?? '');
        if ($token !== '') {
            return $token;
        }

        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        return md5($username . ':' . $password);
    }
}
