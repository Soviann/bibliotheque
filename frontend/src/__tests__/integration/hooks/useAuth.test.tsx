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
    expect(mockNavigate).toHaveBeenCalledWith("/");
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
    expect(mockNavigate).toHaveBeenCalledWith("/login");
  });
});
