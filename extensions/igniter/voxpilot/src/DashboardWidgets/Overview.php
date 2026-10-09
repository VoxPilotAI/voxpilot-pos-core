<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

/** Revenue, orders, average ticket and the share ordered through the AI phone assistant. */
class Overview extends VoxPilotWidget
{
    protected string $partial = 'overview';

    protected function prepareVars(): void
    {
        $this->vars['kpis'] = $this->stats()->kpis();
    }
}
