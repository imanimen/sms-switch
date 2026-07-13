<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch;

use Imanimen\SmsSwitch\Contracts\SmsProvider;
use Imanimen\SmsSwitch\Exceptions\ProviderNotConfiguredException;
use Imanimen\SmsSwitch\Providers\KavenegarProvider;
use Imanimen\SmsSwitch\Providers\MelipayamakProvider;
use Imanimen\SmsSwitch\Providers\MobinsmsProvider;
use Imanimen\SmsSwitch\Providers\SmsIrProvider;

class ProviderFactory
{
    /**
     * @var array<string,SmsProvider>
     */
    private array $instances = [];

    /**
     * @var array<string,class-string<SmsProvider>>
     */
    private array $drivers = [
        'smsir'       => SmsIrProvider::class,
        'kavenegar'   => KavenegarProvider::class,
        'melipayamak' => MelipayamakProvider::class,
        'mobinsms'    => MobinsmsProvider::class,
    ];

    /**
     * @param  array<string,array<string,mixed>>  $providers
     * @param  array<string,mixed>  $httpDefaults
     */
    public function __construct(
        private readonly array $providers,
        private readonly array $httpDefaults = [],
    ) {
    }

    /**
     * Register or override a driver (e.g. custom provider in tests or downstream apps).
     *
     * @param  class-string<SmsProvider>  $class
     */
    public function extend(string $driver, string $class): void
    {
        $this->drivers[$driver] = $class;
        unset($this->instances[$driver]);
    }

    public function make(string $name): SmsProvider
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $config = $this->providers[$name] ?? null;
        if ($config === null) {
            throw new ProviderNotConfiguredException("SMS provider [{$name}] is not defined in config.");
        }

        $driver = (string) ($config['driver'] ?? $name);
        $class = $this->drivers[$driver] ?? null;
        if ($class === null) {
            throw new ProviderNotConfiguredException("SMS driver [{$driver}] is not registered.");
        }

        return $this->instances[$name] = new $class($config, $this->httpDefaults);
    }

    /**
     * @return list<string>
     */
    public function enabledProviderNames(): array
    {
        $names = [];
        foreach ($this->providers as $name => $config) {
            if ((bool) ($config['enabled'] ?? true)) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    public function isEnabled(string $name): bool
    {
        return isset($this->providers[$name]) && (bool) ($this->providers[$name]['enabled'] ?? true);
    }
}
