import { render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

// Mock @react-oauth/google
vi.mock("@react-oauth/google", () => ({
  GoogleLogin: ({ onSuccess }: { onSuccess: (response: { credential: string }) => void }) => (
    <button
      data-testid="google-login-btn"
      onClick={() => onSuccess({ credential: "mock-credential" })}
      type="button"
    >
      Se connecter avec Google
    </button>
  ),
}));

// Must import after mock
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import Login from "../../pages/Login";

describe("Login", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  function renderLogin() {
    const queryClient = new QueryClient({
      defaultOptions: { mutations: { retry: false }, queries: { retry: false } },
    });
    return render(
      <QueryClientProvider client={queryClient}>
        <MemoryRouter>
          <Login />
        </MemoryRouter>
      </QueryClientProvider>,
    );
  }

  it("renders app title", () => {
    renderLogin();
    expect(screen.getByText("Bibliothèque")).toBeInTheDocument();
  });

  it("renders Google login button", () => {
    renderLogin();
    expect(screen.getByTestId("google-login-btn")).toBeInTheDocument();
  });

  it("does not render email or password fields", () => {
    renderLogin();
    expect(screen.queryByLabelText("Email")).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Mot de passe")).not.toBeInTheDocument();
  });
});
