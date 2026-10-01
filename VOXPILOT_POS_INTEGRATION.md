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

## Provisioning and Connect with VoxPilot (SPEC-011)

This is the VoxPilot POS, the first-party path. A business with its own POS connects it from VoxPilot through the generic API automation instead; nothing below applies to it.

- **`POST /api/voxpilot/provision/tenants`** (Bearer `VOXPILOT_PROVISIONING_SECRET`):
  - VoxPilot calls it only for accounts that ticked *Activate VoxPilot POS*.
  - It creates the tenant, the owner, the default location and the membership.
  - It is idempotent by `external_tenant_id`, and it **never returns an order token** (`api_token: null`).
  - The response includes `pos_admin_url` and `owner_invite_sent`.
- **Owner invite.**
  - A newly created owner receives TastyIgniter's staff invite: a set-your-password link to `/admin/login/reset`.
  - It is sent after commit through the mail queue, so the `queue-worker` must run.
  - Disable it per request (`send_owner_invite: false`) or globally (`VOXPILOT_SEND_OWNER_INVITE=false`).
  - The invite subject uses the global site name; set it to something neutral (e.g. "VoxPilot POS").
- **Connect with VoxPilot** (Tools → VoxPilot):
  - The admin gets a one-time code (10 min) and is redirected to VoxPilot.
  - There the owner picks **one** assistant that is not yet connected to a VoxPilot POS.
  - VoxPilot then calls `POST /api/voxpilot/installations/activate`, which mints the order token, returns it once and binds the assistant.
  - **One POS ↔ one assistant**: a second activation is refused (409).
- **Disconnect** (POS admin or VoxPilot) revokes the tokens and keeps the restaurant and its orders.
- **Owner access.** The provisioned owner gets the Owner role and is assigned to the restaurant's location.
  - The role has `Igniter.VoxPilot.Manage` and `Admin.Orders`, added and never overwritten.
  - Menus, customers and the dashboard are global in this POS, so they are not granted.
- **Location mode.** Run with `IGNITER_LOCATION_MODE=multiple`. In `single` mode every admin is pinned to the default location, and an owner sees no orders.
- **Menus are per location.** A new restaurant has none: the agent gets no menu, and order lines arrive as `[sin mapear]`. The owner (or support) must attach or build its menu.
- **Sizes.** VoxPilot sends `items.*.size`. The matched size option (e.g. "Mediana") is priced and stored on the order line, so the kitchen sees it.
- **Migrations:** run `php artisan igniter:up`. The `external_tenant_id` migration skips the column if an older database already has it.
- **Tests:** run them against a **separate** database, never `voxpilot_pos`:

  ```bash
  docker exec -e DB_DATABASE=voxpilot_pos_test voxpilot-ai-pos sh -c 'cd /var/www/html && vendor/bin/phpunit extensions/igniter/voxpilot/tests'
  ```

  Create the copy first; see `VOXPILOT_POS_LOCAL_E2E.md` in voxpilot-force.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| 401 on every request | Check token was copied correctly, including the `{id}\|` prefix |
| 422 "location required" | Set a default location on the token, or pass `location_id` in payload |
| 403 "location forbidden" | The `location_id` in payload doesn't belong to this token's tenant |
| Order not visible in admin | Check correct location is selected in admin location filter |
| "No tenant configured" in admin | Run `php artisan voxpilot:bootstrap-tenant` |
| Migration fails | Run `composer dump-autoload` first, then `php artisan migrate` |
| WebSocket not connecting | See Realtime Troubleshooting below |
| Orders not appearing realtime | See Realtime Troubleshooting below |
| Channel auth 403 | User must be tenant member AND location must belong to tenant |

---

## Realtime Order Notifications

### Overview

When `POST /api/voxpilot/orders` creates a new order, a `VoxPilotOrderCreated` event is broadcast synchronously (`ShouldBroadcastNow`) on a private channel. Admin users on the **Incoming Orders** screen see new orders instantly. No queue worker is required.

Duplicate orders (same `external_order_id`) do NOT trigger a broadcast.

