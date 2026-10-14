<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

use Igniter\VoxPilot\Services\DashboardStats;

/** Close of the day: today's takings by payment and order type, phone orders and cancellations. */
class DaySummary extends VoxPilotWidget
{
    protected string $partial = 'daysummary';

    protected function prepareVars(): void
    {
        $this->vars['summary'] = DashboardStats::forRange(now(), now())->daySummary();
    }
}
