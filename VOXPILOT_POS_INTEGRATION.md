# VoxPilot POS Integration Guide

## Quick Start

### 1. Run Migrations

```bash
docker exec -it pos php artisan migrate
```

### 2. Bootstrap Default Tenant

```bash
docker exec -it pos php artisan voxpilot:bootstrap-tenant
```

This command is idempotent and will:
- Create a default tenant (name from `VOXPILOT_DEFAULT_TENANT_NAME` env var, or "Default Tenant")
- Assign all existing locations to that tenant
- Assign all existing admin users as tenant members

### 3. Regenerate Autoload

```bash
docker exec -it pos composer dump-autoload
```

### 4. Generate an API Token

1. Log in to POS admin at `https://pos.voxpilot.test/admin`
2. Go to **Tools > VoxPilot**
3. Enter a token name (e.g., "Production VoxPilot")
4. Optionally select a default location
5. Click **Generate Token**
6. **Copy the token immediately** — it will not be shown again

The token format is: `{tokenId}|vp_pos_{random}`

### 5. Test the API

```bash
curl -X POST https://pos.voxpilot.test/api/voxpilot/orders \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -k \
  -d '{
    "external_order_id": "vp_test_001",
    "call_sid": "CA_test_123",
    "source": "voice",
    "customer": {
      "name": "Gabriel Test",
      "phone": "+50682831970"
    },
    "fulfillment": {
      "type": "pickup",
      "requested_time": "ASAP"
    },
    "items": [
      {
        "name": "Large Margherita",
        "quantity": 1,
        "unit_price": 12.99,
        "notes": "Thin crust"
      },
      {
        "name": "Soda",
        "quantity": 2,
        "unit_price": 2.50
      }
    ],
    "notes": "Created from VoxPilot voice call",
    "transcript": "Customer ordered one large margherita thin crust and two sodas."
  }'
```

---

## API Reference

### `POST /api/voxpilot/orders`

Creates an order from a VoxPilot voice call or chat interaction.

**Authentication:** `Authorization: Bearer {tokenId}|{tokenSecret}`

**Headers:**
- `Content-Type: application/json`
- `Accept: application/json`

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `external_order_id` | string | Yes | Unique order ID from VoxPilot (idempotency key) |
| `call_sid` | string | No | Twilio call SID |
| `source` | string | No | `voice`, `chat`, `web`, `api` (default: `voice`) |
| `location_id` | integer | No | POS location ID (resolved from token default or tenant if omitted) |
| `customer.name` | string | Yes | Customer full name |
| `customer.phone` | string | No | Customer phone number |
| `customer.email` | string | No | Customer email |
| `fulfillment.type` | string | Yes | `pickup` or `delivery` |
| `fulfillment.requested_time` | string | No | `ASAP` or ISO time string |
| `items` | array | Yes | At least one item |
| `items[].name` | string | Yes | Item name |
| `items[].quantity` | integer | Yes | Quantity (min 1) |
| `items[].unit_price` | number | No | Unit price (default 0) |
| `items[].notes` | string | No | Item-level notes |
| `notes` | string | No | Order-level notes |
| `transcript` | string | No | Call transcript |
| `raw` | object | No | Any extra data (stored as JSON) |

### Response — Success (201 Created)

```json
{
  "data": {
    "order_id": 42,
    "external_order_id": "vp_test_001",
    "status": "Pending",
    "location": {
      "id": 1,
      "name": "Bella Napoli Downtown"
    },
    "items_count": 3,
    "order_total": 17.99,
    "created_at": "2026-09-25T14:30:00+00:00",
    "is_duplicate": false
  }
}
```

### Response — Duplicate (200 OK)

Same payload with `"is_duplicate": true` — returns the existing order.

### Error Responses

**401 Unauthorized:**
```json
{
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "Invalid authentication token."
  }
}
```

**403 Forbidden:**
```json
{
  "error": {
    "code": "LOCATION_FORBIDDEN",
    "message": "Location does not belong to this tenant."
  }
}
```

