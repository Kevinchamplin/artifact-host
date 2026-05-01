# artifact-host

> One-call static site deploys from any MCP-aware client.

Tell your AI "deploy this HTML/JS to a subdomain" — it does. You get a live HTTPS URL in ~25 seconds.

## Install

Add to your MCP client config (e.g. `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "artifact-host": {
      "command": "npx",
      "args": ["-y", "artifact-host-mcp"],
      "env": {
        "ARTIFACT_HOST_API_URL": "https://hosting-api.champlinenterprises.com",
        "ARTIFACT_HOST_API_KEY": "ah_..."
      }
    }
  }
}
```

Restart your client. That's it — no install step, `npx` fetches and runs the [`artifact-host-mcp`](https://www.npmjs.com/package/artifact-host-mcp) package on demand.

## Get an API key

Closed beta. Reach out via [a GitHub issue](https://github.com/Kevinchamplin/artifact-host/issues/new) or DM Kevin (links on [his site](https://champlinenterprises.com/contact)).

## What you can ask the AI to do

| Tool | What it does |
|---|---|
| `deploy_site(subdomain, files[])` | New site at `<subdomain>.hosting.champlinenterprises.com` with HTTPS |
| `list_sites()` | Your sites |
| `update_site(subdomain, files[])` | Atomic-ish file replace |
| `delete_site(subdomain)` | Tear down |
| `get_logs(subdomain, lines)` | Tail access + error logs |

`deploy_site` and `update_site` need an `index.html` at the root of `files[]`.

## Limits

- **Static only.** No PHP execution. `.php` files are served as plain text.
- **Rate limits.** 30 writes/min and 120 reads/min per API key. 429 with `Retry-After` on overflow.
- **File caps.** 5 MB per file, 100 MB per deploy.
- **Subdomain rules.** 1-63 chars, lowercase alphanumeric + hyphens, no leading/trailing hyphen, not in the reserved list (`www`, `api`, `admin`, etc.), not already taken.

## Architecture

```
MCP client (Claude Desktop, etc.)
       │  stdio (JSON-RPC)
       ▼
 artifact-host-mcp  ──────► HTTPS (Bearer api_key)
   (npx, local)                   │
                                  ▼
                       hosted Laravel API on a Plesk VPS
                                  │
                                  ▼
                       <sub>.hosting.champlinenterprises.com
```

The MCP client (this repo + the npm package) is open source. The server is operational code Kevin runs; not bundled here.

## License

MIT. See [LICENSE](./LICENSE).

## Development

This repo holds the MCP client source. To work on it:

```bash
cd mcp
npm install
npm run build
node dist/index.js  # then connect from your MCP client
```

To publish a new version: bump `mcp/package.json`, then `cd mcp && npm publish --access public` (requires Kevin's npm publish token).
