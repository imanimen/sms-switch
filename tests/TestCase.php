<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Imanimen\SmsSwitch\SmsSwitchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [SmsSwitchServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Sms' => \Imanimen\SmsSwitch\Facades\Sms::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('sms-switch.mode', 'manual');
        $app['config']->set('sms-switch.default_provider', 'mobinsms');
        $app['config']->set('sms-switch.providers.mobinsms', [
            'driver'   => 'mobinsms',
            'api_key'  => 'test-key',
            'from'     => '989000000',
            'base_url' => 'https://mobin.example.test',
            'weight'   => 100,
            'enabled'  => true,
        ]);
        $app['config']->set('sms-switch.providers.smsir', [
            'driver'      => 'smsir',
            'api_key'     => 'test-key',
            'line_number' => '9000',
            'base_url'    => 'https://sms-ir.example.test',
            'weight'      => 80,
            'enabled'     => true,
        ]);
        $app['config']->set('sms-switch.providers.kavenegar', [
            'driver'   => 'kavenegar',
            'api_key'  => 'test-key',
            'sender'   => '10001',
            'base_url' => 'https://kavenegar.example.test',
            'weight'   => 60,
            'enabled'  => true,
        ]);
        $app['config']->set('sms-switch.providers.melipayamak', [
            'driver'    => 'melipayamak',
            'api_token' => 'abc',
            'from'      => '10001',
            'base_url'  => 'https://melipayamak.example.test',
            'weight'    => 40,
            'enabled'   => true,
        ]);

        $app['config']->set('sms-switch.circuit_breaker.failure_threshold', 3);
        $app['config']->set('sms-switch.circuit_breaker.open_duration_seconds', 60);
        $app['config']->set('sms-switch.circuit_breaker.half_open_success_threshold', 1);
    }
}
