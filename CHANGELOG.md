# Changelog

All notable changes to artifact-host will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
