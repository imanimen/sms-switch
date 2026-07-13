<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\Providers\SmsIrProvider;

it('sends bulk plain text', function (): void {
    Http::fake([
        'sms-ir.example.test/v1/send/bulk' => Http::response(['status' => 1, 'data' => ['messageIds' => [77]]], 200),
    ]);

    $provider = new SmsIrProvider(
        ['api_key' => 'k', 'line_number' => '9000', 'base_url' => 'https://sms-ir.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeTrue()
        ->and($result->messageId)->toBe('77');

    Http::assertSent(fn ($r) => $r->hasHeader('X-API-KEY', 'k')
        && $r['lineNumber'] === '9000'
        && $r['messageText'] === 'hi'
        && $r['mobiles'] === ['989121234567']);
});

it('sends verify/OTP pattern', function (): void {
    Http::fake([
        'sms-ir.example.test/v1/send/verify' => Http::response(['status' => 1, 'data' => ['messageId' => '55']], 200),
    ]);

    $provider = new SmsIrProvider(
        ['api_key' => 'k', 'line_number' => '9000', 'base_url' => 'https://sms-ir.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::pattern('989121234567', '123', ['code' => '9999']));

    expect($result->success)->toBeTrue()
        ->and($result->messageId)->toBe('55');

    Http::assertSent(fn ($r) => $r['templateId'] === 123
        && $r['mobile'] === '989121234567'
        && $r['parameters'] === [['name' => 'code', 'value' => '9999']]);
});

it('marks status != 1 as failure', function (): void {
    Http::fake([
        '*' => Http::response(['status' => 0, 'message' => 'invalid key'], 200),
    ]);

    $provider = new SmsIrProvider(
        ['api_key' => 'k', 'line_number' => '9000', 'base_url' => 'https://sms-ir.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('invalid key');
});
