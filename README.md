# artifact-host

Deploy static sites (HTML/JS/CSS) from any MCP-aware client to a Plesk-managed VPS in one tool call.

Two halves:

- **`api/`** — Laravel 11 HTTPS API that runs ON the VPS, wraps Plesk REST + CLI, and writes to vhost doc roots.
- **`mcp/`** — TypeScript MCP server (stdio) that runs on the user's machine and calls the API. Distributed via `npx`.

## Architecture

```
MCP client (Claude Desktop, etc.)
       │  stdio (JSON-RPC)
       ▼
 artifact-host-mcp  ──────► HTTPS (Bearer api_key)
   (npx, local)                   │
                                  ▼
                          Laravel API on VPS
                                  │
                       ┌──────────┴──────────┐
                       ▼                     ▼
              `sudo plesk bin ...`   local FS writes
              (subdomain + LE)       (atomic-ish swap)
                       │
                       ▼
              Plesk + nginx serves
              <sub>.hosting.champlinenterprises.com
```

## Tools

| Tool | What it does |
|---|---|
| `deploy_site(subdomain, files[])` | Creates Plesk subdomain, uploads files, requests LE cert, returns live URL |
| `list_sites()` | Lists all sites for the authenticated user |
| `update_site(subdomain, files[])` | Atomic-ish replace of all files |
| `delete_site(subdomain)` | Tears down filesystem + subdomain + DB row |
| `get_logs(subdomain, lines)` | Tails access + error logs |

`deploy_site` and `update_site` require an `index.html` at the root of `files[]`.

## Prereqs (VPS)

- Plesk Obsidian 18.x with Let's Encrypt extension installed
- The apex domain (default: `hosting.champlinenterprises.com`) created in Plesk as a subdomain or domain
- Wildcard DNS: `*.hosting.champlinenterprises.com` → VPS IP
- PHP 8.3+ with `mysqli`, `pdo_mysql`, `bcmath`, `mbstring`, `intl`, `xml`, `curl`
- MySQL 8.x
- The Laravel app must run as a user that owns `/var/www/vhosts/<apex>/...` AND has `NOPASSWD` sudo for `plesk` and `tail` (so `PleskService` can shell out)

## Install — API half

On the VPS:

```bash
# 1. Provision DB (script writes creds you can paste into .env)
~/.claude/bin/create_project_db.sh artifact-host

# 2. Clone + install
cd /var/www/vhosts/<apex>/  # or your preferred app dir
git clone <this-repo> artifact-host && cd artifact-host/api
composer install --no-dev --prefer-dist --optimize-autoloader

# 3. Configure
cp .env.example .env
$EDITOR .env   # fill DB creds, PLESK_API_KEY, ARTIFACTHOST_APEX, ARTIFACTHOST_LE_EMAIL
php artisan key:generate

# 4. Migrate + seed
php artisan migrate --seed --force

# 5. Sudoers (one-time, as root) — let the app run plesk + tail
cat > /etc/sudoers.d/artifact-host <<EOF
<app-user> ALL=(root) NOPASSWD: /usr/sbin/plesk, /usr/bin/tail
EOF
chmod 440 /etc/sudoers.d/artifact-host

# 6. Generate Plesk REST API key (admin-level)
plesk bin secret_key --create -ip-address <vps-ip> -description "artifact-host"
# → paste output into .env as PLESK_API_KEY

# 7. Create your first user
php artisan users:create kevin@kevinchamplin.com pro
# → save the printed API key — it is not stored in plaintext

# 8. Wire up nginx vhost in Plesk for hosting-api.<your-apex> → /api/public
#    + LE cert via the Plesk panel
```

### systemd unit (queue worker — optional, for future async jobs)

artifact-host is sync-only in v1, so no worker needed. If you later move deploys to a queue:

```ini
# /etc/systemd/system/artifact-host-worker.service
[Unit]
Description=artifact-host queue worker
After=network.target mysqld.service

[Service]
User=<app-user>
Restart=always
ExecStart=/opt/plesk/php/8.3/bin/php /var/www/vhosts/<apex>/artifact-host/api/artisan queue:work --sleep=2 --tries=3
WorkingDirectory=/var/www/vhosts/<apex>/artifact-host/api

[Install]
WantedBy=multi-user.target
```

