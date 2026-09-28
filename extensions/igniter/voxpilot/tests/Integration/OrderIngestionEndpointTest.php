<?php

declare(strict_types=1);

namespace Igniter\VoxPilot\Tests\Integration;

use Igniter\Local\Models\Location;
use Igniter\VoxPilot\Models\Tenant;
use Igniter\VoxPilot\Models\TenantApiToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderIngestionEndpointTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected string $plainToken;
    protected int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Integration Tenant',
            'slug' => 'integration-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $location = Location::first();
        if ($location) {
            $location->tenant_id = $this->tenant->id;
            $location->save();
            $this->locationId = $location->location_id;
        }

        $result = TenantApiToken::generateToken(
            tenantId: $this->tenant->id,
            name: 'Test Token',
            defaultLocationId: $this->locationId ?? null,
        );

        $this->plainToken = $result['plain_text'];
    }

    public function test_missing_token_returns_401(): void
    {
        $response = $this->postJson('/api/voxpilot/orders', $this->validPayload());
        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'AUTHENTICATION_FAILED');
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->postJson('/api/voxpilot/orders', $this->validPayload(), [
            'Authorization' => 'Bearer 999|totally_invalid_token',
        ]);
        $response->assertStatus(401);
    }

    public function test_revoked_token_returns_401(): void
    {
        $result = TenantApiToken::generateToken(
            tenantId: $this->tenant->id,
            name: 'Revoked Token',
        );

        $result['token']->revoke();

        $response = $this->postJson('/api/voxpilot/orders', $this->validPayload(), [
            'Authorization' => 'Bearer ' . $result['plain_text'],
        ]);
        $response->assertStatus(401);
        $response->assertJsonPath('error.message', 'Token has been revoked.');
    }

    public function test_valid_request_creates_order(): void
    {
        if (!isset($this->locationId)) {
            $this->markTestSkipped('No location available');
        }

        $response = $this->postJson('/api/voxpilot/orders', $this->validPayload(), [
            'Authorization' => 'Bearer ' . $this->plainToken,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['order_id', 'external_order_id', 'status', 'location', 'items_count', 'order_total', 'created_at', 'is_duplicate'],
        ]);
        $response->assertJsonPath('data.external_order_id', 'vp_test_endpoint_001');
        $response->assertJsonPath('data.is_duplicate', false);
    }

    public function test_duplicate_external_order_id_returns_existing(): void
    {
        if (!isset($this->locationId)) {
            $this->markTestSkipped('No location available');
        }

        $headers = ['Authorization' => 'Bearer ' . $this->plainToken];
        $payload = $this->validPayload();

        $first = $this->postJson('/api/voxpilot/orders', $payload, $headers);
        $first->assertStatus(201);

        $second = $this->postJson('/api/voxpilot/orders', $payload, $headers);
        $second->assertStatus(200);
        $second->assertJsonPath('data.is_duplicate', true);
        $second->assertJsonPath('data.order_id', $first->json('data.order_id'));
    }

    public function test_location_from_another_tenant_rejected(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $payload = $this->validPayload();
        $payload['location_id'] = 99999;

        $response = $this->postJson('/api/voxpilot/orders', $payload, [
            'Authorization' => 'Bearer ' . $this->plainToken,
        ]);

        $response->assertStatus(403);
    }

    public function test_validation_error_on_missing_items(): void
    {
        $payload = $this->validPayload();
        unset($payload['items']);

        $response = $this->postJson('/api/voxpilot/orders', $payload, [
            'Authorization' => 'Bearer ' . $this->plainToken,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    protected function validPayload(): array
    {
        return [
            'external_order_id' => 'vp_test_endpoint_001',
            'call_sid' => 'CA_test_123',
            'source' => 'voice',
            'customer' => [
                'name' => 'Gabriel Test',
                'phone' => '+50682831970',
            ],
            'fulfillment' => [
                'type' => 'pickup',
                'requested_time' => 'ASAP',
            ],
            'items' => [
                [
                    'name' => 'Large Margherita',
                    'quantity' => 1,
                    'unit_price' => 12.99,
                    'notes' => 'Thin crust',
                ],
                [
                    'name' => 'Soda',
                    'quantity' => 2,
                    'unit_price' => 2.50,
                ],
            ],
            'notes' => 'Test order from integration test',
            'transcript' => 'Customer ordered one large margherita and two sodas.',
        ];
    }
}
