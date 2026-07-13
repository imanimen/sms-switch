<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Imanimen\SmsSwitch\Models\SmsProviderMetric;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['smsir', 'kavenegar', 'melipayamak', 'mobinsms'] as $provider) {
            SmsProviderMetric::query()->firstOrCreate(
                ['provider' => $provider],
                ['score' => 0.0, 'window_start' => now()],
            );
        }
    }
}
