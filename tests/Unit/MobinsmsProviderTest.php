<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\DTO\SmsMessage;
use Imanimen\SmsSwitch\Providers\MobinsmsProvider;

it('sends plain text with the expected shape', function (): void {
    Http::fake([
        'mobin.example.test/v1/send/quick' => Http::response(['id' => 1234, 'unique_id' => ''], 200),
    ]);

    $provider = new MobinsmsProvider(
        ['api_key' => 'k', 'from' => '9000', 'base_url' => 'https://mobin.example.test'],
        ['timeout' => 5, 'connect_timeout' => 2],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeTrue()
        ->and($result->messageId)->toBe('1234');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://mobin.example.test/v1/send/quick'
            && $request->hasHeader('X-API-Key', 'k')
            && $request['from'] === '9000'
            && $request['to'] === ['989121234567']
            && $request['body'] === 'hi';
    });
});

it('sends pattern with data map', function (): void {
    Http::fake([
        'mobin.example.test/v1/send/pattern' => Http::response(['id' => 99], 200),
    ]);

    $provider = new MobinsmsProvider(
        ['api_key' => 'k', 'from' => '9000', 'base_url' => 'https://mobin.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::pattern('989121234567', 'otp-verify', ['0' => 'A', '1' => 'B']));

    expect($result->success)->toBeTrue()
        ->and($result->messageId)->toBe('99');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/v1/send/pattern')
            && $request['pattern'] === 'otp-verify'
            && $request['data'] === ['0' => 'A', '1' => 'B'];
    });
});

it('marks non-2xx as failure', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'bad key'], 401),
    ]);

    $provider = new MobinsmsProvider(
        ['api_key' => 'k', 'from' => '9000', 'base_url' => 'https://mobin.example.test'],
        [],
    );

    $result = $provider->send(SmsMessage::text('989121234567', 'hi'));

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('HTTP 401');
});
