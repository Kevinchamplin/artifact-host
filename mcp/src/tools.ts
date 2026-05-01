import { z } from "zod";
import type { ArtifactHostClient } from "./client.js";

const fileSchema = z.object({
  path: z.string().min(1).max(512),
  content: z.string(),
  encoding: z.enum(["utf8", "base64"]).optional(),
});

const subdomainSchema = z
  .string()
  .regex(
    /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/,
    "subdomain must be 1-63 chars, lowercase alphanumeric + hyphens, no leading/trailing hyphen",
  );

export const inputSchemas = {
  deploy_site: z.object({
    subdomain: subdomainSchema,
    files: z.array(fileSchema).min(1),
  }),
  update_site: z.object({
    subdomain: subdomainSchema,
    files: z.array(fileSchema).min(1),
  }),
  list_sites: z.object({}),
  delete_site: z.object({
    subdomain: subdomainSchema,
  }),
  get_logs: z.object({
    subdomain: subdomainSchema,
    lines: z.number().int().min(1).max(1000).optional(),
  }),
};

export const toolDefinitions = [
  {
    name: "deploy_site",
    description:
      "Deploy a static site (HTML/JS/CSS) to a new subdomain. Requires an index.html at the root of files[]. Creates the subdomain, uploads files, and provisions a Let's Encrypt certificate.",
    inputSchema: {
      type: "object",
      properties: {
        subdomain: {
          type: "string",
          description: "1-63 chars, lowercase alphanumeric + hyphens, no leading/trailing hyphen",
        },
        files: {
          type: "array",
          minItems: 1,
          items: {
            type: "object",
            required: ["path", "content"],
            properties: {
              path: { type: "string", description: "Relative path within doc root, e.g. 'index.html' or 'assets/app.js'" },
              content: { type: "string", description: "File contents" },
              encoding: { type: "string", enum: ["utf8", "base64"], description: "Default 'utf8'. Use 'base64' for binary files." },
            },
          },
        },
      },
      required: ["subdomain", "files"],
    },
  },
  {
    name: "list_sites",
    description: "List all sites owned by the authenticated user.",
    inputSchema: { type: "object", properties: {} },
  },
  {
    name: "update_site",
    description: "Replace all files for an existing site (atomic-ish swap). Requires an index.html at the root of files[].",
    inputSchema: {
      type: "object",
      properties: {
        subdomain: { type: "string" },
        files: {
          type: "array",
          minItems: 1,
          items: {
            type: "object",
            required: ["path", "content"],
            properties: {
              path: { type: "string" },
              content: { type: "string" },
              encoding: { type: "string", enum: ["utf8", "base64"] },
            },
          },
        },
      },
      required: ["subdomain", "files"],
    },
  },
  {
    name: "delete_site",
    description: "Tear down a site: remove files, delete the Plesk subdomain, drop the database row.",
    inputSchema: {
      type: "object",
      properties: { subdomain: { type: "string" } },
      required: ["subdomain"],
    },
  },
  {
    name: "get_logs",
    description: "Tail the access and error logs for a site.",
    inputSchema: {
      type: "object",
      properties: {
        subdomain: { type: "string" },
        lines: { type: "integer", minimum: 1, maximum: 1000, default: 100 },
      },
      required: ["subdomain"],
    },
  },
] as const;

export async function dispatch(
  client: ArtifactHostClient,
  name: string,
  args: unknown,
): Promise<unknown> {
  switch (name) {
    case "deploy_site": {
      const input = inputSchemas.deploy_site.parse(args);
      requireIndexHtml(input.files);
      return client.deploy(input.subdomain, input.files);
    }
    case "update_site": {
      const input = inputSchemas.update_site.parse(args);
      requireIndexHtml(input.files);
      return client.update(input.subdomain, input.files);
    }
    case "list_sites": {
      inputSchemas.list_sites.parse(args ?? {});
      return client.list();
    }
    case "delete_site": {
      const input = inputSchemas.delete_site.parse(args);
      return client.delete(input.subdomain);
    }
    case "get_logs": {
      const input = inputSchemas.get_logs.parse(args);
      return client.logs(input.subdomain, input.lines ?? 100);
    }
    default:
      throw new Error(`unknown tool: ${name}`);
  }
}

function requireIndexHtml(files: { path: string }[]): void {
  const has = files.some((f) => normalizePath(f.path) === "index.html");
  if (!has) {
    throw new Error("index.html is required at the root of files[]");
  }
}

function normalizePath(p: string): string {
  return p.replace(/\\/g, "/").replace(/^\/+/, "");
}
