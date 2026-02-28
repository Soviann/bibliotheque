import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  apiFetch,
  getToken,
  isAuthenticated,
  loginWithGoogle,
  removeToken,
  setToken,
} from "../../services/api";

describe("Token management", () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it("getToken returns null when no token stored", () => {
    expect(getToken()).toBeNull();
  });

  it("setToken stores and getToken retrieves the token", () => {
    setToken("my-token");
    expect(getToken()).toBe("my-token");
  });

  it("removeToken clears the token", () => {
    setToken("my-token");
    removeToken();
    expect(getToken()).toBeNull();
  });

  it("isAuthenticated returns false without token", () => {
    expect(isAuthenticated()).toBe(false);
  });

  it("isAuthenticated returns true with token", () => {
    setToken("my-token");
    expect(isAuthenticated()).toBe(true);
  });
});

describe("apiFetch", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("sends Accept header as application/ld+json", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ data: 1 }), { status: 200 }),
    );

    await apiFetch("/test");

    expect(fetch).toHaveBeenCalledWith(
      "/api/test",
      expect.objectContaining({
        headers: expect.objectContaining({
          Accept: "application/ld+json",
        }),
      }),
    );
  });

  it("attaches Authorization header when token exists", async () => {
    setToken("jwt-123");
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({}), { status: 200 }),
    );

    await apiFetch("/test");

    expect(fetch).toHaveBeenCalledWith(
      "/api/test",
      expect.objectContaining({
        headers: expect.objectContaining({
          Authorization: "Bearer jwt-123",
        }),
      }),
    );
  });

  it("does not attach Authorization header without token", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({}), { status: 200 }),
    );

    await apiFetch("/test");

    const headers = vi.mocked(fetch).mock.calls[0][1]?.headers as Record<
      string,
      string
    >;
    expect(headers.Authorization).toBeUndefined();
  });

  it("sets Content-Type for JSON body", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({}), { status: 200 }),
    );

    await apiFetch("/test", {
      body: JSON.stringify({ a: 1 }),
      method: "POST",
    });

    expect(fetch).toHaveBeenCalledWith(
      "/api/test",
      expect.objectContaining({
        headers: expect.objectContaining({
          "Content-Type": "application/ld+json",
        }),
      }),
    );
  });

  it("does not set Content-Type for FormData body", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({}), { status: 200 }),
    );

    await apiFetch("/test", {
      body: new FormData(),
      method: "POST",
    });

    const headers = vi.mocked(fetch).mock.calls[0][1]?.headers as Record<
      string,
      string
    >;
    expect(headers["Content-Type"]).toBeUndefined();
  });

  it("returns undefined for 204 No Content", async () => {
    vi.mocked(fetch).mockResolvedValue(new Response(null, { status: 204 }));

    const result = await apiFetch("/test");
    expect(result).toBeUndefined();
  });

  it("throws on non-ok response with detail message", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ detail: "Not found" }), { status: 404 }),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Not found");
  });

  it("throws generic message when response has no detail", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response("{}", { status: 500 }),
    );

    await expect(apiFetch("/test")).rejects.toThrow("Erreur 500");
  });

  it("removes token and redirects on 401", async () => {
    setToken("expired-token");
    const locationSpy = vi.spyOn(window, "location", "get").mockReturnValue({
      ...window.location,
      href: "",
    });
    // We need to mock location.href setter
    const hrefSetter = vi.fn();
    Object.defineProperty(window, "location", {
      configurable: true,
      value: { href: "" },
      writable: true,
    });

    vi.mocked(fetch).mockResolvedValue(new Response(null, { status: 401 }));

    await expect(apiFetch("/test")).rejects.toThrow("Non authentifié");
    expect(getToken()).toBeNull();

    locationSpy.mockRestore();
  });
});

describe("loginWithGoogle", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("sends credential and stores token on success", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ token: "new-jwt" }), { status: 200 }),
    );

    const token = await loginWithGoogle("google-id-token-123");

    expect(token).toBe("new-jwt");
    expect(getToken()).toBe("new-jwt");
    expect(fetch).toHaveBeenCalledWith(
      "/api/login/google",
      expect.objectContaining({
        body: JSON.stringify({ credential: "google-id-token-123" }),
        headers: { "Content-Type": "application/json" },
        method: "POST",
      }),
    );
  });

  it("throws on unauthorized email", async () => {
    vi.mocked(fetch).mockResolvedValue(
      new Response(
        JSON.stringify({ error: "Adresse email non autorisée." }),
        { status: 403 },
      ),
    );

    await expect(loginWithGoogle("bad-token")).rejects.toThrow();
    expect(getToken()).toBeNull();
  });
});
