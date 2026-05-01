# Changelog

All notable changes to artifact-host will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added (2026-05-01, sprints 1-5)
- **Magic-link auth + dashboard** at `/login`, `/dashboard`, `/dashboard/sites`, `/dashboard/account`. Per-user scoping; users only see their own sites.
- **Server-log analytics + opt-in pixel** — daily aggregates parsed every 15 min, 7-day chart on each site detail, top pages + referrers + browsers.
- **Custom domains** (Pro tier) — CNAME verify + Plesk symlink + LE per custom domain.
- **Light theme + Champlin Enterprises branding** — full visual overhaul. Fraunces serif headlines, warm cream paper background, yellow highlighter CTAs, CE silver mark in nav + footer ("a product by Champlin Enterprises, LLC").
- **Frictionless install** — three paths (terminal one-liner, hosted URL, copy-paste JSON) with giant copy buttons. `curl … | install.sh` safely merges into existing Claude Desktop config without clobbering other MCP servers.
- **Public endpoints**: `/install.json`, `/install.sh`, `/llms.txt`, `/sitemap.xml`, `/status`, `/robots.txt`. Custom 404 + 500 styled with the theme.
- **Note**: Stripe billing deferred. Beta is free; tier upgrades (for custom domains) are manual via email during the closed period.

### Frictionless onboarding (2026-05-01)
- **`signup(email)` MCP tool** + matching `POST /api/v1/auth/signup` endpoint (public, 5/hour/IP rate limit). First-deploy flow needs zero out-of-band coordination — vibe coders install the MCP, ask the AI to deploy, AI calls signup automatically.
- **Local key persistence** at `~/.artifact-host/auth.json` (mode 600) — the MCP server falls back to it when no `ARTIFACT_HOST_API_KEY` env var is set.
- **Welcome email** via Mailgun (using existing CE infrastructure on `mg.815media.com`; will move to `mg.champlinenterprises.com` as part of the 815→CE transition). HTML + plain-text templates.
- **Marketing page** at `https://hosting.champlinenterprises.com` — dark-themed single-pager with hero, how-it-works, install snippet, FAQ.
- **MCP package bumped to `artifact-host-mcp@0.2.0`** — adds `signup` tool, makes `ARTIFACT_HOST_API_KEY` optional, falls back to local file.

### Repo split (2026-05-01)
- **Public repo (this one) is now MCP-client-only.** The Laravel API source moved to a private operational repo. The public README is a "use the hosted service" funnel; the open-source surface is the MCP client (also published as [`artifact-host-mcp`](https://www.npmjs.com/package/artifact-host-mcp)).
- Earlier commits in this repo's history still contain the API code as it was before the split — that's fine, the code there was MIT and was briefly public anyway. Future API changes happen in the private repo.

### Released (2026-05-01)
- **`artifact-host-mcp@0.1.0` published to npm.** Users now install via `npx -y artifact-host-mcp` in their MCP client config. Verified: cold-`npx` load + `tools/list` JSON-RPC returns the full 5-tool catalog.

### Security (2026-05-01, pre-tester hardening)
- **Subdomains created with `-php false`.** Plesk default is PHP-on; left unchanged this would let any user upload `shell.php` and get RCE on the VPS shared with every other CE project. Verified end-to-end: a deployed `shell.php` returns its source, not the executed output.
- **Per-user rate limits** via `RateLimiter::for('artifacthost-writes')` (30/min) and `'artifacthost-reads'` (120/min). Keys on `user_id`. Prevents one tester from draining the shared LE 50/week pool.
- **Apex landing page** at `https://hosting.champlinenterprises.com` replaces the default Plesk page so visitors hitting the apex see a real explanation, not the Plesk welcome screen.

### Fixed (2026-05-01, post-deploy bug bash)
- LE CLI invocation: `plesk ext letsencrypt cli.php` doesn't exist on 18.0.77 (errors with "Hook ApiCli is not implemented"). Use `plesk bin extension --exec letsencrypt cli.php --domain X --email Y`.
- Plesk WHCS doesn't allow `subdomain --create -domain <subdomain>` — `-domain` must be a primary domain. For nested apex (e.g. `hosting.<primary>`), pass the multi-segment prefix as the subdomain name: `subdomain --create <sub>.hosting -domain ChamplinEnterprises.com`. Added `subdomainName()` helper.
- Plesk REST `/api/v2/domains?name=` filter is case-insensitive but the response object's `name` field preserves the parent's stored case. Match on `ascii_name` (always lowercase) for stable lookups.
- Plesk CLI `-domain` flag is exact-case-match. Added `ARTIFACTHOST_PLESK_SUBSCRIPTION_DOMAIN` env override (defaults to `subscription_domain` for filesystem, can be set to e.g. `ChamplinEnterprises.com` for the CLI).

### Deployment notes (2026-05-01)
- Production at `https://hosting-api.champlinenterprises.com` on ce-prod (AlmaLinux 8.10, Plesk 18.0.77, PHP 8.4 dedicated FPM, MySQL).
- FPM `request_terminate_timeout = 120s` set on hosting-api pool — LE issuance can take 20-30s and exceeds the default fcgid 40s ceiling.
- DB: `artifact_host` (user `artifact_host_user`, creds in `/root/.artifact-host-creds` on VPS).
- Plesk REST API key: dedicated, bound to `127.0.0.1`, label `artifact-host MCP API v3` (two prior keys leaked to terminal during setup; both revoked).
- Sudoers: champline already has `NOPASSWD: ALL` (existing `/etc/sudoers.d/champline`), so PleskService shell-outs work without a new entry.
- Apex `hosting.champlinenterprises.com` + wildcard `*.hosting.champlinenterprises.com` DNS in place.

### Added
- Initial scaffold of `api/` (Laravel 11) and `mcp/` (TypeScript MCP server).
- 5 MCP tools wired end-to-end: `deploy_site`, `list_sites`, `update_site`, `delete_site`, `get_logs`.
- `ApiKeyAuth` middleware with sha256-hashed bearer keys.
- `PleskService` shells out via `sudo plesk bin subdomain` / `plesk ext letsencrypt` (REST v2 used only for ID lookup).
- `SftpService` does atomic-ish doc-root replacement (staging dir + two `mv`s + cleanup) with path-traversal guards and per-file / total size caps.
- `users:create` and `users:rotate-key` artisan commands. Plain-text key shown once, sha256 stored.
- Reserved-subdomain seeder (13 entries) and `Subdomain` validation rule.
- Apex domain configurable via `ARTIFACTHOST_APEX` (default `hosting.champlinenterprises.com`).
- Top-level README with VPS install steps, sudoers snippet, and MCP client config JSON.

### Notes
- API runs on-box (CLI shell-out is the reliable subdomain-create path; REST v2 has no clean equivalent).
- LE issuance is best-effort; sites work over HTTP first, `ssl_enabled` flag tracks status.
- Plesk LE 50-cert/week limit is per *registered* domain — wildcard cert at the apex is the future fix; not implemented in v1.