**Channel:** `private-tenant.{tenantId}.location.{locationId}.orders`
**Event:** `.voxpilot.order.created`

### Environment Variables

There are three groups of Reverb env vars. Server-side and client-side are intentionally decoupled.

#### Shared credentials (same value both sides)

| Variable | Description | Local default | Production |
|----------|-------------|---------------|------------|
| `REVERB_APP_ID` | Reverb application ID | `891234` | **Generate unique value** |
| `REVERB_APP_KEY` | Reverb public key (exposed to browser) | `voxpilot-reverb-key` | **Generate unique value** |
| `REVERB_APP_SECRET` | Reverb secret (server-side only) | `voxpilot-reverb-secret` | **Generate unique value** |

#### Server-side (Laravel broadcaster → Reverb, internal)

| Variable | Description | Local default |
|----------|-------------|---------------|
| `REVERB_SERVER_HOST` | Reverb bind address | `0.0.0.0` |
| `REVERB_SERVER_PORT` | Reverb bind port | `6001` |
| `REVERB_SERVER_SCHEME` | `http` or `https` | `http` |

#### Client-side (browser Echo → Caddy/proxy → Reverb)

| Variable | Description | Local | Production |
|----------|-------------|-------|------------|
| `REVERB_HOST` | WebSocket hostname for browser | `pos.voxpilot.test` | `pos.voxpilothq.io` |
| `REVERB_PORT` | WebSocket port for browser | `443` | `443` |
| `REVERB_SCHEME` | `http` or `https` | `https` | `https` |

#### Full `.env` example (local Docker)

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=891234
REVERB_APP_KEY=voxpilot-reverb-key
REVERB_APP_SECRET=voxpilot-reverb-secret

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=6001
REVERB_SERVER_SCHEME=http

REVERB_HOST=pos.voxpilot.test
REVERB_PORT=443
REVERB_SCHEME=https
```

#### Production / Coolify

Set these as environment variables in Coolify (never commit production secrets):

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<generate-unique>
REVERB_APP_KEY=<generate-unique>
REVERB_APP_SECRET=<generate-unique>

REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=6001
REVERB_SERVER_SCHEME=http

REVERB_HOST=pos.voxpilothq.io
REVERB_PORT=443
REVERB_SCHEME=https
```

Generate credentials:
```bash
php -r "echo bin2hex(random_bytes(16));"  # for APP_ID
php -r "echo bin2hex(random_bytes(32));"  # for APP_KEY
php -r "echo bin2hex(random_bytes(32));"  # for APP_SECRET
```

### Reverb Process

Reverb runs as a long-lived PHP process alongside Apache.

**Local Docker:** Reverb starts automatically via `entrypoint.sh` when `BROADCAST_CONNECTION=reverb`. No manual intervention needed.

**Manual start:**
```bash
docker exec -it voxpilot-ai-pos php artisan reverb:start --host=0.0.0.0 --port=6001
```

**Coolify/production:** Run Reverb as a separate supervised process. Options:

1. **Supervisor** (recommended for Coolify):
```ini
[program:reverb]
command=php /var/www/html/artisan reverb:start --host=127.0.0.1 --port=6001
autostart=true
autorestart=true
stderr_logfile=/var/log/reverb.err.log
stdout_logfile=/var/log/reverb.out.log
```

2. **Separate Docker Compose service** (alternative):
```yaml
pos-reverb:
  image: voxpilot-ai-pos:dev
  container_name: voxpilot-ai-pos-reverb
  command: php artisan reverb:start --host=0.0.0.0 --port=6001
  restart: unless-stopped
  depends_on: [pos-db]
  environment: { ... same DB + REVERB env vars as pos service ... }
  volumes:
    - ./voxpilot-pos-core:/var/www/html
  expose: ["6001"]
  networks: [voxpilot]
```

### WebSocket Proxy

Browser connects to the same domain/port as the POS admin (`wss://pos.voxpilot.test/app/{key}`). The reverse proxy routes WebSocket paths to Reverb.

