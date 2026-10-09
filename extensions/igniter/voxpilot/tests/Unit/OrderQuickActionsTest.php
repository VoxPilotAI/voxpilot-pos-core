<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\VoxPilot\Http\Controllers\Concerns\OrderQuickActions;
use Tests\TestCase;

/** Order statuses shown on the board, the live screen and the order modal follow the admin's language. */
class OrderQuickActionsTest extends TestCase
{
    public function test_translates_the_default_tastyigniter_status_names(): void
    {
        app()->setLocale('es');

        $this->assertSame('En preparación', OrderQuickActions::statusLabel('Preparation'));
        $this->assertSame('En camino', OrderQuickActions::statusLabel('Delivery'));
        $this->assertSame('Cancelado', OrderQuickActions::statusLabel('Canceled'));
    }

    public function test_keeps_custom_status_names_as_they_are(): void
    {
        app()->setLocale('de');

        $this->assertSame('Bereit an der Theke', OrderQuickActions::statusLabel('Bereit an der Theke'));
        $this->assertSame('', OrderQuickActions::statusLabel(null));
    }
}
