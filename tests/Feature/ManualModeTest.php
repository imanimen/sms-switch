<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\Facades\Sms;
use Imanimen\SmsSwitch\Models\SmsLog;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

beforeEach(function () {
    config()->set('sms-switch.mode', 'manual');
    config()->set('sms-switch.default_provider', 'mobinsms');
});

it('sends via the default provider and records metrics', function (): void {
    Http::fake([
        'mobin.example.test/v1/send/quick' => Http::response(['id' => 1], 200),
    ]);

    $result = Sms::send('989121234567', 'hi');

    expect($result->success)->toBeTrue()
        ->and($result->providerName)->toBe('mobinsms');

    expect(SmsLog::query()->count())->toBe(1);
    expect(SmsProviderMetric::query()->where('provider', 'mobinsms')->first()->success_count)->toBe(1);
});

it('does NOT fall back on failure in manual mode', function (): void {
    Http::fake([
        'mobin.example.test/*'  => Http::response(['message' => 'bad'], 500),
        'sms-ir.example.test/*' => Http::response(['status' => 1, 'data' => ['messageId' => 'x']], 200),
    ]);

    $result = Sms::send('989121234567', 'hi');

    expect($result->success)->toBeFalse()
        ->and($result->providerName)->toBe('mobinsms');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'mobin.example.test'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'sms-ir.example.test'));

    expect(SmsProviderMetric::query()->where('provider', 'smsir')->count())->toBe(0);
});

it('respects Sms::via() to override provider', function (): void {
    Http::fake([
        'sms-ir.example.test/v1/send/bulk' => Http::response(['status' => 1, 'data' => ['messageIds' => [7]]], 200),
    ]);

    $result = Sms::via('smsir')->send('989121234567', 'hi');

    expect($result->success)->toBeTrue()
        ->and($result->providerName)->toBe('smsir');
});
