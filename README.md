# artifact-host

> One-call static site deploys from any MCP-aware client.

Tell your AI "deploy this HTML/JS to a subdomain" — it does. You get a live HTTPS URL in ~25 seconds.

## Install

**Easiest (Mac/Linux/WSL):**

```bash
curl -fsSL https://hosting.champlinenterprises.com/install.sh | bash
```

The script detects your OS, finds Claude Desktop's config, backs up any existing one, merges in the artifact-host server entry (won't clobber other MCP servers you have), and tells you to restart Claude. ~5 seconds.

**Or paste manually** into `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "artifact-host": {
      "command": "npx",
      "args": ["-y", "artifact-host-mcp"],
      "env": {
        "ARTIFACT_HOST_API_URL": "https://hosting-api.champlinenterprises.com"
      }
    }
  }
}
```

**Or fetch from a URL** if your MCP client supports it: `https://hosting.champlinenterprises.com/install.json`

Restart your client. **No API key needed up front** — the first time you ask the AI to deploy, it'll call the `signup` tool with your email, your account is created automatically, the key is saved to `~/.artifact-host/auth.json`, and a welcome email lands. From then on every deploy is one sentence away.

Full landing page (with a demo conversation, install tabs, FAQ) at [hosting.champlinenterprises.com](https://hosting.champlinenterprises.com).

## What you can ask the AI to do

| Tool | What it does |
|---|---|
| `signup(email)` | Create your account on the first call. Auto-saves the API key locally. |
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
