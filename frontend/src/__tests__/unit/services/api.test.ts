import "fake-indexeddb/auto";
import { http, HttpResponse } from "msw";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  apiFetch,
  getToken,
  isAuthenticated,
  loginWithGoogle,
  removeToken,
  setToken,
} from "../../../services/api";
import { server } from "../../helpers/server";

describe("Token helpers", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("getToken returns null when no token is stored", () => {
    expect(getToken()).toBeNull();
  });

  it("setToken stores a token in localStorage", () => {
    setToken("my-token");
    expect(localStorage.getItem("jwt_token")).toBe("my-token");
  });

  it("getToken returns the stored token", () => {
    setToken("my-token");
    expect(getToken()).toBe("my-token");
  });

  it("removeToken removes the token from localStorage", () => {
    setToken("my-token");
    removeToken();
    expect(getToken()).toBeNull();
  });
});

describe("apiFetch", () => {
  beforeEach(() => {
    localStorage.clear();
    // Ensure navigator.onLine is true by default
    Object.defineProperty(navigator, "onLine", { configurable: true, value: true, writable: true });
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("sends Accept: application/ld+json header", async () => {
    let capturedHeaders: Headers | undefined;

    server.use(
      http.get("/api/test", ({ request }) => {
        capturedHeaders = request.headers;
        return HttpResponse.json({ data: "ok" });
      }),
    );

    await apiFetch("/test");

    expect(capturedHeaders?.get("Accept")).toBe("application/ld+json");
  });

  it("adds Authorization header when token exists", async () => {
    setToken("test-jwt-token");
    let capturedHeaders: Headers | undefined;

    server.use(
      http.get("/api/test", ({ request }) => {
        capturedHeaders = request.headers;
        return HttpResponse.json({ data: "ok" });
      }),
    );

    await apiFetch("/test");

    expect(capturedHeaders?.get("Authorization")).toBe("Bearer test-jwt-token");
  });

  it("does NOT add Authorization header when no token", async () => {
    let capturedHeaders: Headers | undefined;

    server.use(
      http.get("/api/test", ({ request }) => {
        capturedHeaders = request.headers;
        return HttpResponse.json({ data: "ok" });
      }),
    );

    await apiFetch("/test");

    expect(capturedHeaders?.get("Authorization")).toBeNull();
  });

  it("sets Content-Type for POST with JSON body", async () => {
    let capturedHeaders: Headers | undefined;

    server.use(
      http.post("/api/test", ({ request }) => {
        capturedHeaders = request.headers;
        return HttpResponse.json({ id: 1 }, { status: 201 });
      }),
    );

    await apiFetch("/test", {
      body: JSON.stringify({ title: "Test" }),
      method: "POST",
    });

    expect(capturedHeaders?.get("Content-Type")).toBe("application/ld+json");
  });

  it("preserves custom Content-Type when provided", async () => {
    let capturedHeaders: Headers | undefined;

    server.use(
      http.patch("/api/test", ({ request }) => {
        capturedHeaders = request.headers;
        return HttpResponse.json({ id: 1 });
      }),
    );

    await apiFetch("/test", {
      body: JSON.stringify({ bought: true }),
      headers: { "Content-Type": "application/merge-patch+json" },
      method: "PATCH",
    });

    expect(capturedHeaders?.get("Content-Type")).toBe("application/merge-patch+json");
  });

  it("sends JSON body correctly for POST", async () => {
    let capturedBody: unknown;

    server.use(
      http.post("/api/test", async ({ request }) => {
        capturedBody = await request.json();
        return HttpResponse.json({ id: 1 }, { status: 201 });
      }),
    );

    await apiFetch("/test", {
      body: JSON.stringify({ title: "My Comic" }),
      method: "POST",
    });

    expect(capturedBody).toEqual({ title: "My Comic" });
  });

  it("sends JSON body correctly for PUT", async () => {
    let capturedBody: unknown;

    server.use(
      http.put("/api/test/1", async ({ request }) => {
        capturedBody = await request.json();
        return HttpResponse.json({ id: 1, title: "Updated" });
      }),
    );

    await apiFetch("/test/1", {
      body: JSON.stringify({ title: "Updated" }),
      method: "PUT",
    });

    expect(capturedBody).toEqual({ title: "Updated" });
  });

  it("throws on non-OK responses", async () => {
    server.use(
      http.get("/api/test", () =>
        HttpResponse.json({ detail: "Not found" }, { status: 404 }),
      ),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Not found");
  });

  it("throws generic error message when response has no detail", async () => {
    server.use(
      http.get("/api/test", () =>
        HttpResponse.json({}, { status: 500 }),
      ),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Erreur 500");
  });

  it("handles 401 by removing token and redirecting to /login", async () => {
    setToken("expired-token");

    // Replace window.location with a writable mock
    const originalLocation = window.location;
    Object.defineProperty(window, "location", {
      configurable: true,
      value: { ...originalLocation, href: originalLocation.href },
      writable: true,
    });

    server.use(
      http.get("/api/test", () =>
        new HttpResponse(null, { status: 401 }),
      ),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Non authentifié");
    expect(getToken()).toBeNull();
    expect(window.location.href).toBe("/login");

    // Restore original location
    Object.defineProperty(window, "location", {
      configurable: true,
      value: originalLocation,
      writable: true,
    });
  });

  it("returns undefined for 204 No Content", async () => {
    server.use(
      http.delete("/api/test/1", () =>
        new HttpResponse(null, { status: 204 }),
      ),
    );

    const result = await apiFetch("/test/1", { method: "DELETE" });
    expect(result).toBeUndefined();
  });

  it("merges custom headers from options", async () => {
    let capturedHeaders: Headers | undefined;

    server.use(
      http.get("/api/test", ({ request }) => {
        capturedHeaders = request.headers;
        return HttpResponse.json({ data: "ok" });
      }),
    );

    await apiFetch("/test", {
      headers: { "X-Custom-Header": "custom-value" },
    });

    expect(capturedHeaders?.get("X-Custom-Header")).toBe("custom-value");
    // Default headers should still be present
    expect(capturedHeaders?.get("Accept")).toBe("application/ld+json");
  });

  it("falls back to generic error when non-OK response has non-JSON body", async () => {
    server.use(
      http.get("/api/test", () =>
        new HttpResponse("not json", {
          headers: { "Content-Type": "text/plain" },
          status: 500,
        }),
      ),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Erreur 500");
  });

  it("does not set Content-Type for FormData body", async () => {
    let capturedContentType: string | null = null;

    server.use(
      http.post("/api/upload", ({ request }) => {
        capturedContentType = request.headers.get("Content-Type");
        return HttpResponse.json({ id: 1 }, { status: 201 });
      }),
    );

    const formData = new FormData();
    formData.append("file", new Blob(["data"]), "test.jpg");

    await apiFetch("/upload", {
      body: formData,
      method: "POST",
    });

    // Content-Type should NOT be application/ld+json for FormData
    expect(capturedContentType).not.toBe("application/ld+json");
  });
});

describe("isAuthenticated", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("returns true when token exists", () => {
    setToken("some-token");
    expect(isAuthenticated()).toBe(true);
  });

  it("returns false when no token", () => {
    expect(isAuthenticated()).toBe(false);
  });
});

describe("apiFetch — offline scenarios", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
    Object.defineProperty(navigator, "onLine", { configurable: true, value: true, writable: true });
  });

  it("does NOT remove token and does NOT redirect on 401 while offline", async () => {
    setToken("my-token");
    Object.defineProperty(navigator, "onLine", { configurable: true, value: false, writable: true });

    server.use(
      http.get("/api/test", () =>
        new HttpResponse(null, { status: 401 }),
      ),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Non authentifié");
    // Token should NOT be removed when offline
    expect(getToken()).toBe("my-token");
  });

  it("throws 'Vous êtes hors ligne' on network error while offline", async () => {
    Object.defineProperty(navigator, "onLine", { configurable: true, value: false, writable: true });

    server.use(
      http.get("/api/test", () => {
        return HttpResponse.error();
      }),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Vous êtes hors ligne");
  });

  it("re-throws the original error on network error while online", async () => {
    Object.defineProperty(navigator, "onLine", { configurable: true, value: true, writable: true });

    server.use(
      http.get("/api/test", () => {
        return HttpResponse.error();
      }),
    );

    await expect(apiFetch("/test")).rejects.toThrow();
  });
});

describe("loginWithGoogle", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("sends credential and stores returned token", async () => {
    server.use(
      http.post("/api/login/google", async ({ request }) => {
        const body = (await request.json()) as { credential: string };
        expect(body.credential).toBe("google-id-token");
        return HttpResponse.json({ token: "jwt-from-server" });
      }),
    );

    const token = await loginWithGoogle("google-id-token");

    expect(token).toBe("jwt-from-server");
    expect(getToken()).toBe("jwt-from-server");
  });

  it("throws on failed login", async () => {
    server.use(
      http.post("/api/login/google", () =>
        HttpResponse.json({ error: "Invalid token" }, { status: 401 }),
      ),
    );

    await expect(loginWithGoogle("bad-token")).rejects.toThrow("Invalid token");
    expect(getToken()).toBeNull();
  });

  it("throws generic error when response has no error field", async () => {
    server.use(
      http.post("/api/login/google", () =>
        HttpResponse.json({}, { status: 500 }),
      ),
    );

    await expect(loginWithGoogle("bad-token")).rejects.toThrow(
      "Échec de la connexion Google",
    );
  });

  it("falls back to empty object when error response body is not valid JSON", async () => {
    server.use(
      http.post("/api/login/google", () =>
        new HttpResponse("not json", { headers: { "Content-Type": "text/plain" }, status: 500 }),
      ),
    );

    await expect(loginWithGoogle("bad-token")).rejects.toThrow(
      "Échec de la connexion Google",
    );
  });
});
