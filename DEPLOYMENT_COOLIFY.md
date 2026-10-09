# VoxPilot POS — Coolify Deployment Guide

**Target:** `https://pos.voxpilothq.io`
**Branch:** `main`
**Repo:** `voxpilot-ai/voxpilot-pos-core`

---

## 1. Architecture Overview

```
Browser ─── wss://pos.voxpilothq.io/app/{key} ───┐
Browser ─── https://pos.voxpilothq.io/admin ──────┤
curl    ─── https://pos.voxpilothq.io/api/* ──────┤
                                                   │
                    Coolify Reverse Proxy (TLS)    │
                         │                         │
            ┌────────────┴────────────┐            │
            │                         │            │
     /app/* /apps/*             everything else    │
            │                         │            │
     Reverb (:6001)          Apache (:80)          │
            │                         │            │
            └─────── pos container ───┘            │
                         │                         │
                    pos-db (MySQL 8.4)             │
                    pos-redis (Redis 7)            │
```

Single `pos` container runs both Apache and Reverb via Supervisor. MySQL and Redis are separate services.

---

## 2. Coolify Setup Steps

### 2.1 Create New Resource

1. Go to your Coolify project
2. Click **+ New Resource**
3. Select **Docker Compose**
4. Connect GitHub repo: `voxpilot-ai/voxpilot-pos-core`
5. Branch: `main`
6. Docker Compose file: `docker-compose.prod.yml`

### 2.2 Domain Configuration

