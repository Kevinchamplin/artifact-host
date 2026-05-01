import type { Config } from "./config.js";

export interface FilePayload {
  path: string;
  content: string;
  encoding?: "utf8" | "base64";
}

export interface DeployResult {
  subdomain: string;
  url: string;
  ssl_enabled: boolean;
  file_count: number;
  bytes: number;
}

export interface UpdateResult {
  subdomain: string;
  url: string;
  file_count: number;
  bytes: number;
}

export interface SiteSummary {
  subdomain: string;
  url: string;
  custom_domain: string | null;
  ssl_enabled: boolean;
  bytes_used: number;
  created_at: string | null;
}

export interface LogsResult {
  subdomain: string;
  access: string;
  error: string;
}

export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly body: unknown,
    message: string,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export class ArtifactHostClient {
  constructor(private readonly cfg: Config) {}

  deploy(subdomain: string, files: FilePayload[]): Promise<DeployResult> {
    return this.request("POST", "/api/v1/sites", { subdomain, files });
  }

  update(subdomain: string, files: FilePayload[]): Promise<UpdateResult> {
    return this.request("PUT", `/api/v1/sites/${encodeURIComponent(subdomain)}`, { files });
  }

  list(): Promise<{ sites: SiteSummary[] }> {
    return this.request("GET", "/api/v1/sites");
  }

  delete(subdomain: string): Promise<{ deleted: string }> {
    return this.request("DELETE", `/api/v1/sites/${encodeURIComponent(subdomain)}`);
  }

  logs(subdomain: string, lines: number): Promise<LogsResult> {
    const qs = new URLSearchParams({ lines: String(lines) });
    return this.request("GET", `/api/v1/sites/${encodeURIComponent(subdomain)}/logs?${qs}`);
  }

  private async request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), this.cfg.timeoutMs);

    let res: Response;
    try {
      res = await fetch(this.cfg.apiUrl + path, {
        method,
        headers: {
          Authorization: `Bearer ${this.cfg.apiKey}`,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: ctrl.signal,
      });
    } catch (err) {
      if ((err as { name?: string }).name === "AbortError") {
        throw new Error(`request timed out after ${this.cfg.timeoutMs}ms: ${method} ${path}`);
      }
      throw err;
    } finally {
      clearTimeout(timer);
    }

    const text = await res.text();
    let parsed: unknown = text;
    if (text.length) {
      try {
        parsed = JSON.parse(text);
      } catch {
        // leave parsed as raw text
      }
    }

    if (!res.ok) {
      const msg = extractErrorMessage(parsed) ?? `HTTP ${res.status}`;
      throw new ApiError(res.status, parsed, msg);
    }

    return parsed as T;
  }
}

function extractErrorMessage(body: unknown): string | undefined {
  if (body && typeof body === "object") {
    const obj = body as Record<string, unknown>;
    if (typeof obj.message === "string") return obj.message;
    if (typeof obj.error === "string") return obj.error;
    if (obj.errors && typeof obj.errors === "object") {
      const first = Object.values(obj.errors as Record<string, unknown>)[0];
      if (Array.isArray(first) && typeof first[0] === "string") return first[0];
    }
  }
  return undefined;
}
