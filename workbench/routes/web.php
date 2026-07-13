<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Imanimen\SmsSwitch\Facades\Sms;
use Imanimen\SmsSwitch\Models\SmsLog;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

/*
| Workbench routes — poke at the SDK from the browser while iterating.
|
|   GET  /sms                     -> overview: config + metrics + recent logs
|   POST /sms/send?to=&text=      -> Sms::send()
|   POST /sms/pattern?to=&pattern=&tokens[]=  -> Sms::sendPattern()
|   POST /sms/via/{provider}?to=&text=        -> Sms::via()->send()
|   GET  /sms/balance/{provider}              -> Sms::balance()
*/

Route::get('/sms', function () {
    return response()->json([
        'mode'             => config('sms-switch.mode'),
        'default_provider' => config('sms-switch.default_provider'),
        'providers'        => array_map(
            fn ($cfg) => ['driver' => $cfg['driver'] ?? null, 'enabled' => $cfg['enabled'] ?? true, 'weight' => $cfg['weight'] ?? 0],
            (array) config('sms-switch.providers', []),
        ),
        'metrics'          => SmsProviderMetric::query()->orderByDesc('score')->get(),
        'recent_logs'      => SmsLog::query()->latest()->limit(20)->get(),
    ], 200, [], JSON_PRETTY_PRINT);
});

Route::post('/sms/send', function () {
    $to = (string) request('to', '');
    $text = (string) request('text', 'Hello from workbench');
    return response()->json(Sms::send($to, $text));
});

Route::post('/sms/pattern', function () {
    $to = (string) request('to', '');
    $pattern = (string) request('pattern', '');
    $tokens = (array) request('tokens', []);
    return response()->json(Sms::sendPattern($to, $pattern, $tokens));
});

Route::post('/sms/via/{provider}', function (string $provider) {
    $to = (string) request('to', '');
    $text = (string) request('text', 'Hello from workbench (forced provider)');
    return response()->json(Sms::via($provider)->send($to, $text));
});

Route::get('/sms/balance/{provider}', function (string $provider) {
    return response()->json(Sms::balance($provider));
});