**422 Validation Error:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed.",
    "details": {
      "items": ["The items field is required."]
    }
  }
}
```

**422 Location Required:**
```json
{
  "error": {
    "code": "LOCATION_REQUIRED",
    "message": "Could not resolve location. Provide location_id in payload or set a default location on the API token."
  }
}
```

---

## Location Resolution

The endpoint resolves the target location in this priority:

1. `location_id` in the request body (must belong to token's tenant)
2. Token's `default_location_id` (set when generating the token)
3. Tenant's only location (auto-resolved if tenant has exactly one)
4. Error 422 if none of the above resolves

---

## Idempotency

The `external_order_id` acts as an idempotency key per tenant:
- First request with a given `external_order_id` → creates order (201)
- Subsequent requests with the same `external_order_id` → returns existing order (200)

Safe to retry on network failures.

---

## Token Management

### Generate Token
Admin UI at **Tools > VoxPilot** or programmatically.

### Revoke Token
Click "Revoke" in the admin UI. Revoked tokens return 401 immediately.

### Security
- Raw token shown only once at creation
- Only SHA-256 hash stored in database
- Token never logged

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 401 on every request | Check token was copied correctly, including the `{id}\|` prefix |
| 422 "location required" | Set a default location on the token, or pass `location_id` in payload |
| 403 "location forbidden" | The `location_id` in payload doesn't belong to this token's tenant |
| Order not visible in admin | Check correct location is selected in admin location filter |
| "No tenant configured" in admin | Run `php artisan voxpilot:bootstrap-tenant` |
| Migration fails | Run `composer dump-autoload` first, then `php artisan migrate` |
| WebSocket not connecting | Check `BROADCAST_CONNECTION=reverb` and Reverb env vars in `.env` |
| Orders not appearing realtime | Ensure queue worker running: `php artisan queue:work` |
| Channel auth 403 | User must be tenant member AND location must belong to tenant |

---

## Realtime Order Notifications

### Overview

When `POST /api/voxpilot/orders` creates an order, a `VoxPilotOrderCreated` event is broadcast on a private channel. Admin users subscribed via the **Incoming Orders** screen see new orders instantly.

**Channel:** `private-tenant.{tenantId}.location.{locationId}.orders`
**Event:** `.voxpilot.order.created`

### Setup

#### 1. Environment Variables

Add to `.env`:

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=123456
REVERB_APP_KEY=your-reverb-key
REVERB_APP_SECRET=your-reverb-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

For Docker, use `REVERB_HOST=pos` (the container hostname) if accessing from within the Docker network, or `localhost` / `pos.voxpilot.test` if accessing from the host browser.

#### 2. Enable Broadcast Extension

Configure via TastyIgniter admin: **System > Settings > Broadcast Settings**

Set provider to **Reverb** and fill in the same app ID, key, and secret from your `.env`.

Alternatively, seed the settings:

```bash
docker exec -it voxpilot-ai-pos php artisan tinker --execute="
\DB::table('settings')->updateOrInsert(
    ['sort' => 'igniter_broadcast_settings'],
    ['value' => json_encode([
        'provider' => 'reverb',
        'reverb_app_id' => env('REVERB_APP_ID', '123456'),
        'reverb_key' => env('REVERB_APP_KEY', 'your-reverb-key'),
        'reverb_secret' => env('REVERB_APP_SECRET', 'your-reverb-secret'),
        'reverb_host' => env('REVERB_HOST', 'localhost'),
        'reverb_port' => env('REVERB_PORT', 8080),
        'reverb_scheme' => env('REVERB_SCHEME', 'http'),
    ])]
);
echo 'OK';
"
```

#### 3. Create Queue Table (if using database queue)

```bash
docker exec -it voxpilot-ai-pos php artisan queue:table
docker exec -it voxpilot-ai-pos php artisan migrate
```

#### 4. Start Services

**Reverb WebSocket server:**
```bash
docker exec -it voxpilot-ai-pos php artisan reverb:start --host=0.0.0.0 --port=8080
```

**Queue worker:**
```bash
docker exec -it voxpilot-ai-pos php artisan queue:work --tries=3
```

### Testing with Two Browser Windows

1. **Window A:** Open `https://pos.voxpilot.test/admin/igniter/voxpilot/incomingorders`
   - Should show "Connected (N channels)" badge in green
   - Enable sound toggle if desired

2. **Window B:** Open a terminal and send a test order via curl:

```bash
curl -X POST https://pos.voxpilot.test/api/voxpilot/orders \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -k \
  -d '{
    "external_order_id": "vp_realtime_test_001",
    "call_sid": "CA_live_test",
    "source": "voice",
    "customer": {
      "name": "Realtime Test",
      "phone": "+34600000000"
    },
    "fulfillment": { "type": "pickup", "requested_time": "ASAP" },
    "items": [
      { "name": "Pepperoni Pizza", "quantity": 2, "unit_price": 14.50 },
      { "name": "Tiramisu", "quantity": 1, "unit_price": 6.00 }
    ],
    "notes": "Realtime notification test"
  }'
```

3. **Window A** should instantly show the new order with a green highlight and toast notification (+ sound if enabled).

### Channel Authorization

Channel `private-tenant.{tenantId}.location.{locationId}.orders` requires:
- User authenticated as admin (`igniter-admin` guard)
- User has `TenantMembership` for the given tenant
- Location belongs to the tenant (or user is `super_user`)

### Incoming Orders Admin UI

Navigate to **Tools > Incoming Orders** in the POS admin.

Features:
- Real-time order display via WebSocket
- Location filter dropdown
- Sound notification toggle
- Toast alert for each new order
- Link to full order detail page
- Green highlight animation on new orders
