<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

/** Orders per hour of day, split between the AI phone assistant and other channels. */
class OrdersByHour extends VoxPilotWidget
{
    protected string $partial = 'ordersbyhour';

    protected function prepareVars(): void
    {
        $hours = $this->stats()->byHour();
        $max = max(1, ...array_map(fn ($h) => $h['phone'] + $h['other'], $hours ?: [['phone' => 0, 'other' => 0]]));
        $this->vars['hours'] = array_map(fn ($h) => $h + [
            'phoneHeight' => (int) round($h['phone'] / $max * 100),
            'otherHeight' => (int) round($h['other'] / $max * 100),
        ], $hours);
    }
}
