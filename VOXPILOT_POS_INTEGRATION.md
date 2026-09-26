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
