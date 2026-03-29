import "fake-indexeddb/auto";
import { QueryClientProvider } from "@tanstack/react-query";
import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { useAuth } from "../../../hooks/useAuth";
import { createTestQueryClient } from "../../helpers/test-utils";
import { server } from "../../helpers/server";

const mockNavigate = vi.fn();

vi.mock("react-router-dom", async () => {
  const actual = await vi.importActual("react-router-dom");
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

function createWrapper() {
  const queryClient = createTestQueryClient();
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    );
  };
}

describe("useAuth", () => {
  beforeEach(() => {
    localStorage.clear();
    mockNavigate.mockReset();
    vi.stubGlobal("caches", { delete: vi.fn().mockResolvedValue(true) });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("returns isAuthenticated false when no token", () => {
    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    expect(result.current.isAuthenticated).toBe(false);
  });

  it("returns isAuthenticated true when token exists", () => {
    localStorage.setItem("jwt_token", "some-token");

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    expect(result.current.isAuthenticated).toBe(true);
  });

  it("loginPending is true during in-flight request", async () => {
    let resolveLogin: (() => void) | null = null;

    server.use(
      http.post("/api/login/google", async () => {
        await new Promise<void>((resolve) => { resolveLogin = resolve; });
        return HttpResponse.json({ token: "jwt" });
      }),
    );

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    act(() => {
      result.current.login("credential");
    });

    // Wait for the mutation to start and loginPending to become true
    await waitFor(() => expect(result.current.loginPending).toBe(true));

    // Now resolve the request
    resolveLogin?.();

    await waitFor(() => expect(result.current.loginPending).toBe(false));
  });

  it("isAuthenticated becomes true after successful login", async () => {
    server.use(
      http.post("/api/login/google", () =>
        HttpResponse.json({ token: "jwt-token" }),
      ),
    );

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    expect(result.current.isAuthenticated).toBe(false);

    await act(async () => {
      result.current.login("google-credential");
    });

    await waitFor(() => expect(result.current.loginPending).toBe(false));

    // Re-read the hook — isAuthenticated reads from localStorage
    expect(result.current.isAuthenticated).toBe(true);
  });

  it("login stores token and navigates to /", async () => {
    server.use(
      http.post("/api/login/google", () =>
        HttpResponse.json({ token: "jwt-from-google" }),
      ),
    );

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.login("google-credential-token");
    });

    await waitFor(() => expect(result.current.loginPending).toBe(false));

    expect(localStorage.getItem("jwt_token")).toBe("jwt-from-google");
    expect(mockNavigate).toHaveBeenCalledWith("/", { viewTransition: true });
  });

  it("login sets error on failure", async () => {
    server.use(
      http.post("/api/login/google", () =>
        HttpResponse.json(
          { error: "Invalid token" },
          { status: 401 },
        ),
      ),
    );

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    await act(async () => {
      result.current.login("bad-credential");
    });

    await waitFor(() => expect(result.current.loginError).not.toBeNull());

    expect(result.current.loginError?.message).toBe("Invalid token");
  });

  it("logout removes token and navigates to /login", () => {
    localStorage.setItem("jwt_token", "some-token");

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    act(() => {
      result.current.logout();
    });

    expect(localStorage.getItem("jwt_token")).toBeNull();
    expect(mockNavigate).toHaveBeenCalledWith("/login", { viewTransition: true });
  });

  it("logout clears SW api-cache after navigation", async () => {
    vi.useFakeTimers();
    localStorage.setItem("jwt_token", "some-token");

    const { result } = renderHook(() => useAuth(), {
      wrapper: createWrapper(),
    });

    act(() => {
      result.current.logout();
    });

    // Cache clearing is deferred via setTimeout(0) to avoid blocking navigation
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0);
    });

    expect(caches.delete).toHaveBeenCalledWith("api-cache");
    vi.useRealTimers();
  });
});
