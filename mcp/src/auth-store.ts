import { mkdirSync, readFileSync, writeFileSync, chmodSync, existsSync } from "node:fs";
import { homedir, platform } from "node:os";
import { join, dirname } from "node:path";

interface StoredAuth {
  api_key: string;
  email?: string;
  api_url?: string;
  saved_at?: string;
}

function authFilePath(): string {
  return join(homedir(), ".artifact-host", "auth.json");
}

export function readStoredKey(): string | null {
  const path = authFilePath();
  if (!existsSync(path)) return null;
  try {
    const raw = readFileSync(path, "utf8");
    const parsed = JSON.parse(raw) as Partial<StoredAuth>;
    if (typeof parsed.api_key === "string" && parsed.api_key.length > 0) {
      return parsed.api_key;
    }
  } catch {
    // unreadable / malformed — fall through to null
  }
  return null;
}

export function writeStoredKey(data: { api_key: string; email?: string; api_url?: string }): string {
  const path = authFilePath();
  const dir = dirname(path);
  mkdirSync(dir, { recursive: true });

  const payload: StoredAuth = {
    api_key: data.api_key,
    email: data.email,
    api_url: data.api_url,
    saved_at: new Date().toISOString(),
  };

  writeFileSync(path, JSON.stringify(payload, null, 2), { encoding: "utf8" });

  if (platform() !== "win32") {
    try { chmodSync(path, 0o600); } catch { /* best-effort */ }
  }

  return path;
}
