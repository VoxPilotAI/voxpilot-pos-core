<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

/** Best-selling menu items in the dashboard's date range. */
class TopItems extends VoxPilotWidget
{
    protected string $partial = 'topitems';

    protected function prepareVars(): void
    {
        $this->vars['items'] = $this->stats()->topItems();
    }
}
