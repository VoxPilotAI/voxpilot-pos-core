# VoxPilot POS Core — Architecture & Integration Audit

**Date:** 2026-09-25
**Scope:** TastyIgniter 4.x fork at `voxpilot-pos-core`
**Purpose:** Map the codebase for multi-tenancy, API order ingestion, realtime events, and VoxPilot integration.
**Status:** Phase 1 — Audit only. No code changes.

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Authentication and Users](#2-authentication-and-users)
3. [Locations / Business Records](#3-locations--business-records)
4. [Orders](#4-orders)
5. [Customers](#5-customers)
6. [Settings and Admin UI](#6-settings-and-admin-ui)
7. [API Routes](#7-api-routes)
8. [Token / Security Model](#8-token--security-model)
9. [Multi-Tenancy Implementation Strategy](#9-multi-tenancy-implementation-strategy)
10. [Realtime / WebSockets](#10-realtime--websockets)
11. [Local Development Setup](#11-local-development-setup)
12. [Database Migration Plan](#12-database-migration-plan)
13. [Endpoint Design](#13-endpoint-design)
14. [Testing Plan](#14-testing-plan)
15. [Risk Assessment](#15-risk-assessment)
16. [Recommended Implementation Phases](#16-recommended-implementation-phases)
17. [Final Recommendation](#17-final-recommendation)

---

## 1. Project Structure

### Version Stack

| Component | Version | Source |
|-----------|---------|--------|
| PHP | `^8.3` | `composer.json` |
| Laravel | `^13.0` | `composer.json` |
| TastyIgniter Core | `^4.4` (branch alias `4.x-dev`) | `vendor/tastyigniter/core/composer.json` |
| Laravel Sanctum | `^4.0` | TI core dependency |
| Livewire | `^3.0` | TI core dependency |
| Laravel Reverb | `^1.5` | `ti-ext-broadcast` dependency |
| Pusher PHP | `~7.0` | `ti-ext-broadcast` dependency |
| MySQL | 8.4 | Docker `pos-db` |
| DB prefix | `ti_` | `.env` `DB_PREFIX=ti_` |

### Key Finding: Thin Shell Architecture

This project is a **stock TastyIgniter 4.x install**. All domain logic lives in `vendor/tastyigniter/`. The `app/` folder is nearly stock Laravel scaffold. The `extensions/` folder is empty — all extensions are Composer-installed.

### Folder Map

| Path | Contents |
|------|----------|
| `app/Models/User.php` | Stock Laravel User model — **NOT used by TI** |
| `app/Providers/RouteServiceProvider.php` | Registers `api` and `web` middleware groups |
| `routes/web.php`, `routes/api.php` | Commented out, no active routes |
| `routes/channels.php` | Empty broadcast channel auth |
| `config/auth.php` | Stock Laravel auth — TI overrides at boot |
| `config/broadcasting.php` | Pusher/Ably/Redis/Log/Null — currently `log` |
| `database/migrations/` | 4 stock Laravel migrations (users, password_resets, failed_jobs, personal_access_tokens) |
| `vendor/tastyigniter/core/` | Core system: admin scaffold, system models, migrations, views, JS/SCSS |
| `vendor/tastyigniter/ti-ext-cart/` | Orders, menus, categories, inventory, checkout |
| `vendor/tastyigniter/ti-ext-local/` | Locations, delivery areas, working hours, reviews |
| `vendor/tastyigniter/ti-ext-user/` | Admin users, customers, auth guards, roles |
| `vendor/tastyigniter/ti-ext-api/` | REST API with Sanctum tokens, JSON:API |
| `vendor/tastyigniter/ti-ext-broadcast/` | Real-time broadcasting (Reverb/Pusher/Ably) |
| `vendor/tastyigniter/ti-ext-automation/` | Automation rules engine |
| `vendor/tastyigniter/ti-ext-reservation/` | Table reservations |
| `vendor/tastyigniter/ti-ext-payregister/` | Payment gateways |
| `themes/` | Theme files |

### Routes

- **Admin panel:** `/admin` (env `IGNITER_ADMIN_URI`), middleware `['web', 'igniter', 'igniter:admin']`
- **Frontend:** `/` (env `IGNITER_URI`), middleware `['web', 'igniter']`
- **API:** `/api/*` (prefix from `config/igniter-api.php`), middleware `['api', Authenticate::class]`
- **Token endpoint:** `POST /api/token` — creates Sanctum token from email/password
- **Order status:** `PATCH /api/orders/{orderId}/status` — admin-only status update

### Admin Controllers (key ones)

| Controller | File |
|-----------|------|
| Orders | `vendor/tastyigniter/ti-ext-cart/src/Http/Controllers/Orders.php` |
| Menus | `vendor/tastyigniter/ti-ext-cart/src/Http/Controllers/Menus.php` |
| Locations | `vendor/tastyigniter/ti-ext-local/src/Http/Controllers/Locations.php` |
| Users (admin) | `vendor/tastyigniter/ti-ext-user/src/Http/Controllers/Users.php` |
| Customers | `vendor/tastyigniter/ti-ext-user/src/Http/Controllers/Customers.php` |
| Settings | `vendor/tastyigniter/core/src/System/Http/Controllers/Settings.php` |
| API Resources | `vendor/tastyigniter/ti-ext-api/src/Http/Controllers/Resources.php` |
| API Tokens | `vendor/tastyigniter/ti-ext-api/src/Http/Controllers/Tokens.php` |

### Admin Frontend Stack

- **Livewire 3** + **Blade templates**
- Views at `vendor/tastyigniter/core/resources/views/admin/`
- JS widgets at `vendor/tastyigniter/core/resources/js/`
- SCSS at `vendor/tastyigniter/core/resources/scss/`
- Build: `webpack.mix.js`
- Controller pattern: `$implement` array with `ListController`, `FormController`, `LocationAwareController`, `AssigneeController`

### Extension vs Core vs Hybrid

**Recommendation: Hybrid approach.**

- **New TastyIgniter extension** (`extensions/igniter/voxpilot/`) for all VoxPilot-specific code: tenant model, token model, ingestion endpoint, integrations UI, realtime events.
- **Minimal core touches**: add `tenant_id` columns to existing tables via extension migrations, add global scopes via extension boot.
- **Rationale**: keeps TI core upgradable, isolates VoxPilot logic, follows TI extension conventions.

---

## 2. Authentication and Users

### Current State

| Aspect | Finding |
|--------|---------|
| Admin user model | `Igniter\User\Models\User` |
| Admin table | `admin_users` (prefixed: `ti_admin_users`) |
| Primary key | `user_id` |
| Base class | `Igniter\User\Auth\Models\User` (uses `Authenticatable`, `HasApiTokens`, `Notifiable`) |
| Traits | `HasFactory`, `Locationable`, `Purgeable`, `SendsInvite`, `SendsMailTemplate`, `Switchable` |
| Key columns | `user_id`, `name`, `email`, `user_role_id`, `language_id`, `status`, `sale_permission`, `username`, `password`, `super_user`, `is_activated` |
| Admin guard | `igniter-admin` — custom `UserGuard` (session-based, extends `SessionGuard`) |
| Customer guard | `igniter-customer` — custom `CustomerGuard` |
| API guard | `sanctum` (config `igniter-api.guard`) |
| Permission system | Role-based via `UserRole`. Gate after-callback: `$user->hasAnyPermission($ability)` |
| Super user flag | `super_user` column — bypasses permission checks |
| Location relationship | `morphToMany` via `Locationable` trait — users can be tied to locations via `ti_locationables` pivot |

### Legacy Note

`Igniter\Admin\Models\Staff` (table `staffs`) exists but is deprecated. Migration `2022_02_17_000300_merge_staffs_into_users_table.php` merged staffs into users. Migration `2022_06_10_030300_prefix_users_tables_with_admin_table.php` renamed `users` to `admin_users`.

### Answers

**Best place to add tenant-user membership?**

Use a **membership pivot table** `tenant_user_memberships`:
- `tenant_id`
- `user_id` (FK to `admin_users.user_id`)
- `role` (enum: `owner`, `admin`, `member`)
- `created_at`, `updated_at`

Do NOT add `tenant_id` directly to `admin_users`. Reasons:
1. Users belonging to multiple tenants is an explicit requirement.
2. A direct column would force single-tenant and require painful migration later.
3. Membership table is cleaner for role-per-tenant.

**Multi-tenant support path:**
- Membership table from day one.
- Current active tenant stored in session (admin login selects tenant or auto-selects if only one).
- `BelongsToTenant` trait on User reads from session for query scoping.

**Risks:**
- `Locationable` trait already applies a global scope filtering by admin user's assigned locations. Adding tenant scope must compose with it, not conflict.
- `super_user` flag must bypass tenant scope (platform admin access).
- Session-based tenant context must propagate to queued jobs.

---

## 3. Locations / Business Records

### Current State

| Aspect | Finding |
|--------|---------|
| Model | `Igniter\Local\Models\Location` |
| Table | `locations` (prefixed: `ti_locations`) |
| Primary key | `location_id` |
| Key columns | `location_id`, `location_name`, `location_email`, `description`, `location_address_1/2`, `location_city`, `location_state`, `location_postcode`, `location_country_id`, `location_telephone`, `location_lat`, `location_lng`, `location_radius`, `location_status`, `permalink_slug`, `is_default` |
| Traits | `Defaultable`, `HasCountry`, `HasDeliveryAreas`, `HasFactory`, `HasMedia`, `HasPermalink`, `HasWorkingHours`, `LocationHelpers`, `Purgeable`, `Switchable` |
| Linked to orders | `Order.location_id` FK |
| Linked to menus | `morphToMany` via `Locationable` trait (pivot `ti_locationables`) |
| Linked to users | `morphToMany` via `Locationable` trait (admin users are assigned to locations) |
| Location selection in admin | `LocationAwareController` action — admin sees records scoped to their assigned locations |

### Locationable System (Critical)

The `Locationable` trait + `LocationableScope` is TI's existing scoping mechanism:
- Models using `Locationable` get a global scope that filters by admin user's assigned locations.
- Order, Menu, User all use this trait.
- The `ti_locationables` pivot table links models to locations via polymorphic relation.

This is **partial tenancy already**. Locations act as data-isolation boundaries for menus, orders, and staff.

### Answers

**Can existing Location records become children of Tenant?**

Yes. Add `tenant_id` column to `ti_locations`. Each location belongs to exactly one tenant. The existing `is_default` flag should become tenant-scoped (default location per tenant, not globally).

**Safest migration path for existing test business/location:**
1. Create `tenants` table.
2. Create a default tenant record.
3. Add `tenant_id` to `locations` with a backfill migration that assigns all existing locations to the default tenant.
4. Add `NOT NULL` constraint after backfill.

**Queries/controllers needing tenant scoping:**
- `LocationAwareController` in all admin controllers that use it (Orders, Menus, Categories, etc.)
- Location dropdown/selector in admin nav
- Location creation form
- Any `Location::all()` or `Location::query()` call

**Unique constraint risks:**
- `permalink_slug` on locations — must become tenant-scoped (unique per tenant, not globally)
- `is_default` — must become per-tenant default

---

## 4. Orders

### Current State

| Aspect | Finding |
|--------|---------|
| Model | `Igniter\Cart\Models\Order` |
| Table | `orders` (prefixed: `ti_orders`) |
| Primary key | `order_id` |
| Key columns | `order_id`, `customer_id`, `first_name`, `last_name`, `email`, `telephone`, `location_id`, `address_id`, `cart` (serialized), `total_items`, `comment`, `payment`, `order_type` ('delivery'\|'collection'), `order_date`, `order_time`, `order_total`, `status_id`, `ip_address`, `user_agent`, `hash`, `processed`, `order_time_is_asap`, `delivery_comment`, `assignee_id`, `assignee_group_id`, `invoice_prefix`, `invoice_date` |
| Traits | `Assignable`, `GeneratesHash`, `HasCustomer`, `HasFactory`, `HasInvoice`, `Locationable`, `LogsStatusHistory`, `ManagesOrderItems`, `SendsMailTemplate` |
| Order items | `OrderMenu` model, table `order_menus` — columns: `order_menu_id`, `order_id`, `menu_id`, `name`, `quantity`, `price`, `subtotal`, `option_values`, `comment` |
| Order totals | `OrderTotal` model, table `order_totals` — columns: `order_total_id`, `order_id`, `code`, `title`, `value`, `priority`, `is_summable` |
| Relationships | `belongsTo`: customer, location, address, payment_method. `hasMany`: payment_logs, menus, menu_options, totals |
| Status tracking | `LogsStatusHistory` trait — `status_id` FK to `ti_statuses`, history in `ti_status_history` |
| Order creation flow | `OrderManager` service — tightly coupled to session Cart, Location facade, authenticated Customer |

### Order Creation Services

**`OrderManager`** (`vendor/tastyigniter/ti-ext-cart/src/Classes/OrderManager.php`):
- Constructor resolves `CartManager->getCart()`, `App::make('location')`, `Auth::customer()`
- `loadOrder()` creates order from session cart content
- `applyRequiredAttributes()` sets location, customer, order type from session state
- **Tightly coupled to browser session** — cannot be used directly for API ingestion

**Existing API order creation** (`Igniter\Api\ApiResources\Orders`):
- `restAfterSave()` handles `order_menus` and `order_totals` from request input
- Accepts `order_menus` array and `order_totals` array in POST body
- Can set `status_id` and `processed` flag
- **This bypasses OrderManager** and creates orders directly via the repository pattern

**Required fields for POST (from `OrderRequest`):**
- `first_name` (required on POST, 1-48 chars)
- `last_name` (required on POST, 1-48 chars)
- `order_type` (required on POST, alpha_dash — `'delivery'` or `'collection'`)
- `email` (sometimes required, email format)
- `telephone` (string)
- `customer_id` (integer, optional)
- `location_id` (set via `Locationable` — required)
- If delivery: `address.address_1` required

### Answers

**Safest way to create an order from `POST /api/voxpilot/orders`?**

Create a **dedicated `VoxPilotOrderIngestionService`** that:
1. Accepts the VoxPilot payload
2. Resolves tenant and location from token
3. Maps VoxPilot items to existing menu items (by name fuzzy match or SKU)
4. Creates `Order` record directly (not through `OrderManager` which needs session)
5. Creates `OrderMenu` records via `ManagesOrderItems::addOrderMenus()`
6. Creates `OrderTotal` records for subtotal/total
7. Sets initial status
8. Dispatches `VoxPilotOrderCreated` event
9. Stores VoxPilot metadata in a linked table

Do NOT use `OrderManager` — it requires session/cart state. The existing API `Orders` resource's `restAfterSave()` pattern shows direct creation is viable.

**VoxPilot metadata storage:**

Create a **`voxpilot_order_metadata`** table:

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `order_id` | int FK | to `ti_orders.order_id`, unique |
| `external_order_id` | string | `vp_order_123`, unique per tenant |
| `call_sid` | string nullable | Twilio call SID |
| `source` | string | `'voice'`, `'chat'`, etc. |
| `transcript` | text nullable | |
| `raw_payload` | json nullable | Full original payload |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

This is cleaner than adding columns to `ti_orders` (which is vendor-managed).

---

## 5. Customers

### Current State

| Aspect | Finding |
|--------|---------|
| Model | `Igniter\User\Models\Customer` |
| Table | `customers` (prefixed: `ti_customers`) |
| Primary key | `customer_id` |
| Key columns | `customer_id`, `first_name`, `last_name`, `email`, `password`, `telephone`, `address_id`, `newsletter`, `customer_group_id`, `ip_address`, `status`, `is_activated`, `last_login` |
| Base class | `AuthUserModel` (same as admin user — `Authenticatable`, `HasApiTokens`, `Notifiable`) |
| Relationships | `hasMany`: addresses, orders, reservations. `belongsTo`: group, address |
| Uniqueness | `email` is unique (standard Laravel/TI behavior) |
| Location scope | Customers are **global** — not tied to locations |

### Answers

**Should external voice orders create customers?**

For MVP: **No automatic customer creation.** Reasons:
1. Customer model requires `email` + `password` (auth model) — phone orders may only have phone number.
2. Customer uniqueness is by email — phone-only customers would need nullable email, breaking assumptions.
3. Creating ghost customers pollutes the customer list.

**MVP behavior:**
- Store customer name/phone on the `Order` directly (`first_name`, `last_name`, `telephone` columns already exist on Order).
- Skip `customer_id` — leave it null.
- If tenant later wants customer matching, implement as Phase 8+ feature.

**Should customers be scoped by tenant?**

Eventually yes, but **not in MVP**. Customer scoping is complex:
- Email uniqueness must become tenant-scoped.
- Customer auth guard must be tenant-aware.
- Orders already store customer name/phone directly — no customer record needed for voice orders.

---

## 6. Settings and Admin UI

### How Admin Navigation Works

Extensions register navigation in `registerNavigation()` method. Example from API extension (`vendor/tastyigniter/ti-ext-api/src/Extension.php:84`):

```php
public function registerNavigation(): array
{
    return [
        'tools' => [
            'child' => [
                'resources' => [
                    'priority' => 2,
                    'class' => 'api-resources',
                    'href' => admin_url('igniter/api/resources'),
                    'title' => 'APIs',
                    'permission' => 'Igniter.Api',
                ],
            ],
        ],
    ];
}
```

### How Admin Controllers Work

Admin controllers extend `AdminController` and use the `$implement` array pattern:

```php
public array $implement = [
    ListController::class,
    FormController::class,
    LocationAwareController::class,
];
```

Each controller has:
- `$listConfig` — list/table configuration
- `$formConfig` — form configuration (create/edit views)
- Config files typically YAML in `resources/models/` directory of the extension

### How Settings Pages Work

`vendor/tastyigniter/core/src/System/Http/Controllers/Settings.php` handles system settings.
Extensions register settings via `registerSettings()` in their Extension class.

### Answers

**Best path for an Integrations page:**

Create within the VoxPilot extension:
- Controller: `extensions/igniter/voxpilot/src/Http/Controllers/Integrations.php`
- Uses `ListController` + `FormController`
- Registers under `tools` nav group (alongside existing API resources)

```php
public function registerNavigation(): array
{
    return [
        'tools' => [
            'child' => [
                'integrations' => [
                    'priority' => 3,
                    'class' => 'voxpilot-integrations',
                    'href' => admin_url('igniter/voxpilot/integrations'),
                    'title' => 'VoxPilot',
                    'permission' => 'Igniter.VoxPilot.Manage',
                ],
            ],
        ],
    ];
}
```

**Token generation UI flow:**
1. Admin navigates to Tools > VoxPilot > Integrations.
2. Clicks "Generate API Token".
3. Form: token name, optional default location, optional scopes.
4. On submit: generate token, hash it, store hash, show raw token **once** in a modal/flash.
5. List view shows: name, created date, last used, actions (revoke).
6. Revoke sets `revoked_at` timestamp.

**Controller/form/list structure:**
- `IntegrationTokensController` — list and manage tokens
- `_list_tokens.blade.php` — list partial
- `_form_token.blade.php` — creation form
- `_modal_token_created.blade.php` — one-time token display

---

## 7. API Routes

### Current API Infrastructure

| Aspect | Finding |
|--------|---------|
| Route prefix | `/api` (config `igniter-api.prefix`) |
| Auth guard | `sanctum` (config `igniter-api.guard`) |
| Middleware | `['api', Authenticate::class]` |
| Rate limiting | 60/min per user or IP |
| Serializer | `JsonApiSerializer` (Fractal) |
| Token model | `Igniter\Api\Models\Token` extends `PersonalAccessToken` |
| Token table | `igniter_api_access_tokens` (no `ti_` prefix — Sanctum default) |
| Token creation | `POST /api/token` — email/password auth, returns Sanctum token |
| Existing endpoints | Full CRUD for: orders, menus, locations, customers, categories, reservations, reviews, users, etc. |

### Existing Auth Middleware

`Igniter\Api\Http\Middleware\Authenticate` (`vendor/tastyigniter/ti-ext-api/src/Http/Middleware/Authenticate.php`):
- Extends Laravel's `Illuminate\Auth\Middleware\Authenticate`
- Appends `sanctum` guard from config
- On failure: throws `Igniter\Api\Exceptions\AuthenticationException`

### Answers

**Where should `POST /api/voxpilot/orders` be registered?**

In the VoxPilot extension's boot method or a dedicated routes file:

```php
// extensions/igniter/voxpilot/routes/api.php
Route::prefix('api/voxpilot')
    ->middleware(['api', ResolveTenantFromToken::class])
    ->group(function () {
        Route::post('/orders', [VoxPilotOrderController::class, 'store']);
    });
```

**Do NOT register under the existing `/api/orders` endpoint** — that uses Sanctum user-based auth and JSON:API format. VoxPilot needs its own auth model (tenant API tokens, not user tokens).

**Middleware for `/api/voxpilot/orders`:**
1. `api` — Laravel API middleware stack (rate limiting, etc.)
2. `ResolveTenantFromToken` — custom middleware that:
   - Reads `Authorization: Bearer <token>` header
   - Hashes token, looks up in `tenant_api_tokens`
   - Checks `revoked_at IS NULL`
   - Resolves tenant and default location
   - Attaches tenant context to request
   - Returns 401 on invalid/revoked token

**Why not use Sanctum?**

Sanctum tokens are tied to a `tokenable` (User or Customer model). VoxPilot tokens should be tied to a **Tenant**, not a user. Custom token table is cleaner:
- No polymorphic type confusion
- Token scopes are VoxPilot-specific (`orders:create`)
- No conflict with existing Sanctum behavior

---

## 8. Token / Security Model

### Existing Token System

`Igniter\Api\Models\Token` (`vendor/tastyigniter/ti-ext-api/src/Models/Token.php`):
- Extends `Laravel\Sanctum\PersonalAccessToken`
- Table: `igniter_api_access_tokens`
- Columns: `id`, `tokenable_type`, `tokenable_id`, `name`, `token` (SHA-256 hash), `abilities` (JSON), `last_used_at`, `created_at`, `updated_at`
- Token creation: `Token::createToken($tokenable, $name, $abilities)` — generates 80-char random string, hashes with SHA-256, returns `NewAccessToken` with `id|plaintext`
- Abilities: customers get restricted set, admins get `['*']`

### Recommendation: Custom Token Table

Do NOT reuse `igniter_api_access_tokens`. Create a separate `voxpilot_tenant_api_tokens` table.

**Reasons:**
1. Sanctum tokens are polymorphic on `tokenable_type/tokenable_id` — VoxPilot tokens belong to Tenant, not User/Customer.
2. VoxPilot tokens need `tenant_id`, `default_location_id`, `revoked_at`, `created_by_user_id` — fields Sanctum doesn't have.
3. Keeping them separate prevents any interference with existing TI API auth.

### Proposed Schema

```sql
CREATE TABLE voxpilot_tenant_api_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    default_location_id INT UNSIGNED NULL,
    name            VARCHAR(255) NOT NULL,
    token_hash      VARCHAR(64) NOT NULL,      -- SHA-256 hex
    abilities       JSON NULL,                  -- ['orders:create', 'orders:read']
    last_used_at    TIMESTAMP NULL,
    revoked_at      TIMESTAMP NULL,
    created_by_user_id INT UNSIGNED NULL,       -- FK to admin_users.user_id
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_token_hash (token_hash),
    INDEX idx_tenant_id (tenant_id),
    UNIQUE KEY uk_token_hash (token_hash),

    CONSTRAINT fk_vp_token_tenant FOREIGN KEY (tenant_id) REFERENCES voxpilot_tenants(id),
    CONSTRAINT fk_vp_token_location FOREIGN KEY (default_location_id) REFERENCES ti_locations(location_id),
    CONSTRAINT fk_vp_token_user FOREIGN KEY (created_by_user_id) REFERENCES ti_admin_users(user_id)
);
```

### Token Lifecycle

1. **Generation:** Admin clicks "Generate Token" in Integrations UI. System generates 80-char `Str::random(80)`, hashes with `hash('sha256', $plain)`, stores hash. Returns `tokenId|plainTextToken` **once**.
2. **Lookup:** On API request, split `Bearer tokenId|plainText`, find record by `id`, verify `hash('sha256', $plainText) === token_hash`.
3. **Rotation:** Generate new token, revoke old one. No in-place rotation.
4. **Revocation:** Set `revoked_at = now()`. Token remains in table for audit trail.
5. **Logging:** Never log raw tokens. Log token ID and name only. Mask in error responses.

---

## 9. Multi-Tenancy Implementation Strategy

### Options Evaluated

| Strategy | Pros | Cons | Verdict |
|----------|------|------|---------|
| Single DB, shared schema, `tenant_id` | Simple, TI-compatible, single deploy | Needs discipline on scoping | **Selected** |
| Database per tenant | Strong isolation | Massive complexity for TI fork, migration hell | Rejected |
| Location-as-tenant shortcut | Zero schema change | Breaks when tenant has multiple locations | Rejected |
| Hybrid (location-as-tenant for MVP, tenant table later) | Fast MVP | Migration pain later, wrong mental model | Rejected |

### Selected: Single DB, Shared Schema, `tenant_id`

### Tables Needing `tenant_id` Immediately (Vertical Slice)

| Table | Column | Notes |
|-------|--------|-------|
| `ti_locations` | `tenant_id` | Every location belongs to one tenant |
| `ti_orders` | `tenant_id` | Denormalized from location for query efficiency |

Orders already have `location_id` which will resolve to a tenant, but adding `tenant_id` directly prevents expensive joins on every query.

### Tables That Can Wait

| Table | Why Wait |
|-------|----------|
| `ti_customers` | MVP doesn't create customers from voice orders |
| `ti_menus` | Menus are linked to locations via `Locationable`; location scoping = tenant scoping |
| `ti_categories` | Same as menus |
| `ti_reservations` | Not in VoxPilot scope |
| `ti_admin_users` | Membership table handles tenant-user relation |

### BelongsToTenant Trait

```php
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (!$model->tenant_id && $tenantId = resolve(TenantContext::class)->id()) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
```

### Global Scope Risks

| Risk | Mitigation |
|------|------------|
| Breaks admin screens that list all records | `TenantScope` must check `TenantContext::isActive()` — if no tenant in context (platform admin), skip scope |
| Breaks `OrderManager` session flow | `OrderManager` is frontend-facing; tenant context comes from location, which already scopes |
| Breaks migrations/seeds | Global scopes are automatically disabled during migrations |
| `super_user` can't see all tenants | `TenantScope` bypasses for `super_user` flag |
| Queue jobs lose tenant context | Jobs must serialize `tenant_id` and restore context in `handle()` |

### Platform Admin Bypass

```php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = resolve(TenantContext::class);

        if (!$context->isActive()) {
            return; // Platform admin or CLI — no tenant filter
        }

        $builder->where($model->qualifiedTenantColumn(), $context->id());
    }
}
```

### Queue Job Tenant Context

Every queued job that operates on tenant data must:
1. Accept `tenant_id` in constructor
2. Set `TenantContext` in `handle()` before any queries
3. Clear `TenantContext` in a `finally` block

---

## 10. Realtime / WebSockets

### Current State

| Aspect | Finding |
|--------|---------|
| Broadcasting config | `config/broadcasting.php` — default driver: `log` (disabled) |
| Broadcast extension | `tastyigniter/ti-ext-broadcast` installed |
| Reverb dependency | `laravel/reverb ^1.5` in broadcast extension's `composer.json` |
| Pusher dependency | `pusher/pusher-php-server ~7.0` in broadcast extension's `composer.json` |
| Existing broadcast event | `BroadcastOrderPlacedEvent` in `ti-ext-cart/src/Events/` |
| Broadcast channel | `igniter.order-placed.{location_id}` (public Channel, not private) |
| Event name | `cart.order-placed` |
| Broadcast data | order id, type, dateTime, locationName, items, total |
| Queue connection | `sync` (`.env` `QUEUE_CONNECTION=sync`) |

### Key Finding: Existing Broadcast Event

`BroadcastOrderPlacedEvent` (`vendor/tastyigniter/ti-ext-cart/src/Events/BroadcastOrderPlacedEvent.php`):
- Implements `ShouldBroadcast`
- Uses **public** `Channel` (not `PrivateChannel`) — **security risk** in multi-tenant
- Broadcasts on `igniter.order-placed.{location_id}` — location-scoped but not tenant-scoped
- Payload: order ID, type, items, total

### Answers

**Broadcasting driver for local development:**

**Laravel Reverb** — already a dependency. Self-hosted, no external service needed, native Laravel integration.

Required `.env`:
```
BROADCAST_DRIVER=reverb
REVERB_APP_ID=voxpilot-pos
REVERB_APP_KEY=voxpilot-local-key
REVERB_APP_SECRET=voxpilot-local-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
QUEUE_CONNECTION=redis   # or database
```

**Target channels (tenant-isolated, private):**

```
private-tenant.{tenantId}.location.{locationId}.orders
```

**Target event:**

```php
class VoxPilotOrderCreated implements ShouldBroadcast
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public int $tenantId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.location.{$this->order->location_id}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'voxpilot.order.created';
    }
}
```

**Channel authorization:**

In `routes/channels.php` or extension boot:
```php
Broadcast::channel('tenant.{tenantId}.location.{locationId}.orders', function ($user, $tenantId, $locationId) {
    // User must be member of tenant AND have access to location
    return $user->tenants->contains($tenantId)
        && $user->locations->contains($locationId);
});
```

**Minimal realtime UI for MVP:**

Do NOT modify existing order screen. Create a **minimal dedicated "Incoming Orders" page**:
- Simple Blade/Livewire page under admin
- Listens on `private-tenant.{tenantId}.location.{locationId}.orders`
- Shows toast/card for each new order
- Links to existing order detail page
- Can be the "kitchen display" screen

**Lowest risk approach:** Use Livewire with `wire:poll` for MVP instead of full WebSocket. Upgrade to Reverb in Phase 6 when queue worker is stable.

---

## 11. Local Development Setup

### Current Docker Setup

From `.env` and project context:
- Container: `pos` (web), `pos-db` (MySQL 8.4)
- Host: `pos.voxpilot.test`
- DB: `voxpilot_pos` on `pos-db:3306`
- Already running via `voxpilot-ai` Docker Compose (15 services)

### Required Configuration

**`/etc/hosts` (already configured per pos-local memory):**
```
127.0.0.1  pos.voxpilot.test
```

**`.env` additions for VoxPilot features:**
```env
# Existing
APP_URL=https://pos.voxpilot.test
DB_CONNECTION=mysql
DB_HOST=pos-db
DB_PORT=3306
DB_DATABASE=voxpilot_pos
DB_USERNAME=voxpilot_pos
DB_PREFIX=ti_

# Tenant bootstrap
VOXPILOT_DEFAULT_TENANT_NAME="Test Restaurant"

# Broadcasting (Phase 6)
BROADCAST_DRIVER=reverb
REVERB_APP_ID=voxpilot-pos
REVERB_APP_KEY=voxpilot-local-key
REVERB_APP_SECRET=voxpilot-local-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080

# Queue (needed for broadcasts)
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

# Session (already file-based, no change needed)
SESSION_DRIVER=file
```

**Commands:**
```bash
# Migration
docker exec -it pos php artisan migrate

# Seed default tenant (Phase 2)
docker exec -it pos php artisan voxpilot:bootstrap-tenant

# Queue worker (Phase 6)
docker exec -it pos php artisan queue:work redis --queue=default

# Reverb WebSocket server (Phase 6)
docker exec -it pos php artisan reverb:start

# Scheduler (if needed)
docker exec -it pos php artisan schedule:work
```

**Test curl:**
```bash
# Generate token first via admin UI, then:
curl -X POST https://pos.voxpilot.test/api/voxpilot/orders \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Idempotency-Key: vp_order_123" \
  -d '{
    "external_order_id": "vp_order_123",
    "call_sid": "CAxxxxxxxx",
    "source": "voice",
    "customer": {
      "name": "Gabriel",
      "phone": "+50682831970"
    },
    "fulfillment": {
      "type": "pickup",
      "requested_time": "ASAP"
    },
    "items": [
      {"name": "Large Margherita", "quantity": 1, "notes": "Thin crust"},
      {"name": "Soda", "quantity": 2}
    ],
    "notes": "Created from VoxPilot voice call"
  }'
```

---

## 12. Database Migration Plan

### Proposed Migration Sequence

**Migration 1: Create `voxpilot_tenants` table**
```sql
CREATE TABLE voxpilot_tenants (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL UNIQUE,
    status          ENUM('active', 'suspended', 'trial') NOT NULL DEFAULT 'active',
    settings        JSON NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_slug (slug),
    INDEX idx_status (status)
);
```

**Migration 2: Create `voxpilot_tenant_memberships` table**
```sql
CREATE TABLE voxpilot_tenant_memberships (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,       -- FK to ti_admin_users.user_id
    role            VARCHAR(50) NOT NULL DEFAULT 'member',  -- owner, admin, member
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_user (tenant_id, user_id),
    CONSTRAINT fk_membership_tenant FOREIGN KEY (tenant_id) REFERENCES voxpilot_tenants(id),
    CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES ti_admin_users(user_id)
);
```

**Migration 3: Create `voxpilot_tenant_api_tokens` table**

(See schema in Section 8)

**Migration 4: Add `tenant_id` to `ti_locations`**
```sql
ALTER TABLE ti_locations
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER location_id;

-- Backfill: assign all existing locations to default tenant
UPDATE ti_locations SET tenant_id = (SELECT id FROM voxpilot_tenants LIMIT 1);

ALTER TABLE ti_locations
    MODIFY COLUMN tenant_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE ti_locations
    ADD CONSTRAINT fk_location_tenant FOREIGN KEY (tenant_id) REFERENCES voxpilot_tenants(id);

ALTER TABLE ti_locations
    ADD INDEX idx_location_tenant (tenant_id);
```

**Migration 5: Add `tenant_id` to `ti_orders`**
```sql
ALTER TABLE ti_orders
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER order_id;

-- Backfill: derive tenant from location
UPDATE ti_orders o
    JOIN ti_locations l ON o.location_id = l.location_id
    SET o.tenant_id = l.tenant_id;

-- Orders without location get default tenant
UPDATE ti_orders SET tenant_id = (SELECT id FROM voxpilot_tenants LIMIT 1)
    WHERE tenant_id IS NULL;

ALTER TABLE ti_orders
    MODIFY COLUMN tenant_id BIGINT UNSIGNED NOT NULL;

ALTER TABLE ti_orders
    ADD CONSTRAINT fk_order_tenant FOREIGN KEY (tenant_id) REFERENCES voxpilot_tenants(id);

ALTER TABLE ti_orders
    ADD INDEX idx_order_tenant (tenant_id);
```

**Migration 6: Create `voxpilot_order_metadata` table**
```sql
CREATE TABLE voxpilot_order_metadata (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            INT UNSIGNED NOT NULL,
    external_order_id   VARCHAR(255) NOT NULL,
    call_sid            VARCHAR(255) NULL,
    source              VARCHAR(50) NOT NULL DEFAULT 'voice',
    transcript          TEXT NULL,
    raw_payload         JSON NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_order_id (order_id),
    INDEX idx_external_order_tenant (external_order_id),
    CONSTRAINT fk_vp_meta_order FOREIGN KEY (order_id) REFERENCES ti_orders(order_id)
);
```

### Rollback Considerations

- Each migration has a `down()` that drops columns/tables in reverse order.
- Backfill migrations are **not reversible** — data mapping is one-way.
- `tenant_id` removal from `ti_locations` and `ti_orders` would orphan tenant references.

### Unique Constraints That Must Become Tenant-Aware

| Table | Constraint | Change Needed |
|-------|-----------|---------------|
| `ti_locations` | `permalink_slug` unique | Make composite: `(tenant_id, permalink_slug)` |
| `voxpilot_order_metadata` | `external_order_id` | Make composite: `(tenant_id, external_order_id)` — needs `tenant_id` column on metadata OR enforce at application level via order's tenant |
| `ti_admin_users` | `email` unique | Keep global — users are global, membership is per-tenant |
| `ti_customers` | `email` unique | Keep global for MVP — revisit if tenant-scoping customers |

---

## 13. Endpoint Design

### `POST /api/voxpilot/orders`

**Route:** `POST /api/voxpilot/orders`

**Middleware stack:**
1. `api` — rate limiting (60/min), JSON parsing
2. `voxpilot.auth` — custom `ResolveTenantFromToken` middleware

**Auth flow:**
1. Read `Authorization: Bearer {tokenId}|{plainText}` header
2. Split on `|` — extract `tokenId` and `plainText`
3. Query `voxpilot_tenant_api_tokens` by `id = tokenId`
4. Verify `hash('sha256', plainText) === token_hash`
5. Check `revoked_at IS NULL`
6. Update `last_used_at`
7. Set `TenantContext` with tenant and default location
8. On failure: `401 Unauthorized` with error body (no token details in response)

**Tenant resolution:**
- Token carries `tenant_id` — always resolved.

**Location resolution (priority order):**
1. `location_id` in payload (if provided, must belong to token's tenant — 403 otherwise)
2. Token's `default_location_id` (if set)
3. Tenant's single location (if tenant has exactly one)
4. Error: `422 Unprocessable Entity` — location required

**Validation rules:**
```php
[
    'external_order_id'          => ['required', 'string', 'max:255'],
    'call_sid'                   => ['nullable', 'string', 'max:255'],
    'source'                     => ['nullable', 'string', 'in:voice,chat,web,api'],
    'location_id'                => ['nullable', 'integer', 'exists:locations,location_id'],
    'customer.name'              => ['required', 'string', 'max:100'],
    'customer.phone'             => ['nullable', 'string', 'max:30'],
    'customer.email'             => ['nullable', 'email', 'max:96'],
    'fulfillment.type'           => ['required', 'string', 'in:pickup,delivery'],
    'fulfillment.requested_time' => ['nullable', 'string'],
    'items'                      => ['required', 'array', 'min:1'],
    'items.*.name'               => ['required', 'string', 'max:255'],
    'items.*.quantity'           => ['required', 'integer', 'min:1'],
    'items.*.notes'              => ['nullable', 'string', 'max:500'],
    'items.*.unit_price'         => ['nullable', 'numeric', 'min:0'],
    'notes'                      => ['nullable', 'string', 'max:1000'],
    'transcript'                 => ['nullable', 'string'],
    'raw'                        => ['nullable', 'array'],
]
```

**Idempotency:**
- Key: `external_order_id` scoped to tenant
- Before creating: check `voxpilot_order_metadata` for existing `external_order_id` where order's `tenant_id` matches
- If exists: return existing order with `200 OK` (not 201)
- If not: create order, return `201 Created`

**Order creation flow:**
1. Validate payload
2. Check idempotency
3. Resolve location
4. Map `fulfillment.type`: `pickup` → `collection`, `delivery` → `delivery`
5. Split `customer.name` into `first_name` / `last_name`
6. Create `Order` record:
   - `tenant_id` from token context
   - `location_id` from resolution
   - `first_name`, `last_name`, `email`, `telephone` from customer
   - `order_type` from fulfillment mapping
   - `order_date` = today, `order_time` = now (or parsed from `requested_time`)
   - `order_time_is_asap` = `requested_time === 'ASAP'`
   - `comment` from `notes`
   - `status_id` = configurable "new/pending" status
   - `processed` = false
   - `payment` = null (voice orders are typically pay-on-pickup)
7. Create `OrderMenu` records for each item:
   - Try fuzzy match `name` against `ti_menus` for `menu_id` (optional — null if no match)
   - Set `name`, `quantity`, `price` (from `unit_price` or 0), `comment` from `notes`
8. Create `OrderTotal` record for subtotal/total
9. Create `voxpilot_order_metadata` record with `external_order_id`, `call_sid`, `source`, `transcript`, `raw_payload`
10. Dispatch `VoxPilotOrderCreated` event (broadcasts to tenant/location channel)
11. Return response

**Response format (201 Created):**
```json
{
    "data": {
        "order_id": 42,
        "external_order_id": "vp_order_123",
        "status": "pending",
        "location": {
            "id": 1,
            "name": "Bella Napoli Downtown"
        },
        "items_count": 2,
        "created_at": "2026-09-25T14:30:00Z"
    }
}
```

**Error format:**
```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The items field is required.",
        "details": {
            "items": ["The items field is required."]
        }
    }
}
```

Error codes: `AUTHENTICATION_FAILED`, `TOKEN_REVOKED`, `VALIDATION_ERROR`, `LOCATION_NOT_FOUND`, `LOCATION_FORBIDDEN`, `LOCATION_REQUIRED`, `INTERNAL_ERROR`.

**Logging strategy:**
- Log request (excluding raw token) at INFO level
- Log order creation success with `order_id` and `external_order_id`
- Log auth failures at WARNING level with IP and token ID (not raw token)
- Never log: raw token value, `raw_payload` content at default level

**Retry safety:**
- Idempotency on `external_order_id` per tenant makes retries safe
- `X-Idempotency-Key` header supported as alias for `external_order_id`
- Concurrent requests with same `external_order_id`: use DB unique constraint + catch duplicate key exception

---

## 14. Testing Plan

### Required Tests

| # | Test | Type | Priority |
|---|------|------|----------|
| 1 | Tenant can be created | Unit | P0 |
| 2 | User/admin belongs to tenant via membership | Unit | P0 |
| 3 | Location belongs to tenant | Unit | P0 |
| 4 | API token resolves tenant correctly | Integration | P0 |
| 5 | Invalid token returns 401 | Integration | P0 |
| 6 | Revoked token returns 401 | Integration | P0 |
| 7 | `POST /api/voxpilot/orders` creates order with correct tenant/location | Integration | P0 |
| 8 | Idempotency: same `external_order_id` returns existing order | Integration | P0 |
| 9 | Location from another tenant returns 403 | Integration | P0 |
| 10 | Order belongs to correct tenant and location | Unit | P0 |
| 11 | Tenant A user cannot query Tenant B orders | Integration | P0 |
| 12 | Broadcast channel auth rejects unauthorized user | Integration | P1 |
| 13 | Broadcast channel auth allows authorized tenant/location user | Integration | P1 |
| 14 | `VoxPilotOrderCreated` event dispatched on external order | Unit | P1 |
| 15 | Malformed payload returns 422 with field errors | Integration | P0 |
| 16 | Empty items array returns 422 | Integration | P0 |
| 17 | Token `last_used_at` updated on successful request | Integration | P2 |
| 18 | Token without `default_location_id` requires location in payload | Integration | P1 |
| 19 | `VoxPilotOrderMetadata` created with correct fields | Unit | P1 |
| 20 | Order status set to configurable initial status | Unit | P2 |

### Test Structure

```
extensions/igniter/voxpilot/tests/
├── Unit/
│   ├── Models/
│   │   ├── TenantTest.php
│   │   ├── TenantApiTokenTest.php
│   │   ├── TenantMembershipTest.php
│   │   └── VoxPilotOrderMetadataTest.php
│   ├── Services/
│   │   └── OrderIngestionServiceTest.php
│   └── Scopes/
│       └── TenantScopeTest.php
├── Integration/
│   ├── Api/
│   │   ├── OrderIngestionEndpointTest.php
│   │   ├── TokenAuthenticationTest.php
│   │   └── IdempotencyTest.php
│   └── Broadcasting/
│       └── ChannelAuthorizationTest.php
└── TestCase.php
```

### Test Database

Use SQLite in-memory or a dedicated test MySQL database. Ensure `DB_PREFIX=ti_` is set in test config.

---

## 15. Risk Assessment

### Critical Risks

| # | Risk | Impact | Likelihood | Mitigation |
|---|------|--------|------------|------------|
| 1 | **Cross-tenant data leakage** | Critical | Medium | `TenantScope` global scope on all tenant-owned models; test coverage for isolation; never bypass scope except for super_user |
| 2 | **Global scopes breaking existing TI admin screens** | High | High | `TenantScope` must be conditional — only applies when `TenantContext` is active. Super users bypass. Test all admin CRUD operations after adding scopes |
| 3 | **Order creation bypassing native validation** | Medium | Medium | Dedicated ingestion service with its own validation; don't modify `OrderManager`; test order integrity |
| 4 | **Token leakage in logs/responses** | High | Low | Never log raw tokens; hash before storage; mask in error responses; show plaintext only once at creation |
| 5 | **WebSocket auth leakage** | High | Medium | Use `PrivateChannel` (not `Channel`); channel auth checks tenant membership AND location access |
| 6 | **Queued jobs without tenant context** | High | High | All VoxPilot jobs must serialize `tenant_id`; middleware or base job class enforces context restoration |
| 7 | **Upgrade pain from modifying vendor/core** | Medium | Low | Extension-based approach minimizes core changes; only add columns to existing tables via extension migrations |
| 8 | **Locationable scope conflicts with TenantScope** | Medium | Medium | Test that both scopes compose correctly; TenantScope should apply first (broader filter) |

### Moderate Risks

| # | Risk | Impact | Likelihood | Mitigation |
|---|------|--------|------------|------------|
| 9 | Incorrect menu item matching for voice orders | Medium | High | MVP: store item name as-is, no menu matching required; menu matching is Phase 8+ |
| 10 | Local/prod environment differences | Medium | Medium | Docker Compose ensures parity; document all env vars |
| 11 | Deployment complexity with Reverb | Medium | Medium | Defer WebSocket to Phase 6; use Livewire polling for MVP incoming orders screen |
| 12 | `is_default` location flag breaks with multi-tenant | Low | Medium | Make `is_default` tenant-scoped in migration |
| 13 | `permalink_slug` uniqueness breaks with multi-tenant | Low | Medium | Composite unique on `(tenant_id, permalink_slug)` |

---

## 16. Recommended Implementation Phases

### Phase 1: Audit and Design ✅

- **Goal:** Map codebase, identify integration points, design schemas.
- **Status:** This document.
- **Risk:** None.

### Phase 2: Tenant Foundation and Bootstrap

- **Goal:** Create tenant model, migration, bootstrap command.
- **Files:**
  - `extensions/igniter/voxpilot/src/Models/Tenant.php`
  - `extensions/igniter/voxpilot/src/Models/TenantMembership.php`
  - `extensions/igniter/voxpilot/database/migrations/xxxx_create_voxpilot_tenants_table.php`
  - `extensions/igniter/voxpilot/database/migrations/xxxx_create_voxpilot_tenant_memberships_table.php`
  - `extensions/igniter/voxpilot/src/Console/BootstrapTenant.php`
  - `extensions/igniter/voxpilot/src/Extension.php`
  - `extensions/igniter/voxpilot/composer.json`
- **DB changes:** 2 new tables
- **Verification:** `php artisan voxpilot:bootstrap-tenant` creates tenant, assigns existing location and admin user.
- **Risk:** Low

### Phase 3: Tenant-Aware Locations and Users

- **Goal:** Add `tenant_id` to locations, implement `TenantScope`, tenant context.
- **Files:**
  - `extensions/igniter/voxpilot/database/migrations/xxxx_add_tenant_id_to_locations.php`
  - `extensions/igniter/voxpilot/src/Models/Concerns/BelongsToTenant.php`
  - `extensions/igniter/voxpilot/src/Models/Scopes/TenantScope.php`
  - `extensions/igniter/voxpilot/src/Classes/TenantContext.php`
- **DB changes:** `tenant_id` column on `ti_locations`, backfill
- **Verification:** Admin sees only their tenant's locations. Super user sees all.
- **Risk:** Medium — global scope could break admin screens

### Phase 4: Tenant API Tokens and Integrations UI

- **Goal:** Token CRUD, Integrations admin page, token generation flow.
- **Files:**
  - `extensions/igniter/voxpilot/database/migrations/xxxx_create_voxpilot_tenant_api_tokens.php`
  - `extensions/igniter/voxpilot/src/Models/TenantApiToken.php`
  - `extensions/igniter/voxpilot/src/Http/Controllers/Integrations.php`
  - `extensions/igniter/voxpilot/src/Http/Middleware/ResolveTenantFromToken.php`
  - `extensions/igniter/voxpilot/resources/views/integrations/` (Blade views)
  - `extensions/igniter/voxpilot/resources/models/integrations/` (YAML configs)
- **DB changes:** 1 new table
- **Verification:** Admin generates token, sees it once, can list/revoke. Token resolves tenant via API.
- **Risk:** Low

### Phase 5: External Order Ingestion Endpoint

- **Goal:** `POST /api/voxpilot/orders` fully functional.
- **Files:**
  - `extensions/igniter/voxpilot/routes/api.php`
  - `extensions/igniter/voxpilot/src/Http/Controllers/VoxPilotOrderController.php`
  - `extensions/igniter/voxpilot/src/Http/Requests/VoxPilotOrderRequest.php`
  - `extensions/igniter/voxpilot/src/Services/OrderIngestionService.php`
  - `extensions/igniter/voxpilot/src/Models/VoxPilotOrderMetadata.php`
  - `extensions/igniter/voxpilot/database/migrations/xxxx_create_voxpilot_order_metadata.php`
  - `extensions/igniter/voxpilot/database/migrations/xxxx_add_tenant_id_to_orders.php`
- **DB changes:** 1 new table, `tenant_id` on `ti_orders`, backfill
- **Verification:** `curl POST` with valid token creates order visible in admin.
- **Risk:** Medium — order creation must not break existing flows

### Phase 6: Realtime WebSocket Order Events

- **Goal:** Broadcast `VoxPilotOrderCreated` on private tenant/location channel.
- **Files:**
  - `extensions/igniter/voxpilot/src/Events/VoxPilotOrderCreated.php`
  - `extensions/igniter/voxpilot/routes/channels.php`
  - `.env` updates for Reverb
  - Docker Compose: add Reverb service, Redis if not present
- **DB changes:** None
- **Verification:** Order creation triggers event visible in Reverb logs. Channel auth rejects unauthorized user.
- **Risk:** Medium — requires queue worker and Reverb

### Phase 7: Minimal Incoming Orders UI

- **Goal:** Admin page showing incoming VoxPilot orders in realtime.
- **Files:**
  - `extensions/igniter/voxpilot/src/Http/Controllers/IncomingOrders.php`
  - `extensions/igniter/voxpilot/resources/views/incomingorders/` (Blade/Livewire)
  - `extensions/igniter/voxpilot/resources/js/incoming-orders.js` (Echo listener)
- **DB changes:** None
- **Verification:** Open page, send test order via curl, order appears without refresh.
- **Risk:** Low

### Phase 8: Tests and Hardening

- **Goal:** Full test suite, edge case coverage, security hardening.
- **Files:** `extensions/igniter/voxpilot/tests/`
- **DB changes:** None
- **Verification:** All 20 test cases pass. Manual pen-test of cross-tenant isolation.
- **Risk:** Low

### Phase 9: Coolify Deployment

- **Goal:** Deploy to production via Coolify.
- **Files:**
  - `docker-compose.yml` updates
  - Coolify env configuration
  - SSL/TLS for WebSocket
  - Queue worker supervisor config
- **DB changes:** Run migrations on prod
- **Verification:** Full E2E: VoxPilot sends order, POS receives it, admin sees it in realtime.
- **Risk:** Medium

---

## 17. Final Recommendation

### What to implement first

**Phase 2 + 3 + 4 + 5 as a single vertical slice.** The safest vertical slice is:

1. Create tenant model and bootstrap command (Phase 2)
2. Add `tenant_id` to locations with backfill (Phase 3)
3. Create token table and generation UI (Phase 4)
4. Build `POST /api/voxpilot/orders` endpoint (Phase 5)

This gets you from zero to "VoxPilot can send an order to the POS via API token" without WebSocket complexity.

### What should NOT be touched yet

- Customer model/table — no tenant scoping, no customer creation from voice orders
- Menu matching — store item names as-is, match later
- `OrderManager` class — it's session-bound, don't modify it
- WebSocket/Reverb — defer to Phase 6 after core flow works
- Existing TI admin screens — don't modify, only add new ones
- Existing API endpoints (`/api/orders`, `/api/menus`, etc.) — leave untouched

### Extension vs Core vs Hybrid

**Hybrid, extension-heavy:**
- All VoxPilot code lives in `extensions/igniter/voxpilot/` as a TastyIgniter extension
- Extension adds columns to existing tables via its own migrations
- Extension registers routes, navigation, permissions via Extension class
- Core TI vendor files are **never modified**

### The safest vertical slice

Token auth → tenant resolution → order creation → metadata storage → success response.

No WebSocket. No customer creation. No menu matching. No realtime UI. Just the API contract.

### Next prompt for implementation

After this audit is reviewed and approved:

```
Act as a senior Laravel/TastyIgniter developer.

Create the VoxPilot TastyIgniter extension at extensions/igniter/voxpilot/ following
VOXPILOT_POS_AUDIT.md phases 2-5.

Implement in order:
1. Extension scaffold (composer.json, Extension.php)
2. Tenant model + migration + bootstrap artisan command
3. TenantMembership model + migration
4. TenantApiToken model + migration + ResolveTenantFromToken middleware
5. Add tenant_id to ti_locations migration with backfill
6. Add tenant_id to ti_orders migration with backfill
7. VoxPilotOrderMetadata model + migration
8. OrderIngestionService
9. VoxPilotOrderController + VoxPilotOrderRequest
10. API routes (POST /api/voxpilot/orders)
11. Integrations admin controller + views for token management

Test with: php artisan voxpilot:bootstrap-tenant then curl POST.
Do not implement WebSocket, realtime UI, or customer creation.
Reference real files/classes found in the audit.
```

---

*End of audit. Generated 2026-09-25.*
