<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\Providers\KavenegarProvider;

it('sends plain sms via /sms/send.json', function (): void {
    Http::fake([
        'kavenegar.example.test/v1/*/sms/send.json' => Http::response([
            'return'  => ['status' => 200, 'message' => 'OK'],
            'entries' => [['messageid' => 42]],
        ], 200),
    ]);

    $provider = new KavenegarProvider(
        ['api_key' => 'test-key', 'sender' => '10001', 'base_url' => 'https://kavenegar.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeTrue()
        ->and($result->messageId)->toBe('42');
});

it('sends pattern via /verify/lookup.json with numbered tokens', function (): void {
    Http::fake([
        'kavenegar.example.test/v1/*/verify/lookup.json*' => Http::response([
            'return'  => ['status' => 200],
            'entries' => [['messageid' => 7]],
        ], 200),
    ]);

    $provider = new KavenegarProvider(
        ['api_key' => 'k', 'sender' => '10001', 'base_url' => 'https://kavenegar.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::pattern('989121234567', 'verify', ['a' => '1', 'b' => '2', 'c' => '3']));

    expect($result->success)->toBeTrue();

    Http::assertSent(function ($request) {
        $data = $request->data();
        return $data['template'] === 'verify'
            && $data['token'] === '1'
            && $data['token2'] === '2'
            && $data['token3'] === '3';
    });
});
