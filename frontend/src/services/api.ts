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

export function getErrorMessage(
  err: unknown,
  fallback = "Erreur inconnue",
): string {
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
  if (
    options.body &&
    !(options.body instanceof FormData) &&
    !headers["Content-Type"]
  ) {
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

export async function loginWithDev(
  username: string,
  password: string,
): Promise<string> {
  const response = await fetch(`${API_BASE}${endpoints.login.dev}`, {
    body: JSON.stringify({ username, password }),
    headers: { "Content-Type": "application/json" },
    method: "POST",
  });

  if (!response.ok) {
    throw new Error("Échec de la connexion dev");
  }

  const data = (await response.json()) as { token: string };
  setToken(data.token);
  return data.token;
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