## Install — MCP half

The MCP server runs locally on each user's machine.

```bash
cd mcp
npm install
npm run build
```

Publish to npm (after testing):

```bash
npm publish --access public
```

### MCP client config

Drop this into your MCP client's config (e.g. Claude Desktop's `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "artifact-host": {
      "command": "npx",
      "args": ["-y", "artifact-host-mcp"],
      "env": {
        "ARTIFACT_HOST_API_URL": "https://hosting-api.champlinenterprises.com",
        "ARTIFACT_HOST_API_KEY": "ah_xxx..."
      }
    }
  }
}
```

For local dev (before publishing):

```json
{
  "mcpServers": {
    "artifact-host": {
      "command": "node",
      "args": ["/absolute/path/to/artifact-host/mcp/dist/index.js"],
      "env": { "ARTIFACT_HOST_API_URL": "...", "ARTIFACT_HOST_API_KEY": "..." }
    }
  }
}
```

## Operations

### Create / rotate user keys

```bash
php artisan users:create alice@example.com free
php artisan users:rotate-key alice@example.com   # invalidates old key
```

### Reserved subdomains

Seed list: `www, api, mcp, panel, admin, mail, ftp, app, docs, blog, status, billing, dashboard`. Add more via the `reserved_subdomains` table.

### Atomic-ish file replacement

`update_site` writes to `<doc_root>.deploy-<rand>`, swaps it with two `mv` calls, then deletes the old root. There's a sub-second window where the doc root doesn't exist (404 served). Truly atomic replacement requires nginx config-level docroot pointers — out of scope for v1.

## Gotchas / known limits

- **Let's Encrypt rate limit** — 50 certs/week per *registered* domain (i.e. `champlinenterprises.com` as a whole, not per subdomain). For high churn, plan a wildcard cert at the apex (`*.hosting.champlinenterprises.com`) — out of scope for v1, but it's a one-line `plesk ext letsencrypt -wildcard ...` swap when needed.
- **Plesk subdomain creation requires CLI** — REST v2 doesn't expose a clean subdomain-create endpoint, so the API shells out to `plesk bin subdomain --create`. This is why the API has to run on-box.
- **Per-deploy size cap** — `ARTIFACTHOST_MAX_FILE_BYTES` (5 MB) and `ARTIFACTHOST_MAX_TOTAL_BYTES` (100 MB). Tune in `.env`.
- **No queue in v1** — every `deploy_site` call blocks until Plesk is done provisioning (typically 5-15s). For multi-user scale, move LE issuance to a queued job.
- **One shared FS user** — v1 assumes the API user owns the doc roots. Per-tenant FS isolation (one Plesk subscription per artifact-host user) is a future tier.
- **`tier=dynamic`** is reserved in the schema for future PHP-enabled sites; v1 deploys are static-only regardless of tier.

## Subdomain rules

`1-63` chars, lowercase alphanumeric + hyphens, no leading/trailing hyphen, not in `reserved_subdomains`, not already taken.

## Project layout

```
artifact-host/
├── api/                              # Laravel 11
│   ├── app/
│   │   ├── Console/Commands/         # users:create, users:rotate-key
│   │   ├── Exceptions/PleskException.php
│   │   ├── Http/
│   │   │   ├── Controllers/          # Deploy, Site
│   │   │   └── Middleware/ApiKeyAuth.php
│   │   ├── Models/                   # User, Site, Deploy, ReservedSubdomain
│   │   ├── Rules/Subdomain.php
│   │   └── Services/                 # PleskService, SftpService
│   ├── config/artifacthost.php
│   ├── database/
│   │   ├── migrations/               # users, sites, deploys, reserved_subdomains
│   │   └── seeders/ReservedSubdomainSeeder.php
│   ├── routes/api.php
│   └── .env.example
└── mcp/                              # TypeScript MCP server
    ├── src/
    │   ├── config.ts                 # env loader
    │   ├── client.ts                 # HTTPS client to API
    │   ├── tools.ts                  # tool defs + dispatch
    │   └── index.ts                  # stdio bootstrap
    ├── package.json
    ├── tsconfig.json
    └── .env.example
```
