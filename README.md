<p align="center"><a href="https://tastyigniter" target="_blank"><img src="https://tastyigniter.com/images/logos/logo-padded.png" width="400"></a></p>

<p align="center">
<a href="https://packagist.org/packages/tastyigniter/TastyIgniter"><img src="https://img.shields.io/packagist/v/tastyigniter/TastyIgniter.svg?label=Stable&style=flat-square" alt="Stable"></a>
<a href="https://packagist.org/packages/tastyigniter/TastyIgniter"><img src="https://poser.pugx.org/tastyigniter/flame/downloads" alt="Total Downloads"></a>
<a href="https://github.com/tastyigniter/TastyIgniter/blob/master/LICENSE.txt"><img src="https://img.shields.io/github/license/tastyigniter/TastyIgniter.svg?label=License&style=flat-square" alt="License"></a>
<a href="https://github.com/tastyigniter/TastyIgniter" title="PHP Versions Supported"><img alt="PHP Versions Supported" src="https://img.shields.io/badge/php-8.3%20to%208.4-777bb3.svg?logo=php&logoColor=white&labelColor=555555"></a>
<a href="https://translate.tastyigniter.com/engage/tastyigniter/"><img src="https://translate.tastyigniter.com/widget/tastyigniter/svg-badge.svg" alt="Translate"></a>
<a href="https://twitter.com/TastyIgniter"><img src="https://img.shields.io/twitter/follow/TastyIgniter.svg?label=Follow" alt="Twitter"></a>
</p>

