<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Imanimen\SmsSwitch\CircuitBreaker\CircuitBreaker;
use Imanimen\SmsSwitch\Console\CleanupLogsCommand;
use Imanimen\SmsSwitch\Console\RecomputeMetricsCommand;
use Imanimen\SmsSwitch\Console\ShowMetricsCommand;
use Imanimen\SmsSwitch\Console\TestProviderCommand;
use Imanimen\SmsSwitch\Contracts\ProviderSelector;
use Imanimen\SmsSwitch\Metrics\MetricsRecorder;
use Imanimen\SmsSwitch\Selection\ManualSelector;
use Imanimen\SmsSwitch\Selection\ScoreSelector;

class SmsSwitchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sms-switch.php', 'sms-switch');

        $this->app->singleton(CircuitBreaker::class, fn (Application $app) => new CircuitBreaker(
            (array) $app['config']->get('sms-switch.circuit_breaker', [])
        ));

        $this->app->singleton(MetricsRecorder::class, fn (Application $app) => new MetricsRecorder(
            $app->make(CircuitBreaker::class),
            (array) $app['config']->get('sms-switch.scoring', []),
            (bool) $app['config']->get('sms-switch.logging.enabled', true),
        ));

        $this->app->singleton(ProviderSelector::class, function (Application $app) {
            $mode = (string) $app['config']->get('sms-switch.mode', 'manual');

            return $mode === 'auto'
                ? new ScoreSelector((array) $app['config']->get('sms-switch.providers', []))
                : new ManualSelector(
                    (string) $app['config']->get('sms-switch.default_provider', ''),
                    (array) $app['config']->get('sms-switch.providers', []),
                );
        });

        $this->app->singleton(ProviderFactory::class, fn (Application $app) => new ProviderFactory(
            (array) $app['config']->get('sms-switch.providers', []),
            (array) $app['config']->get('sms-switch.http', []),
        ));

        $this->app->singleton(SmsManager::class, fn (Application $app) => new SmsManager(
            $app->make(ProviderFactory::class),
            $app->make(ProviderSelector::class),
            $app->make(MetricsRecorder::class),
            (string) $app['config']->get('sms-switch.mode', 'manual'),
            (array) $app['config']->get('sms-switch.queue', []),
        ));

        $this->app->alias(SmsManager::class, 'sms-switch');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sms-switch.php' => config_path('sms-switch.php'),
            ], 'sms-switch-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'sms-switch-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->commands([
                CleanupLogsCommand::class,
                RecomputeMetricsCommand::class,
                ShowMetricsCommand::class,
                TestProviderCommand::class,
            ]);
        }
    }
}
