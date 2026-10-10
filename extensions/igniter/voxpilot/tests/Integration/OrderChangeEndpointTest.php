<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Cart\Models\Order;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Igniter\VoxPilot\Tests\Concerns\MakesRestaurant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

require_once __DIR__.'/../Concerns/MakesRestaurant.php';

/** A caller changes or cancels their order by phone, only before the kitchen accepts it (SPEC-005). */
class OrderChangeEndpointTest extends TestCase
{
    use DatabaseTransactions;
    use MakesRestaurant;

    protected string $token;

    protected function order(): Order
    {
        Queue::fake();
        $tenant = $this->makeTenant();
        $location = $this->makeLocation($tenant);
        $this->makeMenu($location, 'Pizza Margherita', 10.99);
        $this->makeMenu($location, 'Coca-Cola', 2.5);
        $this->token = TenantApiToken::generateToken(tenantId: $tenant->id, name: 'Change', defaultLocationId: $location->getKey())['plain_text'];

        $this->postJson('/api/voxpilot/orders', [
            'external_order_id' => 'ord_change_1',
            'customer' => ['name' => 'Ana', 'phone' => '+50688887777'],
            'fulfillment' => ['type' => 'pickup'],
            'items' => [['name' => 'Pizza Margherita', 'quantity' => 1]],
        ], $this->auth())->assertStatus(201);

        return Order::withoutGlobalScopes()->latest('order_id')->first();
    }

    protected function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_replaces_the_items_and_totals_before_the_kitchen_accepts(): void
    {
        $order = $this->order();

        $response = $this->putJson('/api/voxpilot/orders/ord_change_1', [
            'fulfillment' => ['type' => 'delivery', 'address' => 'Barrio Escalante'],
            'items' => [['name' => 'Pizza Margherita', 'quantity' => 2], ['name' => 'Coca-Cola', 'quantity' => 1]],
        ], $this->auth());

        $response->assertOk();
        $response->assertJsonPath('data.order_total', 24.48);
        $order->refresh();
        $this->assertSame(Order::DELIVERY, $order->order_type);
        $this->assertStringContainsString('📍 Barrio Escalante', (string) $order->comment);
        $this->assertCount(2, $order->menus);
    }

    public function test_notes_on_the_order_are_in_the_restaurant_language(): void
    {
        $order = $this->order();
        $tenant = Tenant::find($order->tenant_id);
        $tenant->settings = array_merge((array) $tenant->settings, ['locale' => 'es']);
        $tenant->save();

        $this->putJson('/api/voxpilot/orders/ord_change_1', [
            'items' => [['name' => 'Coca-Cola', 'quantity' => 1]],
        ], $this->auth())->assertOk();

        $this->assertStringContainsString('Modificado por el cliente por teléfono', (string) $order->refresh()->comment);
    }

    public function test_rejects_items_not_on_the_menu_and_keeps_the_order(): void
    {
        $order = $this->order();

        $this->putJson('/api/voxpilot/orders/ord_change_1', ['items' => [['name' => 'Lasaña', 'quantity' => 1]]], $this->auth())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ITEMS_UNAVAILABLE')
            ->assertJsonPath('error.unmapped.0.name', 'Lasaña');
        $this->assertSame('Pizza Margherita', $order->fresh()->menus->first()->name);
    }

    public function test_cancels_and_then_the_order_is_locked(): void
    {
        $order = $this->order();

        $this->postJson('/api/voxpilot/orders/ord_change_1/cancel', [], $this->auth())->assertOk();
        $this->assertTrue($order->fresh()->isCanceled());
        $this->postJson('/api/voxpilot/orders/ord_change_1/cancel', [], $this->auth())
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ORDER_LOCKED');
    }

    public function test_an_accepted_order_or_another_tenants_order_cannot_change(): void
    {
        $order = $this->order();
        $order->updateOrderStatus(2);

        $this->postJson('/api/voxpilot/orders/ord_change_1/cancel', [], $this->auth())->assertStatus(409);

        $other = $this->makeTenant('Other');
        $otherToken = TenantApiToken::generateToken(tenantId: $other->id, name: 'Other')['plain_text'];
        $this->postJson('/api/voxpilot/orders/ord_change_1/cancel', [], ['Authorization' => 'Bearer '.$otherToken])->assertStatus(404);
    }

    public function test_stores_the_call_conversation_for_the_order_modal(): void
    {
        $order = $this->order();

        $this->putJson('/api/voxpilot/orders/ord_change_1/transcript', ['turns' => [
            ['role' => 'assistant', 'content' => 'Hola, Bella Napoli, ¿qué deseas?'],
            ['role' => 'caller', 'content' => 'Una margherita mediana.'],
            ['role' => 'system', 'content' => 'hidden'],
            ['role' => 'caller', 'content' => '  '],
        ]], $this->auth())->assertOk()->assertJsonPath('data.turns', 2);

        $stored = \Igniter\VoxPilot\Models\VoxPilotOrderMetadata::where('order_id', $order->order_id)->value('transcript');
        $turns = \Igniter\VoxPilot\Http\Controllers\Concerns\OrderQuickActions::transcriptTurns($stored);
        $this->assertSame(['assistant', 'caller'], array_column($turns, 'role'));
        $this->assertSame('Una margherita mediana.', $turns[1]['content']);
        $this->assertSame([['role' => 'assistant', 'content' => 'Confirmed order']], \Igniter\VoxPilot\Http\Controllers\Concerns\OrderQuickActions::transcriptTurns('Confirmed order'));

        $this->putJson('/api/voxpilot/orders/ord_unknown/transcript', ['turns' => [['role' => 'caller', 'content' => 'x']]], $this->auth())->assertStatus(404);
        $this->putJson('/api/voxpilot/orders/ord_change_1/transcript', ['turns' => []], $this->auth())->assertStatus(422);
    }
}