[TastyIgniter](https://tastyigniter.com/) provides a professional and reliable platform for restaurants wanting to offer
online food ordering and table reservation to their customers.

![screenshot](https://tastyigniter.com/images/mockups/v4/Menus.png)

---

# VoxPilot POS Core

This repository is VoxPilot's fork of TastyIgniter 4.x: a **multi-tenant point of sale** for the restaurants that use the VoxPilot AI phone assistant. The `igniter/voxpilot` extension (`extensions/igniter/voxpilot`) adds a small API so an external system — VoxPilot Force, or any other integrator — can:

1. **Provision** a restaurant (tenant + admin user + location + API token).
2. **Read the menu** of a location (to let the AI assistant quote items and prices).
3. **Dispatch orders** taken on a call into the POS (they show up in real time in *Tools › Incoming Orders* and in the native TastyIgniter *Orders* screen).
4. **Receive status callbacks** when the restaurant moves the order forward (accepted, in preparation, ready, delivered, cancelled…).

Deeper material: [`VOXPILOT_POS_INTEGRATION.md`](VOXPILOT_POS_INTEGRATION.md) (setup, realtime/Reverb, troubleshooting) and [`DEPLOYMENT_COOLIFY.md`](DEPLOYMENT_COOLIFY.md) (production deployment).

## Integration contract

### Base URL and conventions

- Every endpoint lives under `{APP_URL}/api/voxpilot` — locally `https://pos.voxpilot.test`, in production `https://pos.voxpilothq.io`.
- JSON in and out. Always send `Accept: application/json` and `Content-Type: application/json`.
- Errors always have the shape `{ "error": { "code": "...", "message": "...", "details": { ... } } }` (`details` only on validation errors).
- A **tenant** is one restaurant business. It maps 1:1 to the caller's own account id through `external_tenant_id`. A tenant owns one or more **locations** (branches); orders and menus are per location.

### Credentials

| Credential | Where it lives on the POS | Counterpart in VoxPilot Force | Used for |
|---|---|---|---|
| **Provisioning secret** | env `VOXPILOT_PROVISIONING_SECRET` (≥ 32 chars) | env `POS_PROVISIONING_SECRET` | `POST /provision/tenants`, as `Authorization: Bearer <secret>` |
| **Tenant API token** | hashed in `ti_voxpilot_tenant_api_tokens` (plain text is never stored) | `pos_connection` integration document, encrypted | `POST /orders` and `GET /menu`, as `Authorization: Bearer {id}\|vp_pos_…` |
| **HMAC shared secret** | env `VOXPILOT_HMAC_SECRET` (≥ 32 chars) | env `POS_HMAC_SECRET` | Optional request signature on inbound calls; **required** signature on outbound status callbacks |

The tenant API token is returned **once**: in the provisioning response (`api_token`), or when an admin creates one in *Tools › VoxPilot Tokens*. It can be revoked there at any time and may carry a default location.

**HMAC signature** (same scheme in both directions):

```
X-VoxPilot-Timestamp: <unix seconds>
X-VoxPilot-Signature: v1=<hex( HMAC_SHA256( secret, "<timestamp>.<raw request body>" ) )>
```

The timestamp may drift at most 300 s. On inbound requests the signature is verified **in addition to** the bearer token whenever both headers are present (`503 HMAC_NOT_CONFIGURED` if the POS has no secret, `401 INVALID_SIGNATURE` / `401 TIMESTAMP_STALE` on mismatch).

### 1. Provision a tenant — `POST /api/voxpilot/provision/tenants`

Auth: provisioning secret. Idempotent on `external_tenant_id`.

```json
{
  "external_tenant_id": "68d1f0c2a4b7e9001c3f2a11",
  "company_name": "Bella Napoli",
  "admin_email": "owner@bellanapoli.test",
  "admin_name": "Mario Rossi",
  "location_name": "Bella Napoli Centro",
  "phone": "+34600000000",
  "webhook_callback_url": "https://api.voxpilothq.io"
}
```

| Field | Required | Notes |
|---|---|---|
| `external_tenant_id` | yes | Your own tenant/user id (max 255). Re-sending it returns the existing tenant. |
| `company_name` | yes | Tenant name; also the first location's name unless `location_name` is given. |
| `admin_email` | yes | Owner admin user. If a user with this email exists it is reused and made owner of the tenant. |
| `admin_name`, `location_name`, `phone` | no | |
| `webhook_callback_url` | no | `http(s)` base URL of your API. Status callbacks are POSTed to `{webhook_callback_url}/pos/webhooks/order-status`. |

Creates the tenant, the admin user (random password — they sign in via password reset), an `owner` membership, one location, and an API token with ability `orders:create` bound to that location.

`201 Created`:

```json
{
  "data": {
    "provisioned": true,
    "tenant_id": 2,
    "external_tenant_id": "68d1f0c2a4b7e9001c3f2a11",
    "location_id": 2,
    "admin_user_id": 5,
    "api_token": "5|vp_pos_mgDOs00dw85d38JwL2ibPEDezJiF24cpCB4hp8fjHSQbnh6l",
    "base_url": "https://pos.voxpilothq.io"
  }
}
```

`200 OK` when the tenant already exists: same shape with `provisioned: false` and `api_token: null` (the token cannot be recovered — create a new one from the admin panel). Errors: `401 PROVISIONING_AUTH_FAILED`, `422 VALIDATION_ERROR`, `503` when the secret is not configured on the POS.

### 2. Read the menu — `GET /api/voxpilot/menu[?location_id=2]`

Auth: tenant API token. Returns the **active** menu items of the resolved location (see *Location resolution*). VoxPilot Force fetches this to inject the menu into the assistant's prompt.

```json
{
  "data": {
    "location_id": 2,
    "items_count": 15,
    "items": [
      {
        "menu_id": 7,
        "name": "Margherita",
        "description": "Tomato, mozzarella, basil",
        "base_price": 9.5,
        "categories": ["Pizzas"],
        "sizes": [
          { "name": "Size", "required": true, "values": [ { "name": "Medium", "price": 0 }, { "name": "Large", "price": 3 } ] }
        ],
        "modifiers": [
          { "name": "Extras", "required": false, "values": [ { "name": "Extra cheese", "price": 1.5 } ] }
        ]
      }
    ]
  }
}
```

`sizes` are required options with more than one value; every other option is a `modifier`. Option value prices are **add-ons** over `base_price`.

### 3. Dispatch an order — `POST /api/voxpilot/orders`

Auth: tenant API token (+ optional HMAC). Idempotent per tenant on `external_order_id`: re-sending the same id returns the original order with `200` instead of creating a duplicate.

```json
{
  "external_order_id": "ord_68d1f1a0c2e4",
  "call_sid": "CA1234567890abcdef",
  "source": "voice",
  "location_id": 2,
  "customer": { "name": "Ana García", "phone": "+34611223344", "email": null },
  "fulfillment": { "type": "delivery", "requested_time": "ASAP", "address": "Calle Mayor 12, 2ºB" },
  "items": [
    { "name": "Margherita", "size": "Large", "quantity": 2, "notes": "no basil", "unit_price": 12.5 },
    { "name": "Coca-Cola", "quantity": 2, "unit_price": 2.5 }
  ],
  "notes": "Ring the bell twice",
  "transcript": "Customer ordered two large margheritas without basil…",
  "raw": { "anything": "the integrator wants to keep" }
}
```

| Field | Required | Notes |
|---|---|---|
| `external_order_id` | yes | Your order id (max 255). Idempotency key. |
| `call_sid` | no | Call reference, shown in the Incoming Orders screen. |
| `source` | no | `voice` (default), `chat`, `web`, `api`. |
| `location_id` | no | Must belong to the tenant, otherwise `403 LOCATION_FORBIDDEN`. |
| `customer.name` | yes | max 100. `phone` (max 30) and `email` optional. |
| `fulfillment.type` | yes | `pickup` or `delivery`. `requested_time` free text (e.g. `ASAP`, `19:30`); `address` max 500. |
| `items[]` | yes, ≥ 1 | `name` (required), `quantity` (≥ 1), optional `size`, `notes` (max 500), `unit_price` (decimal) or `unitPriceCents` (integer). |
| `notes`, `transcript`, `raw` | no | Stored with the order; `raw` is kept verbatim in `ti_voxpilot_order_metadata`. |

**Menu matching.** Each item is matched by name (and `size` against a required option) against the location's menu. Matched items become real order lines priced with the POS price; if the caller sent a different `unit_price` the response flags `price_mismatch: true`. Items that cannot be matched are still recorded on the order (as a "⚠ Sin mapear" comment) and returned in `unmapped` so the restaurant can fix them by hand.

`201 Created` (or `200 OK` with `is_duplicate: true`):

```json
{
  "data": {
    "order_id": 1043,
    "external_order_id": "ord_68d1f1a0c2e4",
    "status": "Received",
    "location": { "id": 2, "name": "Bella Napoli Centro" },
    "items_count": 4,
    "order_total": 30.0,
    "created_at": "2026-09-28T12:00:00+00:00",
    "is_duplicate": false,
    "price_mismatch": false,
    "unmapped": [ { "name": "Tiramisu", "quantity": 1 } ]
  }
}
```

Errors: `401 AUTHENTICATION_FAILED` (missing/invalid/revoked token, inactive tenant), `403 LOCATION_FORBIDDEN`, `422 VALIDATION_ERROR`, `422 LOCATION_REQUIRED`.

Side effects: the order is a normal TastyIgniter order (visible under *Sales › Orders*) and the event `voxpilot.order.created` is broadcast on the private channel `tenant.{tenant_id}.location.{location_id}.orders` (Laravel Reverb) so *Tools › Incoming Orders* updates live.

### Location resolution (orders and menu)

1. `location_id` from the payload/query — must belong to the tenant (`403` otherwise).
2. The API token's default location.
3. The tenant's only location, if it has exactly one.
4. Otherwise `422 LOCATION_REQUIRED`.

### 4. Order status callbacks — POS → integrator

Whenever a status is added to an order that came through this API (the restaurant changes it in the admin), the POS queues a `NotifyStatusChange` job and POSTs to `{webhook_callback_url}/pos/webhooks/order-status`:

```
POST {webhook_callback_url}/pos/webhooks/order-status
Content-Type: application/json
X-VoxPilot-Event: order.status_changed
X-VoxPilot-Timestamp: 1790000000
X-VoxPilot-Signature: v1=<hex hmac_sha256(VOXPILOT_HMAC_SECRET, "1790000000.<body>")>
```

```json
{
  "event": "order.status_changed",
  "external_order_id": "ord_68d1f1a0c2e4",
  "order_id": 1043,
  "status": "Preparation",
  "comment": "Ready in 20 min",
  "tenant_id": "68d1f0c2a4b7e9001c3f2a11",
  "timestamp": "2026-09-28T12:05:00+00:00"
}
```

- `status` is the TastyIgniter status **name** (defaults: `Received`, `Pending`, `Preparation`, `Delivery`, `Completed`, `Canceled` — restaurants may add their own). `tenant_id` is the `external_tenant_id` you provisioned with.
- The request is signed only when `VOXPILOT_HMAC_SECRET` is set; VoxPilot Force rejects unsigned callbacks with `401`.
- Answer `2xx` to acknowledge. Non-2xx or network errors are retried 3 times (10 s, 60 s, 300 s). Nothing is sent when the tenant has no `webhook_callback_url`.
- Requires the **queue worker** to be running (see *Processes*).

### Environment variables

POS side (`.env`, see [`.env.example`](.env.example)):

| Variable | Purpose |
|---|---|
| `APP_URL` | Public base URL; returned as `base_url` on provisioning. |
| `VOXPILOT_PROVISIONING_SECRET` | Provisioning bearer secret (≥ 32 chars). |
| `VOXPILOT_HMAC_SECRET` | HMAC secret for inbound verification and outbound callback signing (≥ 32 chars). |
| `QUEUE_CONNECTION=database` | Queued broadcasts and status callbacks (`ti_jobs` table, created by `php artisan migrate`). |
| `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID/KEY/SECRET` | Realtime Incoming Orders. |
| `REVERB_SERVER_HOST=0.0.0.0`, `REVERB_SERVER_PORT=6001` | Where the Reverb process binds. |
| `REVERB_CLIENT_HOST=127.0.0.1` | Where the Laravel broadcaster reaches Reverb (same container). |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | What the browser connects to (through the reverse proxy). |

VoxPilot Force side (`app/backend`): `POS_BASE_URL`, `POS_PROVISIONING_SECRET` (= `VOXPILOT_PROVISIONING_SECRET`), `POS_HMAC_SECRET` (= `VOXPILOT_HMAC_SECRET`), and `API_PUBLIC_URL` (or `BACKEND_URL`) which is sent as `webhook_callback_url`. When the POS runs on a private/Docker hostname (e.g. `http://voxpilot-ai-pos`), that host must be listed in Force's `DISPATCH_ALLOWED_PRIVATE_HOSTS` (comma-separated) or its SSRF guard blocks the request.

### Processes

The production image runs supervisord with three programs (`docker/supervisor/supervisord.conf`): **apache**, **reverb** (`artisan reverb:start`) and **queue-worker** (`artisan queue:work`). After deploying run `php artisan migrate` (creates the VoxPilot tables and `ti_jobs`) and, on a fresh database, `php artisan voxpilot:bootstrap-tenant`.

### Local smoke test

```bash
# 1. provision (secret from .env)
curl -sk https://pos.voxpilot.test/api/voxpilot/provision/tenants \
  -H "Authorization: Bearer $VOXPILOT_PROVISIONING_SECRET" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"external_tenant_id":"local_1","company_name":"Demo","admin_email":"demo@example.test","webhook_callback_url":"http://voxpilot-ai-backend:3000"}'
```

```bash
# 2. menu (token from step 1)
curl -sk https://pos.voxpilot.test/api/voxpilot/menu -H "Authorization: Bearer $POS_TOKEN" -H 'Accept: application/json'
```

```bash
# 3. order
curl -sk https://pos.voxpilot.test/api/voxpilot/orders -H "Authorization: Bearer $POS_TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"external_order_id":"local_ord_1","customer":{"name":"Test"},"fulfillment":{"type":"pickup"},"items":[{"name":"Margherita","quantity":1}]}'
```

Then change the order's status in the admin and watch `storage/logs/queue-worker.log` for `status webhook sent`.

### Tests

```bash
docker exec voxpilot-ai-pos php vendor/bin/phpunit extensions/igniter/voxpilot/tests
```

The suite wraps every test in a database transaction, so it is safe to run against the configured database.

---

### Documentation
The best place to learn TastyIgniter is by reading the [documentation](https://tastyigniter.com/docs)

### Installation
Please read the [Installation Guide](https://tastyigniter.com/docs/installation) for more information.

### Questions
For questions and support please use the [Community Forum](https://forum.tastyigniter.com) or [Join us on Discord](https://tastyigniter.com/discord). 

### Issues
Please report bugs using the [GitHub issue tracker](https://github.com/tastyigniter/TastyIgniter/issues)

### Stay in touch
- [Follow us on Twitter](https://twitter.com/tastyigniter/) for announcements and updates.
- [Blog](https://tastyigniter.com/blog) for tips and latest developments in the food industry.

## Contributing
We would love your help building TastyIgniter! Please read the [Contributing Guidelines](.github/CONTRIBUTING.md) to learn how you can help.

Thank you to all the people who already contributed to TastyIgniter!

<a href="https://github.com/tastyigniter/TastyIgniter/graphs/contributors"><img src="https://opencollective.com/tastyigniter/contributors.svg?width=890&button=false" /></a>

## Supporting TastyIgniter
TastyIgniter is an MIT-licensed community-driven project with its continuous development made possible by the support of these awesome [backers](#contributing). If you'd like to help support the future of the project, please consider:
1. Donating development time to the project.
2. Spreading the word about TastyIgniter.
3. Becoming a sponsor by donating funds (see below).

## Sponsors
Become a sponsor and get your logo on our README on Github with a link to your site. 

### via Open Collective
<a href="https://opencollective.com/tastyigniter" target="_blank" rel="noopener noreferrer"><img src="https://opencollective.com/tastyigniter/sponsors.svg"></a>

<a href="https://opencollective.com/tastyigniter" target="_blank" rel="noopener noreferrer"><img src="https://opencollective.com/tastyigniter/backers.svg"></a>

### via Patreon
[[Become a Patreon sponsor](https://www.patreon.com/sampoyigi)]

## Built With :heart:
- Laravel full-stack PHP framework
- Bootstrap 5 front-end framework

## Author
TastyIgniter was created by [Samuel Adepoyigi](https://github.com/sampoyigi).

## Security Vulnerabilities
If you discover a security vulnerability within TastyIgniter, please send an e-mail to support@tastyigniter.com.

## License
TastyIgniter is open-source software licensed under the [MIT license](https://tastyigniter.com/licence/).

