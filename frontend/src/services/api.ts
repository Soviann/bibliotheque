import { del, set } from "idb-keyval";
import { endpoints } from "../endpoints";

const API_BASE = "/api";
const TOKEN_KEY = "jwt_token";
const IDB_TOKEN_KEY = "jwt_token_sw";

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
  void set(IDB_TOKEN_KEY, token);
}

export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY);
  void del(IDB_TOKEN_KEY);
}

export function isAuthenticated(): boolean {
  return getToken() !== null;
}

export function getErrorMessage(err: unknown, fallback = "Erreur inconnue"): string {
  if (err instanceof Error) return err.message;
  if (typeof err === "string") return err;
  if (err !== null && err !== undefined) return String(err);
  return fallback;
}

export function handleUnauthorized(): void {
  if (navigator.onLine) {
    removeToken();
    window.location.href = "/login";
  }
}

const SERVER_ERROR_PATTERNS = [
  /SQLSTATE/i,
  /exception.*driver/i,
  /stack trace/i,
  /vendor\//i,
];

function sanitizeErrorMessage(message: string, status: number): string {
  if (status >= 500 && SERVER_ERROR_PATTERNS.some((p) => p.test(message))) {
    return "Erreur serveur";
  }
  return message;
}

export async function apiFetch<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    Accept: "application/ld+json",
    ...((options.headers as Record<string, string>) ?? {}),
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  // Only set Content-Type for requests with body (not FormData), unless already set
  if (options.body && !(options.body instanceof FormData) && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/ld+json";
  }

  let response: Response;
  try {
    response = await fetch(`${API_BASE}${path}`, {
      ...options,
      headers,
    });
  } catch (err) {
    // Network error — if offline, don't clear token
    if (!navigator.onLine) {
      throw new Error("Vous êtes hors ligne");
    }
    throw err;
  }

  if (response.status === 401) {
    handleUnauthorized();
    throw new Error("Non authentifié");
  }

  if (!response.ok) {
    const error = await response.json().catch(() => ({}));
    const body = error as { detail?: string; error?: string };
    const message = body.detail ?? body.error ?? `Erreur ${response.status}`;
    throw new Error(sanitizeErrorMessage(message, response.status));
  }

  // 204 No Content — callers should type T as void for DELETE endpoints
  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

export async function fetchSSE<TMessage, TComplete>(
  path: string,
  body: Record<string, unknown>,
  onMessage: (data: TMessage) => void,
  onComplete: (data: TComplete) => void,
  onError: (error: Error) => void,
  signal?: AbortSignal,
): Promise<void> {
  const token = getToken();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let response: Response;
  try {
    response = await fetch(`${API_BASE}${path}`, {
      body: JSON.stringify(body),
      headers,
      method: "POST",
      signal,
    });
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    onError(err instanceof Error ? err : new Error(String(err)));
    return;
  }

  if (response.status === 401) {
    handleUnauthorized();
    onError(new Error("Non authentifié"));
    return;
  }

  if (!response.ok) {
    const errBody = (await response.json().catch(() => ({}))) as {
      detail?: string;
      error?: string;
    };
    onError(
      new Error(
        errBody.detail ?? errBody.error ?? `Erreur ${response.status}`,
      ),
    );
    return;
  }

  const reader = response.body?.getReader();
  if (!reader) {
    onError(new Error("ReadableStream non supporté"));
    return;
  }

  const decoder = new TextDecoder();
  let buffer = "";

  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split("\n");
      buffer = lines.pop() ?? "";

      let currentEvent = "";

      for (const line of lines) {
        if (line.startsWith("event: ")) {
          currentEvent = line.slice(7).trim();
        } else if (line.startsWith("data: ")) {
          const json = line.slice(6);
          try {
            const parsed = JSON.parse(json);
            if (currentEvent === "complete") {
              onComplete(parsed as TComplete);
            } else {
              onMessage(parsed as TMessage);
            }
          } catch {
            // Ignorer les lignes JSON invalides
          }
          currentEvent = "";
        }
      }
    }
  } catch (err) {
    if (err instanceof DOMException && err.name === "AbortError") return;
    onError(err instanceof Error ? err : new Error(String(err)));
  }
}

export async function loginWithGoogle(credential: string): Promise<string> {
  const response = await fetch(`${API_BASE}${endpoints.login.google}`, {
    body: JSON.stringify({ credential }),
    headers: { "Content-Type": "application/json" },
    method: "POST",
  });

  if (!response.ok) {
    const data = (await response.json().catch(() => ({}))) as {
      error?: string;
    };
    throw new Error(data.error ?? "Échec de la connexion Google");
  }

  const data = (await response.json()) as { token: string };
  setToken(data.token);
  return data.token;
}
