# imanimen/laravel-sms-switch

A minimal, reliability-first Laravel SDK for sending SMS through Iranian providers with **manual selection** or **auto-switching** (score-based + circuit breaker).

Supported providers out of the box:

- **sms.ir** - `POST /v1/send/bulk`, `POST /v1/send/verify`
- **Kavenegar** - `sms/send.json`, `verify/lookup.json`
- **Melipayamak** - `api/send/simple`, `api/send/shared-otp`
- **MOBINSMS** - `/v1/send/quick`, `/v1/send/pattern`

## Requirements

- PHP **8.2+**
- Laravel **11 or 12**

## Install

```bash
composer require imanimen/laravel-sms-switch
php artisan vendor:publish --tag=sms-switch-config
php artisan migrate
```

## Configure

Add credentials to `.env`. Any provider you don't use can be left blank and disabled:

```env
SMS_SWITCH_MODE=manual
SMS_SWITCH_DEFAULT=smsir

SMSIR_API_KEY=...
SMSIR_LINE=30001111

KAVENEGAR_API_KEY=...
KAVENEGAR_SENDER=10001

MELIPAYAMAK_USERNAME=...
MELIPAYAMAK_PASSWORD=...
MELIPAYAMAK_FROM=10001

MOBINSMS_API_KEY=...
MOBINSMS_BASE_URL=https://api.mobinsms.example
MOBINSMS_FROM=9000

SMS_CB_THRESHOLD=5
SMS_CB_OPEN_SECONDS=60
SMS_SCORING_WINDOW_MIN=15
SMS_LOG_RETENTION_DAYS=30
```

`config/sms-switch.php` exposes provider weights, timeouts, scoring weights, and queue routing.

## Usage

```php
use Imanimen\SmsSwitch\Facades\Sms;

// Plain text (uses default_provider in manual mode)
Sms::send('989121234567', 'Your code is 12345');

// Pattern / OTP (each provider maps this to its template API)
Sms::sendPattern('989121234567', 'otp-verify', ['code' => '12345']);

// Force a specific provider (bypasses selector, no fallback)
Sms::via('kavenegar')->send('989121234567', 'hi');

// Queue it
Sms::queue('989121234567', 'hi');
Sms::queuePattern('989121234567', 'otp-verify', ['code' => '12345']);

// Ops
$balance = Sms::balance('smsir');
$status  = Sms::status($messageId, 'mobinsms');
```

Every send returns an `SmsResult` with fields: `success`, `providerName`, `messageId`, `latencyMs`, `error`, `raw`.

In **auto mode**, if the primary provider fails, the SDK falls through to the next-highest-scored healthy provider. In **manual mode** it does not; failures surface immediately.

## Modes

**Manual (default).** Every send goes through `default_provider`. Failures are returned as-is. No fallback. Predictable and boring.

**Auto.** Providers are ranked by a live score:

```
score = 0.6 * success_rate + 0.3 * (1 - avg_latency / max_latency) + 0.1 * (manual_weight / 100)
```

Circuit breaker per provider (state stored inline on `sms_provider_metrics`):

- `closed` - normal traffic. After N consecutive failures, transitions to `open`.
- `open` - skipped by the selector for `open_duration_seconds`, then transitions to `half_open`.
- `half_open` - one probe. Success closes the breaker; failure reopens it with a fresh timer.

## Console commands

- `sms:cleanup --days=30` - prune `sms_logs` rows older than N days (chunked delete).
- `sms:metrics` - table of success/failure/latency/score/circuit per provider.
- `sms:metrics:recompute` - rebuild `sms_provider_metrics` from `sms_logs` (window-limited).
- `sms:test {provider} {to}` - send a canary; useful when onboarding a new API key.

Schedule cleanup daily in `bootstrap/app.php` (Laravel 11+):

```php
->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
    $schedule->command('sms:cleanup')->dailyAt('03:00');
})
```

## Schema

Two tables, kept intentionally minimal:

- **`sms_logs`** - one row per send attempt. Columns: `provider`, `type`, `to`, `body`, `pattern`, `tokens`, `status`, `message_id`, `latency_ms`, `error_message`, `correlation_id`. Rows are grouped by `correlation_id` so every fallback attempt for a single logical send is traceable.
- **`sms_provider_metrics`** - one row per provider. Rolling counters (`success_count`, `failure_count`, `total_latency_ms`, `avg_latency_ms`), current `score`, and circuit-breaker state (`circuit_state`, `circuit_opened_at`, `consecutive_failures`, `half_open_probes`, `window_start`).

## Testing

```bash
composer install
vendor/bin/pest
```

The suite uses Orchestra Testbench with in-memory SQLite and `Http::fake()`. No live HTTP calls.

## Local dev with Workbench

```bash
composer serve
```

Then open `http://localhost:8000/sms` for a JSON overview of config, metrics, and recent logs. See `workbench/routes/web.php` for endpoints like `POST /sms/send`, `POST /sms/via/{provider}`, and `GET /sms/balance/{provider}`.

## Extending with a custom provider

Implement `Imanimen\SmsSwitch\Contracts\SmsProvider` (or extend `AbstractHttpProvider`) and register it:

```php
app(\Imanimen\SmsSwitch\ProviderFactory::class)->extend('mycustom', MyCustomProvider::class);
```

Then reference `driver => 'mycustom'` in `config/sms-switch.php`.

## License

MIT.