- Set domain: `pos.voxpilothq.io`
- Map to service: `pos` on port `80`
- Enable HTTPS (Let's Encrypt)

### 2.3 WebSocket Proxy (CRITICAL)

Coolify must route WebSocket paths to Reverb (port 6001), not Apache (port 80).

**If Coolify uses Caddy:**
```
pos.voxpilothq.io {
    @reverb path /app/* /apps/*
    reverse_proxy @reverb pos:6001

    reverse_proxy pos:80 {
        header_up X-Forwarded-Proto https
    }
}
```

**If Coolify uses Traefik:**
Add a custom label or middleware to route `/app/` and `/apps/` to port 6001. Alternatively, expose port 6001 as a secondary port in Coolify's service config and add a path-based router:

```yaml
labels:
  - "traefik.http.routers.pos-ws.rule=Host(`pos.voxpilothq.io`) && PathPrefix(`/app/`, `/apps/`)"
  - "traefik.http.routers.pos-ws.service=pos-ws"
  - "traefik.http.services.pos-ws.loadbalancer.server.port=6001"
  - "traefik.http.routers.pos-ws.tls=true"
  - "traefik.http.routers.pos-ws.tls.certresolver=letsencrypt"
```

**If Coolify uses Nginx:**
```nginx
location ~ ^/(app|apps)/ {
    proxy_pass http://pos:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
}
```

**Verify after deploy:**
```bash
curl -sk --http1.1 -o /dev/null -w "%{http_code}" \
  -H "Connection: Upgrade" -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  "https://pos.voxpilothq.io/app/YOUR_REVERB_APP_KEY"
```
Expected: `101` (Switching Protocols)

---

## 3. Environment Variables

Set these in Coolify's environment variables section.

### Required — Generate unique values

```env
APP_KEY=base64:<generate with php artisan key:generate --show>
DB_PASSWORD=<strong-random-password>
REVERB_APP_ID=<generate: php -r "echo bin2hex(random_bytes(8));">
REVERB_APP_KEY=<generate: php -r "echo bin2hex(random_bytes(32));">
REVERB_APP_SECRET=<generate: php -r "echo bin2hex(random_bytes(32));">

# VoxPilot (SPEC-011): same values as the VoxPilot backend's POS_PROVISIONING_SECRET / POS_HMAC_SECRET
VOXPILOT_PROVISIONING_SECRET=<generate: php -r "echo bin2hex(random_bytes(32));">
VOXPILOT_HMAC_SECRET=<generate: php -r "echo bin2hex(random_bytes(32));">
```

### Required — Fixed values

```env
APP_NAME="VoxPilot POS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pos.voxpilothq.io

DB_CONNECTION=mysql
DB_HOST=pos-db
DB_PORT=3306
DB_DATABASE=voxpilot_pos
DB_USERNAME=voxpilot_pos
DB_PREFIX=ti_

CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=pos-redis
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_HOST=pos.voxpilothq.io
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=6001
REVERB_SERVER_SCHEME=http

# One location per VoxPilot restaurant: in `single` mode every admin is pinned to the default
# location and restaurant owners see no orders.
IGNITER_LOCATION_MODE=multiple

QUEUE_CONNECTION=database

# Where "Connect with VoxPilot" redirects (the VoxPilot app of this environment)
VOXPILOT_APP_INSTALL_URL=https://app.voxpilothq.io/integrations/pos/install
```

For a DEV POS, also set `APP_URL`, `REVERB_HOST` and `VOXPILOT_APP_INSTALL_URL` to the DEV domains (e.g. `https://app-dev.voxpilothq.io/integrations/pos/install`).

### Mail (required for the VoxPilot owner invite)

Provisioning e-mails each new restaurant owner a set-password link. With `MAIL_MAILER=log` (the default) nothing is sent and owners cannot sign in. `VOXPILOT_SEND_OWNER_INVITE=false` turns the invite off.

Set the store / site name in *Settings* to something neutral (e.g. "VoxPilot POS"): the invite subject uses it.

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@voxpilothq.io
MAIL_FROM_NAME="VoxPilot POS"
```

---

## 4. Services

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| `pos` | Built from Dockerfile | 80 (web), 6001 (Reverb) | Apache + Reverb via Supervisor |
| `pos-db` | `mysql:8.4` | 3306 (internal) | MySQL database |
| `pos-redis` | `redis:7-alpine` | 6379 (internal) | Cache + sessions |

No scheduler service needed (no scheduled tasks registered).
No queue worker needed (broadcasts use `ShouldBroadcastNow`).

---

## 5. Volumes

| Volume | Mount | Purpose |
|--------|-------|---------|
| `pos_db_data` | `/var/lib/mysql` | Database persistence |
| `pos_redis_data` | `/data` | Redis persistence |
| `pos_storage` | `/var/www/html/storage` | Logs, uploads, sessions |
| `pos_bootstrap_cache` | `/var/www/html/bootstrap/cache` | Laravel bootstrap cache |

---

## 6. First Deploy

The entrypoint handles most setup automatically. After first deploy:

### 6.1 Automatic (via entrypoint)

- Waits for MySQL
- Runs `php artisan igniter:install` (creates schema, seeds admin)
- Runs `php artisan migrate`
- Clears config/route/view caches
- Starts Supervisor (Apache + Reverb)

### 6.2 Manual — One-time setup

After the first deploy is up:

```bash
# Access the running container
docker exec -it voxpilot-pos sh

# Bootstrap VoxPilot tenant
php artisan voxpilot:bootstrap-tenant

# Verify Reverb is running
ps aux | grep reverb

# Verify broadcast route
php artisan route:list --path=broadcasting
```

### 6.3 Create Admin User

TastyIgniter's `igniter:install` creates the first admin user interactively. If the container runs non-interactively (Coolify default), create the admin manually:

```bash
docker exec -it voxpilot-pos php artisan tinker --execute="
\$user = new \Igniter\Admin\Models\User;
\$user->username = 'admin';
\$user->email = 'admin@voxpilothq.io';
\$user->password = 'CHANGE_THIS_PASSWORD';
\$user->name = 'Admin';
\$user->super_user = true;
\$user->is_activated = true;
\$user->save();
echo 'Admin created: admin@voxpilothq.io';
"
```

**Change the password immediately after first login.**

---

## 7. Smoke Test

After deploy is fully up:

### 7.1 Admin Access

1. Open `https://pos.voxpilothq.io/admin`
2. Login with admin credentials
3. Verify **Tools > VoxPilot > Integrations** exists
4. Generate an API token — **copy it immediately**

### 7.2 WebSocket

5. Go to **Tools > VoxPilot > Incoming Orders**
6. Verify green **"Listening (N)"** badge
7. Open browser console — should show `VoxPilot: WebSocket connected`

### 7.3 API + Realtime

8. Send test order:

```bash
curl -X POST https://pos.voxpilothq.io/api/voxpilot/orders \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "external_order_id": "prod_smoke_001",
    "source": "voice",
    "customer": {"name": "Smoke Test", "phone": "+34600000001"},
    "fulfillment": {"type": "pickup", "requested_time": "ASAP"},
    "items": [{"name": "Test Pizza", "quantity": 1, "unit_price": 10.00}],
    "notes": "Production smoke test"
  }'
```

9. Verify order appears instantly on Incoming Orders screen
10. Verify order appears in native **Orders** admin
11. Repeat same `external_order_id` — verify `is_duplicate: true`, no duplicate row

### 7.4 Expected Responses

**New order:**
```json
{"data":{"order_id":1,"external_order_id":"prod_smoke_001","status":"Received","location":{"id":1,"name":"..."},"items_count":1,"order_total":10,"is_duplicate":false}}
```

**Duplicate:**
```json
{"data":{"order_id":1,"external_order_id":"prod_smoke_001","status":"Received","location":{"id":1,"name":"..."},"items_count":1,"order_total":10,"is_duplicate":true}}
```

---

## 8. Post-Deploy Commands

Run after any code update:

```bash
docker exec -it voxpilot-pos sh -c '
  php artisan migrate --force --no-interaction
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
'
```

---

## 9. Rollback

Coolify supports rollback to previous deployment:

1. Go to Coolify dashboard > POS resource > Deployments
2. Click previous successful deployment
3. Click **Rollback**
4. Verify smoke test after rollback

If database migration needs rollback:
```bash
docker exec -it voxpilot-pos php artisan migrate:rollback --step=1
```

---

## 10. Troubleshooting

| Problem | Diagnosis | Fix |
|---------|-----------|-----|
| Container won't start | Check Coolify build logs | Usually missing env var or DB connection |
| 502 Bad Gateway | Apache not ready yet | Wait 30s for healthcheck; check `docker logs voxpilot-pos` |
| WebSocket 404 | Proxy not routing /app/* to :6001 | Configure path-based proxy (see section 2.3) |
| WebSocket 502 | Reverb not running | `docker exec voxpilot-pos ps aux \| grep reverb` |
| Channel auth 403 | Admin session not available | Verify you're logged into POS admin. Check cookie domain matches |
| No realtime events | Broadcaster can't reach Reverb | Check `REVERB_SERVER_HOST=0.0.0.0` (not external domain) |
| `BroadcastException cURL error 7` | Server-side using client-facing host | Set `REVERB_SERVER_HOST=0.0.0.0`, not `pos.voxpilothq.io` |
| MySQL connection refused | DB not ready or wrong credentials | Check `DB_HOST=pos-db`, verify `DB_PASSWORD` matches MySQL env |
| Redis connection refused | Redis not started | Check `REDIS_HOST=pos-redis`, verify pos-redis service is up |

---

## 11. Security Notes

- `APP_DEBUG=false` in production (never true)
- `APP_KEY` generated unique per deployment
- `REVERB_APP_SECRET` server-side only, never exposed to browser
- `REVERB_APP_KEY` is public (exposed in browser JS, same as Pusher key)
- API tokens are SHA-256 hashed in DB, plaintext shown only once at creation
- No production secrets in git — all via Coolify env vars
- `.env` is in `.gitignore` and `.dockerignore`