**Caddy (local, already configured):**
```
pos.voxpilot.test {
    tls internal
    @reverb path /app/* /apps/*
    reverse_proxy @reverb pos:6001
    reverse_proxy pos:80 {
        header_up X-Forwarded-Proto https
    }
}
```

**Coolify/Nginx equivalent:**
```nginx
location ~ ^/(app|apps)/ {
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
}
```

Key points:
- `/app/*` and `/apps/*` are Pusher-protocol paths used by Reverb
- Must proxy to Reverb port (6001), not Apache (80)
- WebSocket requires `Upgrade` and `Connection` headers
- Must use HTTP/1.1 (HTTP/2 does not support WebSocket upgrade)

### Browser Test Checklist

1. Log in to POS admin at `https://pos.voxpilot.test/admin`
2. Go to **Tools > Incoming Orders**
3. Verify green **"Listening (N)"** badge
4. Open browser console — should show:
   - `VoxPilot: subscribing to private-tenant.X.location.Y.orders`
   - `VoxPilot: WebSocket connected`
5. Send test order via curl (see below)
6. Verify order appears instantly with green highlight + toast notification
7. Verify console shows `VoxPilot: order received {...}`

### curl Test

```bash
curl -sk -X POST https://pos.voxpilot.test/api/voxpilot/orders \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "external_order_id": "vp_test_'$(date +%s)'",
    "call_sid": "CA_test",
    "source": "voice",
    "customer": {"name": "Test Customer", "phone": "+34600000000"},
    "fulfillment": {"type": "pickup", "requested_time": "ASAP"},
    "items": [
      {"name": "Pepperoni Pizza", "quantity": 2, "unit_price": 14.50},
      {"name": "Tiramisu", "quantity": 1, "unit_price": 6.00}
    ],
    "notes": "Realtime notification test"
  }'
```

### Channel Authorization

Channel `private-tenant.{tenantId}.location.{locationId}.orders` requires:
- User authenticated as TI admin (middleware: `web` + `igniter`, guard: `igniter-admin`)
- User has `TenantMembership` for the given tenant
- Location belongs to the tenant (or user is `super_user`)
- Channel auth does NOT trust client-sent `tenant_id` — verified server-side

### Realtime Troubleshooting

| Problem | Diagnosis | Fix |
|---------|-----------|-----|
| WebSocket connects but no events | Reverb running? `ps aux \| grep reverb` | Start Reverb: `php artisan reverb:start --host=0.0.0.0 --port=6001` |
| `/broadcasting/auth` returns 403 | User not logged in, or not a tenant member | Log in to admin. Run `voxpilot:bootstrap-tenant` to assign membership |
| `/broadcasting/auth` returns 404 | Broadcast routes not registered | Verify `BROADCAST_CONNECTION=reverb` in `.env`. Run `php artisan config:clear` |
| Mixed content error in browser | Client-side scheme mismatch | Set `REVERB_SCHEME=https` and `REVERB_PORT=443` |
| Reverb port unreachable | Port not exposed or proxy misconfigured | Check `expose: ["6001"]` in compose. Check Caddy `@reverb` path matcher |
| Wrong host/port/scheme | Server-side vs client-side vars swapped | Server: `REVERB_SERVER_*`. Client: `REVERB_HOST/PORT/SCHEME` |
| Duplicate `external_order_id` does not broadcast | Expected behavior | Idempotent duplicates return 200 with `is_duplicate: true` and do not fire events |
| `BroadcastException: cURL error 7` | Server-side broadcaster using client-facing host | Check `REVERB_SERVER_HOST` points to `127.0.0.1` or `0.0.0.0`, not external domain |

### Incoming Orders Admin UI

Navigate to **Tools > Incoming Orders** in the POS admin.

Features:
- Real-time order display via WebSocket (Pusher + Echo CDN, standalone — no TI broadcast extension dependency)
- Location filter dropdown
- Sound notification toggle
- Toast alert for each new order
- Link to full order detail page
- Green highlight animation on new orders
- Connection status badge (Listening / Disconnected / Reconnecting / Auth failed)
