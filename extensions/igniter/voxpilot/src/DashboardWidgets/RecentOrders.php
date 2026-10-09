<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

/** The latest orders with their source (AI phone or not) and status. */
class RecentOrders extends VoxPilotWidget
{
    protected string $partial = 'recentorders';

    protected function prepareVars(): void
    {
        $this->vars['orders'] = $this->stats()->recent();
    }
}
