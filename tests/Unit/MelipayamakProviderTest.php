<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\Providers\MelipayamakProvider;

it('sends simple text with token in path', function (): void {
    Http::fake([
        'melipayamak.example.test/api/send/simple/abc' => Http::response(['recId' => 1500, 'status' => 'ok'], 200),
    ]);

    $provider = new MelipayamakProvider(
        ['api_token' => 'abc', 'from' => '10001', 'base_url' => 'https://melipayamak.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeTrue()
        ->and($result->messageId)->toBe('1500');

    Http::assertSent(fn ($r) => $r['from'] === '10001'
        && $r['to'] === '989121234567'
        && $r['text'] === 'hi');
});

it('falls back to md5(user:pass) when no api_token is set', function (): void {
    $expectedToken = md5('u:p');

    Http::fake([
        "melipayamak.example.test/api/send/simple/{$expectedToken}" => Http::response(['recId' => 1, 'status' => 'ok'], 200),
    ]);

    $provider = new MelipayamakProvider(
        ['username' => 'u', 'password' => 'p', 'from' => '10001', 'base_url' => 'https://melipayamak.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeTrue();
});
