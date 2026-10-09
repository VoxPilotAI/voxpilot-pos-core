<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\DashboardWidgets;

use Igniter\Admin\Classes\BaseDashboardWidget;
use Igniter\VoxPilot\Services\DashboardStats;

/**
 * Base for the VoxPilot POS dashboard widgets: the dashboard's date range and its order figures.
 */
abstract class VoxPilotWidget extends BaseDashboardWidget
{
    /** Partial folder and file under resources/views/_partials/dashboardwidgets. */
    protected string $partial = '';

    protected function stats(): DashboardStats
    {
        return DashboardStats::forRange($this->getStartDate(), $this->getEndDate());
    }

    public function defineProperties(): array
    {
        return [];
    }

    public function render(): string
    {
        $this->prepareVars();

        return $this->makePartial($this->partial.'/'.$this->partial);
    }

    abstract protected function prepareVars(): void;
}
