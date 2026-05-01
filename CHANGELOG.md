# Changelog

All notable changes to artifact-host will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
