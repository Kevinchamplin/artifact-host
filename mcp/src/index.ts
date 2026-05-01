#!/usr/bin/env node
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { z } from "zod";
import { loadConfig } from "./config.js";
import { ApiError, ArtifactHostClient } from "./client.js";
import { dispatch, toolDefinitions } from "./tools.js";

async function main(): Promise<void> {
  const config = loadConfig();
  const client = new ArtifactHostClient(config);

  const server = new Server(
    { name: "artifact-host", version: "0.1.0" },
    { capabilities: { tools: {} } },
  );

  server.setRequestHandler(ListToolsRequestSchema, async () => ({
    tools: toolDefinitions.map((t) => ({ ...t })),
  }));

  server.setRequestHandler(CallToolRequestSchema, async (req) => {
    const { name, arguments: args } = req.params;

    try {
      const result = await dispatch(client, name, args);
      return {
        content: [{ type: "text", text: JSON.stringify(result, null, 2) }],
      };
    } catch (err) {
      const message = formatError(err);
      return {
        content: [{ type: "text", text: message }],
        isError: true,
      };
    }
  });

  const transport = new StdioServerTransport();
  await server.connect(transport);
}

function formatError(err: unknown): string {
  if (err instanceof z.ZodError) {
    return "validation error: " + err.issues.map((i) => `${i.path.join(".")}: ${i.message}`).join("; ");
  }
  if (err instanceof ApiError) {
    return `api error (HTTP ${err.status}): ${err.message}`;
  }
  if (err instanceof Error) {
    return err.message;
  }
  return String(err);
}

main().catch((err) => {
  // eslint-disable-next-line no-console
  console.error("[artifact-host-mcp] fatal:", err);
  process.exit(1);
});
