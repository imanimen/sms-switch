<?php

declare(strict_types=1);

namespace Imanimen\SmsSwitch\Selection;

use Illuminate\Support\Facades\App;
use Imanimen\SmsSwitch\CircuitBreaker\CircuitBreaker;
use Imanimen\SmsSwitch\Contracts\ProviderSelector;
use Imanimen\SmsSwitch\Metrics\MetricsRecorder;

class ScoreSelector implements ProviderSelector
{
    /**
     * @param  array<string,array<string,mixed>>  $providers
     */
    public function __construct(private readonly array $providers)
    {
    }

    public function candidates(): array
    {
        $recorder = App::make(MetricsRecorder::class);
        $breaker = App::make(CircuitBreaker::class);

        $entries = [];
        foreach ($this->providers as $name => $config) {
            if (! (bool) ($config['enabled'] ?? true)) {
                continue;
            }

            $metric = $recorder->metric((string) $name);
            $allowed = $breaker->allows($metric);

            $entries[] = [
                'name'    => (string) $name,
                'score'   => (float) $metric->score,
                'weight'  => (int) ($config['weight'] ?? 0),
                'allowed' => $allowed,
            ];
        }

        // Order: allowed-first, then by score desc, then by weight desc as tie-breaker.
        usort($entries, static function (array $a, array $b): int {
            if ($a['allowed'] !== $b['allowed']) {
                return $a['allowed'] ? -1 : 1;
            }
            if ($a['score'] === $b['score']) {
                return $b['weight'] <=> $a['weight'];
            }
            return $b['score'] <=> $a['score'];
        });

        return array_values(array_map(static fn (array $e) => $e['name'], array_filter($entries, static fn (array $e) => $e['allowed'])));
    }
}
