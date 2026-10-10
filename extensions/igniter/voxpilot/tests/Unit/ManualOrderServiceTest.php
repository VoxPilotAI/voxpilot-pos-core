<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Unit;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\VoxPilotOrderMetadata;
use Igniter\VoxPilot\Services\ManualOrderService;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** Quick manual order from the board: a normal POS order, not an AI phone order. */
class ManualOrderServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    public function test_creates_the_order_with_lines_and_totals_without_voxpilot_metadata(): void
    {
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $pizza = $this->makeMenu($location, 'Margherita', 8.5);
        $drink = $this->makeMenu($location, 'Agua', 1.25);

        $order = (new ManualOrderService())->create($location, [
            'customer_name' => 'Ana María',
            'telephone' => '+50688887777',
            'type' => 'pickup',
            'notes' => 'Sin cebolla',
            'items' => [['menu_id' => $pizza->getKey(), 'quantity' => 2], ['menu_id' => $drink->getKey(), 'quantity' => '3']],
        ]);

        $this->assertSame((int) $tenant->id, (int) $order->tenant_id);
        $this->assertSame((int) $location->getKey(), (int) $order->location_id);
        $this->assertSame('Ana', $order->first_name);
        $this->assertSame(Order::COLLECTION, $order->order_type);
        $this->assertSame(5, (int) $order->total_items);
        $this->assertEqualsWithDelta(20.75, (float) $order->order_total, 0.001);
        $this->assertCount(2, $order->menus);
        $this->assertFalse(VoxPilotOrderMetadata::where('order_id', $order->order_id)->exists());
    }

    public function test_rejects_items_that_are_not_on_the_location_menu(): void
    {
        $location = $this->makeLocation($this->makeTenant());
        $foreign = $this->makeMenu($this->makeLocation($this->makeTenant('Other')), 'Foreign');

        $this->expectException(ValidationException::class);

        (new ManualOrderService())->create($location, ['type' => 'pickup', 'items' => [['menu_id' => $foreign->getKey(), 'quantity' => 1]]]);
    }

    public function test_delivery_needs_an_address_and_at_least_one_item(): void
    {
        $location = $this->makeLocation($this->makeTenant());

        try {
            (new ManualOrderService())->create($location, ['type' => 'delivery', 'items' => []]);
            $this->fail('Expected a validation error');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('address', $e->errors());
            $this->assertArrayHasKey('items', $e->errors());
        }
    }
}
