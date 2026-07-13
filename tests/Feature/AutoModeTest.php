<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Imanimen\SmsSwitch\Exceptions\AllProvidersFailedException;
use Imanimen\SmsSwitch\Facades\Sms;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

beforeEach(function () {
    config()->set('sms-switch.mode', 'auto');
    // seed metrics so ordering is deterministic
    SmsProviderMetric::query()->create(['provider' => 'mobinsms', 'score' => 0.9]);
    SmsProviderMetric::query()->create(['provider' => 'smsir', 'score' => 0.5]);
    SmsProviderMetric::query()->create(['provider' => 'kavenegar', 'score' => 0.3]);
    SmsProviderMetric::query()->create(['provider' => 'melipayamak', 'score' => 0.1]);
});

it('picks highest-scored provider first', function (): void {
    Http::fake([
        'mobin.example.test/*' => Http::response(['id' => 1], 200),
        '*'                    => Http::response(['status' => 1, 'data' => ['messageId' => 'x']], 200),
    ]);

    $result = Sms::send('989121234567', 'hi');

    expect($result->success)->toBeTrue()
        ->and($result->providerName)->toBe('mobinsms');
});

it('falls through to the next provider on failure', function (): void {
    Http::fake([
        'mobin.example.test/*'  => Http::response(['message' => 'down'], 500),
        'sms-ir.example.test/*' => Http::response(['status' => 1, 'data' => ['messageId' => 'y']], 200),
    ]);

    $result = Sms::send('989121234567', 'hi');

    expect($result->success)->toBeTrue()
        ->and($result->providerName)->toBe('smsir');
});

it('throws AllProvidersFailedException when all providers fail', function (): void {
    Http::fake([
        '*' => Http::response(['message' => 'nope'], 500),
    ]);

    Sms::send('989121234567', 'hi');
})->throws(AllProvidersFailedException::class);

it('opens the circuit after threshold consecutive failures', function (): void {
    // We drive failures through via() so ScoreSelector doesn't route away from
    // the failing provider before the breaker has a chance to trip.
    Http::fake([
        'mobin.example.test/*' => Http::response(['message' => 'down'], 500),
    ]);

    for ($i = 0; $i < 3; $i++) {
        Sms::via('mobinsms')->send('989121234567', 'hi');
    }

    $metric = SmsProviderMetric::query()->where('provider', 'mobinsms')->first();
    expect($metric->circuit_state)->toBe('open')
        ->and($metric->consecutive_failures)->toBe(3);

    Http::fake([
        'sms-ir.example.test/*' => Http::response(['status' => 1, 'data' => ['messageId' => 'x']], 200),
    ]);

    // In auto mode the tripped mobinsms is skipped; next-best allowed is smsir.
    $result = Sms::send('989121234567', 'hi');
    expect($result->providerName)->toBe('smsir');
});
