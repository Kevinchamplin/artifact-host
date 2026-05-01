import { readStoredKey } from "./auth-store.js";

export interface Config {
  apiUrl: string;
  apiKey: string | null;
  timeoutMs: number;
}

export function loadConfig(): Config {
  const apiUrl = (process.env.ARTIFACT_HOST_API_URL ?? "https://hosting-api.champlinenterprises.com").trim();
  const envKey = process.env.ARTIFACT_HOST_API_KEY?.trim();
  const apiKey = envKey && envKey.length > 0 ? envKey : readStoredKey();
  const timeoutMs = Number(process.env.ARTIFACT_HOST_TIMEOUT_MS ?? 60000);

  if (!apiUrl) {
    throw new Error("ARTIFACT_HOST_API_URL is required");
  }
  if (!Number.isFinite(timeoutMs) || timeoutMs <= 0) {
    throw new Error("ARTIFACT_HOST_TIMEOUT_MS must be a positive number");
  }

  return {
    apiUrl: apiUrl.replace(/\/+$/, ""),
    apiKey,
    timeoutMs,
  };
}
