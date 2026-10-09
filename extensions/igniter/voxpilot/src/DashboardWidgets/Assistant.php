<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

use Igniter\VoxPilot\Services\VoxPilotApiClient;

/** The connected VoxPilot assistant: calls answered, calls that became orders, average call. */
class Assistant extends VoxPilotWidget
{
    protected string $partial = 'assistant';

    protected function prepareVars(): void
    {
        $stats = app(VoxPilotApiClient::class)->assistantStats($this->stats()->start(), $this->stats()->end());

        $this->vars['stats'] = $stats;
        $this->vars['phoneOrders'] = $this->stats()->phoneOrders();
        $this->vars['conversion'] = $stats && ($stats['calls']['answered'] ?? 0) > 0 ? (int) min(100, round(($stats['conversionRate'] ?? 0) * 100)) : null;
        $this->vars['avgCall'] = $stats && ($stats['calls']['avgDurationSeconds'] ?? 0) > 0 ? sprintf('%d:%02d', intdiv((int) $stats['calls']['avgDurationSeconds'], 60), $stats['calls']['avgDurationSeconds'] % 60) : '—';
    }
}
